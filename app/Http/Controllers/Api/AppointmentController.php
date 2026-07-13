<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Studio;
use App\Models\User;
use App\Services\AppointmentNotificationService;
use App\Services\AppointmentService;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    // Sistem içinde tipler sabit kalır; kullanıcıya designer=randevu, tattoo=bilet olarak gösterilir.
    public const APPOINTMENT_TYPES = ['designer', 'tattoo'];

    /** Mobil admin paneli için tüm stüdyolardaki randevuları döndürür */
    public function adminIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'studio_id' => ['nullable', 'integer', 'exists:studios,id'],
            'appointment_type' => ['nullable', 'string', 'in:designer,tattoo'],
            'status' => ['nullable', 'string', 'in:confirmed,in_progress,completed,cancelled,rescheduled'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $appointments = Appointment::query()
            ->with(['createdBy', 'assignedArtist', 'studio.company'])
            ->when(isset($filters['studio_id']), fn ($query) => $query
                ->where('studio_id', (int) $filters['studio_id']))
            ->when(isset($filters['company_id']), fn ($query) => $query
                ->whereHas('studio', fn ($studioQuery) => $studioQuery
                    ->where('company_id', (int) $filters['company_id'])))
            ->when(isset($filters['status']), fn ($query) => $query
                ->where('status', $filters['status']))
            ->when(isset($filters['appointment_type']), fn ($query) => $query
                ->where('appointment_type', $filters['appointment_type']))
            ->when(isset($filters['date_from']), fn ($query) => $query
                ->whereDate('appointment_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query
                ->whereDate('appointment_at', '<=', $filters['date_to']))
            ->latest('appointment_at')
            ->get();

        return response()->json([
            'data' => $appointments->map(fn ($appointment): array => [
                'id'               => $appointment->id,
                'customer'         => $this->formatCustomer($appointment),
                'pax'              => $appointment->pax,
                'price'            => $this->visiblePriceFor($appointment),
                'appointment_at'   => optional($appointment->appointment_at)->toIso8601String(),
                'appointment_type' => $appointment->appointment_type,
                'status'           => $appointment->status,
                'artist_status'    => $appointment->artist_status,
                'notes'            => $appointment->notes,
                'source_image_path' => $this->imageUrl($appointment->source_image_path),
                'photo_path'        => $this->imageUrl($appointment->photo_path),
                'tattoo_image_paths' => $this->imageUrls($appointment->tattoo_image_paths),
                'completed_tattoo_image_path' => $this->imageUrl($appointment->completed_tattoo_image_path),
                'pickup_required'   => (bool) $appointment->pickup_required,
                'assigned_artist_user_id' => $appointment->assigned_artist_user_id,
                'artist' => $appointment->assignedArtist ? [
                    'id'            => $appointment->assignedArtist->id,
                    'name'          => $appointment->assignedArtist->fullName(),
                    'profile_image' => $appointment->assignedArtist->profile_image,
                    'rating'        => $appointment->assignedArtist->rating,
                ] : null,
                'studio' => $appointment->studio ? [
                    'id'   => $appointment->studio->id,
                    'name' => $appointment->studio->name,
                    'company' => $appointment->studio->company ? [
                        'id'   => $appointment->studio->company->id,
                        'name' => $appointment->studio->company->name,
                    ] : null,
                ] : null,
                'created_at' => optional($appointment->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    /** Şoförün bağlı olduğu stüdyolardaki pick up randevularını döndürür */
    public function myAppointments(Request $request): JsonResponse
    {
        $user = $request->user();

        $myStudioIds = $user->studios()
            ->wherePivot('is_active', true)
            ->wherePivot('role', UserRole::Sofor->value)
            ->pluck('studios.id');

        $appointments = Appointment::query()
            ->with(['studio.company', 'createdBy'])
            ->whereIn('studio_id', $myStudioIds)
            ->where('pickup_required', true)
            ->orderBy('appointment_at')
            ->get();

        return response()->json([
            'data' => $appointments->map(fn ($appointment): array => [
                'id'     => $appointment->id,
                'studio' => $appointment->studio ? [
                    'id'   => $appointment->studio->id,
                    'name' => $appointment->studio->name,
                ] : null,
                'customer'          => $this->formatCustomer($appointment),
                'place'             => $appointment->place,
                'pax'               => $appointment->pax,
                'price'             => $this->visiblePriceFor($appointment),
                'appointment_at'    => optional($appointment->appointment_at)->toIso8601String(),
                'appointment_type'  => $appointment->appointment_type,
                'status'            => $appointment->status,
                'driver_status'     => $appointment->driver_status,
                'source_image_path' => $this->imageUrl($appointment->source_image_path),
                'photo_path'        => $this->imageUrl($appointment->photo_path),
                'tattoo_image_paths' => $this->imageUrls($appointment->tattoo_image_paths),
                'completed_tattoo_image_path' => $this->imageUrl($appointment->completed_tattoo_image_path),
                'pickup_required'   => (bool) $appointment->pickup_required,
                'notes'             => $appointment->notes,
                'created_by'        => $appointment->createdBy ? [
                    'id'   => $appointment->createdBy->id,
                    'name' => $appointment->createdBy->fullName(),
                ] : null,
                'created_at' => optional($appointment->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    /** Artist/designer kendi aktif stüdyosundaki uygun randevuları görür */
    public function myArtistAppointments(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $artistStudioIds = $this->activeStudioIdsForRole($user, UserRole::Artist);
        $designerStudioIds = $this->activeStudioIdsForRole($user, UserRole::Designer);

        $isIndependentArtist = $user->isIndependentProfessional();

        if ($artistStudioIds === [] && $designerStudioIds === [] && ! $isIndependentArtist) {
            return response()->json(['data' => []]);
        }

        $appointments = Appointment::query()
            ->with(['studio.company', 'createdBy'])
            ->where(function ($query) use ($artistStudioIds, $designerStudioIds, $isIndependentArtist, $user): void {
                if ($artistStudioIds !== []) {
                    $query->orWhere(function ($artistQuery) use ($artistStudioIds): void {
                        $artistQuery
                            ->whereIn('studio_id', $artistStudioIds)
                            ->where('appointment_type', 'tattoo');
                    });
                }

                if ($designerStudioIds !== []) {
                    $query->orWhere(function ($designerQuery) use ($designerStudioIds): void {
                        $designerQuery
                            ->whereIn('studio_id', $designerStudioIds)
                            ->where('appointment_type', 'designer');
                    });
                }

                if ($isIndependentArtist) {
                    $query->orWhere(function ($independentQuery) use ($user): void {
                        $independentQuery
                            ->whereNull('studio_id')
                            ->where('assigned_artist_user_id', $user->id);
                    });
                }
            })
            ->where('assigned_artist_user_id', $user->id)
            ->orderBy('appointment_at')
            ->get();

        return response()->json([
            'data' => $appointments->map(function ($appointment): array {
                $limitedView = $this->artistLimitedView($appointment);

                return [
                    'id'     => $appointment->id,
                    'studio' => $appointment->studio ? [
                        'id'   => $appointment->studio->id,
                        'name' => $appointment->studio->name,
                        'company' => $appointment->studio->company ? [
                            'id'   => $appointment->studio->company->id,
                            'name' => $appointment->studio->company->name,
                        ] : null,
                    ] : null,
                    'customer'         => $limitedView ? [] : $this->formatCustomer($appointment),
                    'place'            => $limitedView ? null : $appointment->place,
                    'pax'              => $limitedView ? null : $appointment->pax,
                    'price'            => $this->visiblePriceFor($appointment),
                    'appointment_at'   => optional($appointment->appointment_at)->toIso8601String(),
                    'appointment_type' => $appointment->appointment_type,
                    'status'           => $appointment->status,
                    'artist_status'    => $appointment->artist_status,
                    'notes'            => $limitedView ? null : $appointment->notes,
                    'source_image_path' => $limitedView ? null : $this->imageUrl($appointment->source_image_path),
                    'photo_path'        => $limitedView ? null : $this->imageUrl($appointment->photo_path),
                    'tattoo_image_paths' => $this->imageUrls($appointment->tattoo_image_paths),
                    'completed_tattoo_image_path' => $this->imageUrl($appointment->completed_tattoo_image_path),
                    'pickup_required'   => $limitedView ? null : (bool) $appointment->pickup_required,
                    'artist_limited_view' => $limitedView,
                    'created_at'       => optional($appointment->created_at)->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function support(Studio $studio, Request $request): JsonResponse
    {
        abort_unless($request->user()?->canManageStudioAppointments($studio), 403);

        $artists = $studio->users()
            ->whereNull('users.banned_at')
            ->wherePivot('role', \App\Enums\UserRole::Artist->value)
            ->wherePivot('is_active', true)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.surname', 'users.phone', 'users.profile_image', 'users.rating']);

        return response()->json([
            'data' => [
                'artists' => $artists->map(fn ($a): array => [
                    'id'            => $a->id,
                    'name'          => $a->fullName(),
                    'phone'         => $a->phone,
                    'profile_image' => $a->profile_image,
                    'rating'        => $a->rating,
                ])->values(),
                'appointment_types' => self::APPOINTMENT_TYPES,
                'statuses' => ['confirmed', 'in_progress', 'completed', 'cancelled', 'rescheduled'],
            ],
        ]);
    }

    public function show(Studio $studio, Appointment $appointment): JsonResponse
    {
        if ((int) $appointment->studio_id !== (int) $studio->id) {
            abort_unless($this->driverCanAccessAppointment($studio, $appointment, request()), 403);
        }

        return $this->appointmentDetailResponse($appointment);
    }

    public function showMine(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $canAccess =
            (int) $appointment->created_by_user_id === (int) $user->id ||
            (int) $appointment->assigned_artist_user_id === (int) $user->id ||
            ($appointment->studio && $user->canManageStudioAppointments($appointment->studio)) ||
            ($appointment->studio && $user->studios()
                ->where('studios.id', $appointment->studio->id)
                ->wherePivot('is_active', true)
                ->exists()) ||
            ($appointment->pickup_required && $appointment->studio && $this->driverCanAccessStudio($appointment->studio, $request));

        abort_unless($canAccess, 403);

        return $this->appointmentDetailResponse($appointment);
    }

    private function appointmentDetailResponse(Appointment $appointment): JsonResponse
    {
        $appointment->load(['createdBy', 'assignedArtist', 'studio.company']);
        $limitedView = $this->artistLimitedView($appointment);
        $currentUser = request()->user();

        return response()->json([
            'data' => [
                'id'               => $appointment->id,
                'appointment_type' => $appointment->appointment_type,
                'full_name'        => $limitedView ? null : trim($appointment->first_name.' '.$appointment->last_name),
                'customer'         => $limitedView ? [] : $this->formatCustomer($appointment),
                'date'             => optional($appointment->appointment_at)->format('Y-m-d'),
                'time'             => optional($appointment->appointment_at)->format('H:i'),
                'place'            => $limitedView ? null : $appointment->place,
                'pax'              => $limitedView ? null : $appointment->pax,
                'price'            => $this->visiblePriceFor($appointment),
                'status'           => $appointment->status,
                'driver_status'    => $limitedView ? null : $appointment->driver_status,
                'artist_status'    => $appointment->artist_status,
                'can_artist_respond' => $currentUser instanceof User
                    && (int) $appointment->assigned_artist_user_id === (int) $currentUser->id
                    && $appointment->appointment_type === 'tattoo'
                    && ! in_array($appointment->status, ['cancelled', 'completed'], true),
                'notes'            => $limitedView ? null : $appointment->notes,
                'source_image_path' => $limitedView ? null : $this->imageUrl($appointment->source_image_path),
                'photo_path'        => $limitedView ? null : $this->imageUrl($appointment->photo_path),
                'tattoo_image_paths' => $this->imageUrls($appointment->tattoo_image_paths),
                'completed_tattoo_image_path' => $this->imageUrl($appointment->completed_tattoo_image_path),
                'pickup_required'   => $limitedView ? null : (bool) $appointment->pickup_required,
                'is_old_customer'  => $limitedView ? null : $appointment->is_old_customer,
                'artist_limited_view' => $limitedView,
                'studio'           => $appointment->studio ? [
                    'id'   => $appointment->studio->id,
                    'name' => $appointment->studio->name,
                    'company' => $appointment->studio->company ? [
                        'id'   => $appointment->studio->company->id,
                        'name' => $appointment->studio->company->name,
                    ] : null,
                ] : null,
                'artist'           => $appointment->assignedArtist ? [
                    'id'            => $appointment->assignedArtist->id,
                    'name'          => $appointment->assignedArtist->fullName(),
                    'profile_image' => $appointment->assignedArtist->profile_image,
                    'rating'        => $appointment->assignedArtist->rating,
                ] : null,
                'created_by' => ! $limitedView && $appointment->createdBy ? [
                    'id'   => $appointment->createdBy->id,
                    'name' => $appointment->createdBy->fullName(),
                ] : null,
                'created_at' => optional($appointment->created_at)->toIso8601String(),
            ],
        ]);
    }

    public function checkCustomerStatus(
        Request $request,
        Studio $studio,
        AppointmentService $appointmentService
    ): JsonResponse {
        $validated = $request->validate([
            'customer.first_name'         => ['nullable', 'string', 'max:255'],
            'customer.last_name'          => ['nullable', 'string', 'max:255'],
            'customer.phone_country_code' => ['nullable', 'string', 'max:10'],
            'customer.phone_number'       => ['nullable', 'string', 'max:30'],
            'customer.hotel_name'         => ['nullable', 'string', 'max:255'],
        ]);

        $status = $appointmentService->checkCustomerStatus($studio, $validated['customer']);
        $matchedAppointment = $status['matched_appointment'];

        return response()->json([
            'data' => [
                'is_old_customer'     => $status['is_old_customer'],
                'last_appointment_id' => $matchedAppointment?->id,
                'customer'            => $matchedAppointment ? $this->formatCustomer($matchedAppointment) : null,
                'previous_appointments' => $status['appointments']->map(fn (Appointment $appointment): array => [
                    'id'               => $appointment->id,
                    'appointment_type' => $appointment->appointment_type,
                    'appointment_at'   => optional($appointment->appointment_at)->toIso8601String(),
                    'status'           => $appointment->status,
                    'hotel_name'       => $appointment->hotel_name,
                    'room_number'      => $appointment->room_number,
                    'place'            => $appointment->place,
                    'pax'              => $appointment->pax,
                    'price'            => $this->visiblePriceFor($appointment),
                    'notes'            => $appointment->notes,
                    'customer_notes'   => $appointment->customer_notes,
                ])->values(),
            ],
        ]);
    }

    /** Stüdyo randevuları — tüm stüdyo çalışanları tüm randevuları görür */
    public function index(Request $request, Studio $studio): JsonResponse
    {
        $filters = $request->validate([
            'appointment_type' => ['nullable', 'string', 'in:designer,tattoo'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $appointments = $studio->appointments()
            ->with(['createdBy', 'assignedArtist'])
            ->when(isset($filters['appointment_type']), fn ($query) => $query
                ->where('appointment_type', $filters['appointment_type']))
            ->when(isset($filters['date_from']), fn ($query) => $query
                ->whereDate('appointment_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query
                ->whereDate('appointment_at', '<=', $filters['date_to']))
            ->orderBy('appointment_at')
            ->get();

        return response()->json([
            'data' => $appointments->map(function ($appointment) use ($studio): array {
                $limitedView = $this->artistLimitedView($appointment);

                return [
                'id'               => $appointment->id,
                'customer'         => $limitedView ? [] : $this->formatCustomer($appointment),
                'pax'              => $limitedView ? null : $appointment->pax,
                'price'            => $this->visiblePriceFor($appointment),
                'appointment_at'   => optional($appointment->appointment_at)->toIso8601String(),
                'appointment_type' => $appointment->appointment_type,
                'status'           => $appointment->status,
                'artist_status'    => $appointment->artist_status,
                'notes'            => $limitedView ? null : $appointment->notes,
                'source_image_path' => $limitedView ? null : $this->imageUrl($appointment->source_image_path),
                'photo_path'        => $limitedView ? null : $this->imageUrl($appointment->photo_path),
                'tattoo_image_paths' => $this->imageUrls($appointment->tattoo_image_paths),
                'completed_tattoo_image_path' => $this->imageUrl($appointment->completed_tattoo_image_path),
                'pickup_required'   => $limitedView ? null : (bool) $appointment->pickup_required,
                'artist_limited_view' => $limitedView,
                'assigned_artist_user_id' => $appointment->assigned_artist_user_id,
                'artist' => $appointment->assignedArtist ? [
                    'id'            => $appointment->assignedArtist->id,
                    'name'          => $appointment->assignedArtist->fullName(),
                    'profile_image' => $appointment->assignedArtist->profile_image,
                    'rating'        => $appointment->assignedArtist->rating,
                ] : null,
                'studio' => [
                    'id'   => $studio->id,
                    'name' => $studio->name,
                ],
                'created_at' => optional($appointment->created_at)->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    /** Şoför atandığı stüdyodaki randevunun durumunu güncelleyebilir */
    public function driverAction(Request $request, Studio $studio, Appointment $appointment): JsonResponse
    {
        abort_if((int) $appointment->studio_id !== (int) $studio->id, 404);
        abort_unless($appointment->pickup_required, 403);

        $user = $request->user();

        abort_unless($user?->hasStudioRole($studio, [UserRole::Sofor]), 403);

        $validated = $request->validate([
            'driver_status' => ['required', 'string', 'in:picked_up,dropped_off,cancelled,customer_no_show'],
        ]);

        $appointment->driver_status = $validated['driver_status'];

        if ($validated['driver_status'] === 'dropped_off') {
            $appointment->status = 'completed';
        } elseif (in_array($validated['driver_status'], ['cancelled', 'customer_no_show'], true)) {
            $appointment->status = 'cancelled';
        }

        $appointment->save();

        $statusLabel = match ($validated['driver_status']) {
            'picked_up' => 'Şoför müşteriyi aldı.',
            'dropped_off' => 'Şoför müşteriyi bıraktı ve randevu tamamlandı.',
            'customer_no_show' => 'Müşteri gelmedi, randevu iptal edildi.',
            default => 'Şoför randevuyu iptal etti.',
        };
        app(AppointmentNotificationService::class)
            ->notifyDriverAction($appointment, $statusLabel, $user);

        return response()->json([
            'message' => 'Durum güncellendi.',
            'data'    => [
                'id'            => $appointment->id,
                'status'        => $appointment->status,
                'driver_status' => $appointment->driver_status,
            ],
        ]);
    }

    /** Supervisor+ tarafından randevuya artist/freelancer atar */
    public function assignArtist(
        Request $request,
        Studio $studio,
        Appointment $appointment,
        AppointmentService $appointmentService
    ): JsonResponse {
        abort_if((int) $appointment->studio_id !== (int) $studio->id, 404);
        abort_unless($request->user()?->canAssignArtist($studio), 403);

        $validated = $request->validate([
            'assigned_artist_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $appointment = $appointmentService->assignArtist(
            $studio,
            $appointment,
            $validated['assigned_artist_user_id'] !== null ? (int) $validated['assigned_artist_user_id'] : null
        );

        $appointment->load('assignedArtist');

        if ($appointment->assignedArtist instanceof User) {
            $recordLabel = $appointment->appointment_type === 'tattoo' ? 'Bilet' : 'Randevu';
            app(FcmService::class)->sendToUser(
                $appointment->assignedArtist,
                "Yeni {$recordLabel} Atandı",
                "{$studio->name} için {$appointment->appointment_at?->format('d.m.Y H:i')} tarihli {$recordLabel} size atandı.",
                'artist_assigned',
                [
                    'appointment_id' => $appointment->id,
                    'studio_id'      => $studio->id,
                ],
            );
        }

        return response()->json([
            'message' => 'Artist atandı.',
            'data'    => [
                'id'                      => $appointment->id,
                'assigned_artist_user_id' => $appointment->assigned_artist_user_id,
                'artist_status'           => $appointment->artist_status,
                'artist'                  => $appointment->assignedArtist ? [
                    'id'            => $appointment->assignedArtist->id,
                    'name'          => $appointment->assignedArtist->fullName(),
                    'profile_image' => $appointment->assignedArtist->profile_image,
                    'rating'        => $appointment->assignedArtist->rating,
                ] : null,
            ],
        ]);
    }

    public function artistResponse(Request $request, Appointment $appointment): JsonResponse
    {
        $artist = $request->user();
        abort_unless($artist instanceof User, 403);
        abort_unless((int) $appointment->assigned_artist_user_id === (int) $artist->id, 403);
        abort_unless($appointment->appointment_type === 'tattoo', 422);
        abort_if(in_array($appointment->status, ['cancelled', 'completed'], true), 422);

        $validated = $request->validate([
            'response' => ['required', 'string', 'in:accepted,rejected'],
        ]);
        abort_if($appointment->artist_status === $validated['response'], 422, 'Bu bilete daha önce yanıt verdiniz.');

        $appointment->forceFill([
            'artist_status' => $validated['response'],
        ])->save();

        app(AppointmentNotificationService::class)
            ->notifyArtistResponse($appointment, $artist);

        return response()->json([
            'message' => $validated['response'] === 'accepted'
                ? 'Bilet kabul edildi.'
                : 'Bilet reddedildi.',
            'data' => [
                'id' => $appointment->id,
                'artist_status' => $appointment->artist_status,
            ],
        ]);
    }

    /** Artist bitmiş dövme fotoğrafını yükleyerek bileti tamamlar. */
    public function artistComplete(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless((int) $appointment->assigned_artist_user_id === (int) $user->id, 403);
        abort_unless($appointment->appointment_type === 'tattoo', 422);
        abort_unless(
            $user->isIndependentProfessionalFor(UserRole::Artist)
                || ($appointment->studio_id !== null && $user->hasStudioRole((int) $appointment->studio_id, [UserRole::Artist])),
            403
        );
        abort_if(in_array($appointment->status, ['cancelled', 'completed'], true), 422);

        $request->validate([
            'completed_tattoo_image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ]);

        $appointment->fill([
            'completed_tattoo_image_path' => $this->storeCompletedTattooImage($request, $appointment),
            'status' => 'completed',
        ])->save();

        app(AppointmentNotificationService::class)
            ->notifyAppointmentUpdated($appointment, 'completed', $user);

        return response()->json([
            'message' => 'Dövme fotoğrafı yüklendi ve bilet tamamlandı.',
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'price' => $this->visiblePriceFor($appointment),
                'completed_tattoo_image_path' => $this->imageUrl($appointment->completed_tattoo_image_path),
            ],
        ]);
    }

    public function store(
        Request $request,
        Studio $studio,
        AppointmentService $appointmentService,
        AppointmentNotificationService $appointmentNotificationService
    ): JsonResponse
    {
        $validated = $request->validate([
            'slip_image_path'             => ['nullable', 'string', 'max:2048'],
            'customer.first_name'         => ['required', 'string', 'max:255'],
            'customer.last_name'          => ['required', 'string', 'max:255'],
            'customer.phone_country_code' => ['nullable', 'string', 'max:10'],
            'customer.phone_number'       => ['nullable', 'string', 'max:30'],
            'customer.hotel_name'         => ['nullable', 'string', 'max:255'],
            'customer.room_number'        => ['nullable', 'string', 'max:100'],
            'customer.customer_notes'     => ['nullable', 'string'],
            'pax'                         => ['required', 'integer', 'min:1', 'max:50'],
            'price'                       => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'appointment_at'              => ['required', 'date'],
            'appointment_type'            => ['nullable', 'string', 'in:designer,tattoo'],
            'notes'                       => ['nullable', 'string'],
            'source_image_path'           => ['nullable', 'string', 'max:2048'],
            'image'                       => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'tattoo_image_paths'           => ['nullable', 'array', 'max:3'],
            'tattoo_image_paths.*'         => ['string', 'max:2048'],
            'tattoo_images'                => ['nullable', 'array', 'max:3'],
            'tattoo_images.*'              => ['image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'pickup_required'              => ['sometimes', 'boolean'],
        ]);
        $validated = $this->withoutUnauthorizedPrice($validated, $request->user());

        $sourceImagePath = $validated['source_image_path'] ?? $validated['slip_image_path'] ?? null;
        if ($request->hasFile('image')) {
            $sourceImagePath = $this->storeAppointmentImage($request, $studio);
        }
        $tattooImagePaths = $this->resolveTattooImagePaths($request, $studio, $validated['tattoo_image_paths'] ?? []);

        $appointment = $appointmentService->create($studio, $request->user(), [
            'customer' => [
                'first_name'         => $validated['customer']['first_name'],
                'last_name'          => $validated['customer']['last_name'],
                'phone_country_code' => $validated['customer']['phone_country_code'] ?? null,
                'phone_number'       => $validated['customer']['phone_number'] ?? null,
                'hotel_name'         => $validated['customer']['hotel_name'] ?? $studio->name,
                'room_number'        => $validated['customer']['room_number'] ?? null,
                'place'              => $validated['customer']['hotel_name'] ?? null,
                'photo_path'         => $validated['slip_image_path'] ?? null,
                'customer_notes'     => $validated['customer']['customer_notes'] ?? null,
            ],
            'appointment_type'  => $validated['appointment_type'] ?? 'designer',
            'pax'               => $validated['pax'],
            'price'             => $validated['price'] ?? null,
            'appointment_at'    => $validated['appointment_at'],
            'notes'             => $validated['notes'] ?? null,
            'source_image_path' => $sourceImagePath,
            'tattoo_image_paths' => $tattooImagePaths,
            'pickup_required'   => $validated['pickup_required'] ?? false,
        ]);

        $appointmentNotificationService->notifyStudioAppointmentCreated($appointment, $request->user());
        $recordLabel = $appointment->appointment_type === 'tattoo' ? 'Bilet' : 'Randevu';

        return response()->json([
            'message' => "{$recordLabel} oluşturuldu.",
            'data'    => ['id' => $appointment->id, 'status' => $appointment->status],
        ], 201);
    }

    public function update(
        Request $request,
        Studio $studio,
        Appointment $appointment,
        AppointmentService $appointmentService,
        AppointmentNotificationService $appointmentNotificationService
    ): JsonResponse {
        $previousStatus = $appointment->status;

        $validated = $request->validate([
            'customer.first_name'         => ['sometimes', 'string', 'max:255'],
            'customer.last_name'          => ['sometimes', 'string', 'max:255'],
            'customer.phone_country_code' => ['nullable', 'string', 'max:10'],
            'customer.phone_number'       => ['nullable', 'string', 'max:30'],
            'customer.hotel_name'         => ['nullable', 'string', 'max:255'],
            'customer.room_number'        => ['nullable', 'string', 'max:100'],
            'customer.customer_notes'     => ['nullable', 'string'],
            'pax'                         => ['sometimes', 'integer', 'min:1', 'max:50'],
            'price'                       => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'appointment_at'              => ['sometimes', 'date'],
            'appointment_type'            => ['sometimes', 'string', 'in:designer,tattoo'],
            'status'                      => ['sometimes', 'string', 'in:confirmed,in_progress,completed,cancelled,rescheduled'],
            'notes'                       => ['nullable', 'string'],
            'source_image_path'           => ['nullable', 'string', 'max:2048'],
            'image'                       => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'tattoo_image_paths'           => ['nullable', 'array', 'max:3'],
            'tattoo_image_paths.*'         => ['string', 'max:2048'],
            'tattoo_images'                => ['nullable', 'array', 'max:3'],
            'tattoo_images.*'              => ['image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'pickup_required'              => ['sometimes', 'boolean'],
        ]);
        $validated = $this->withoutUnauthorizedPrice($validated, $request->user());

        if ($request->hasFile('image')) {
            $validated['source_image_path'] = $this->storeAppointmentImage($request, $studio);
        }
        if ($request->hasFile('tattoo_images') || array_key_exists('tattoo_image_paths', $validated)) {
            $validated['tattoo_image_paths'] = $this->resolveTattooImagePaths(
                $request,
                $studio,
                $validated['tattoo_image_paths'] ?? []
            );
        }

        $appointment = $appointmentService->update($studio, $appointment, $validated);
        $event = match (true) {
            $previousStatus !== $appointment->status && $appointment->status === 'cancelled' => 'cancelled',
            $previousStatus !== $appointment->status && $appointment->status === 'in_progress' => 'started',
            $previousStatus !== $appointment->status && $appointment->status === 'completed' => 'completed',
            default => 'updated',
        };
        $appointmentNotificationService->notifyAppointmentUpdated(
            $appointment,
            $event,
            $request->user(),
        );
        $recordLabel = $appointment->appointment_type === 'tattoo' ? 'Bilet' : 'Randevu';

        return response()->json([
            'message' => "{$recordLabel} güncellendi.",
            'data'    => ['id' => $appointment->id, 'status' => $appointment->status],
        ]);
    }

    public function destroy(
        Studio $studio,
        Appointment $appointment,
        AppointmentService $appointmentService
    ): JsonResponse {
        $appointment = $studio->appointments()
            ->whereKey($appointment->id)
            ->firstOrFail();

        $appointmentService->delete($studio, $appointment);
        $recordLabel = $appointment->appointment_type === 'tattoo' ? 'Bilet' : 'Randevu';

        return response()->json(['message' => "{$recordLabel} silindi."]);
    }

    private function formatCustomer(Appointment $appointment): array
    {
        return [
            'first_name'         => $appointment->first_name,
            'last_name'          => $appointment->last_name,
            'phone_country_code' => $appointment->phone_country_code,
            'phone_number'       => $appointment->phone_number,
            'hotel_name'         => $appointment->hotel_name,
            'room_number'        => $appointment->room_number,
            'photo_path'         => $this->imageUrl($appointment->photo_path),
            'customer_notes'     => $appointment->customer_notes,
        ];
    }

    private function driverCanAccessAppointment(Studio $routeStudio, Appointment $appointment, Request $request): bool
    {
        $user = $request->user();
        if (! $user?->hasRole(UserRole::Sofor)) {
            return false;
        }
        if (! $appointment->pickup_required) {
            return false;
        }

        return (int) $routeStudio->id === (int) $appointment->studio_id
            && $user->hasStudioRole($routeStudio, [UserRole::Sofor]);
    }

    private function driverCanAccessStudio(Studio $studio, Request $request): bool
    {
        $user = $request->user();
        return $user?->hasStudioRole($studio, [UserRole::Sofor]) ?? false;
    }

    private function imageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return url($path);
        }

        if (str_starts_with($path, 'storage/')) {
            return url($path);
        }

        return url('storage/' . $path);
    }

    /**
     * @param  array<int, string>|null  $paths
     * @return array<int, string>
     */
    private function imageUrls(?array $paths): array
    {
        return collect($paths ?? [])
            ->map(fn (string $path): ?string => $this->imageUrl($path))
            ->filter()
            ->values()
            ->all();
    }

    private function visiblePriceFor(Appointment $appointment): mixed
    {
        $user = request()->user();
        $canViewSalePrice = $appointment->studio_id !== null
            && $user?->hasStudioRole((int) $appointment->studio_id, [
                UserRole::Info,
                UserRole::Designer,
            ]);

        return $appointment->status === 'completed'
            || $this->canManagePrice($user)
            || $canViewSalePrice
            ? $appointment->price
            : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function withoutUnauthorizedPrice(array $validated, ?User $user): array
    {
        if (! $this->canManagePrice($user)) {
            unset($validated['price']);
        }

        return $validated;
    }

    private function canManagePrice(?User $user): bool
    {
        return $user?->hasAnyRole([
            UserRole::Admin,
            UserRole::Yonetici,
            UserRole::Supervisor,
        ]) === true;
    }

    private function artistLimitedView(Appointment $appointment): bool
    {
        $user = request()->user();

        return $user instanceof User
            && $user->hasAnyRole([UserRole::Artist, UserRole::KullaniciRol])
            && ! $user->isIndependentProfessional()
            && $appointment->appointment_type === 'tattoo';
    }

    /**
     * @return array<int, int>
     */
    private function activeStudioIdsForRole(User $user, UserRole $role): array
    {
        return $user->studios()
            ->wherePivot('is_active', true)
            ->wherePivot('role', $role->value)
            ->pluck('studios.id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function storeAppointmentImage(Request $request, Studio $studio): string
    {
        $file = $request->file('image');
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('appointments/' . $studio->id, $name, 'public');

        return Storage::disk('public')->url($path);
    }

    private function storeCompletedTattooImage(Request $request, Appointment $appointment): string
    {
        $file = $request->file('completed_tattoo_image');
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $folder = $appointment->studio_id ?? 'freelancers';
        $path = $file->storeAs('appointments/' . $folder . '/completed-tattoos', $name, 'public');

        return Storage::disk('public')->url($path);
    }

    /**
     * @param  array<int, string>  $existingPaths
     * @return array<int, string>
     */
    private function resolveTattooImagePaths(Request $request, Studio $studio, array $existingPaths): array
    {
        $paths = array_values($existingPaths);

        foreach ($request->file('tattoo_images', []) as $file) {
            $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('appointments/' . $studio->id . '/tattoo-images', $name, 'public');
            $paths[] = Storage::disk('public')->url($path);
        }

        if (count($paths) > 3) {
            throw ValidationException::withMessages([
                'tattoo_images' => ['En fazla 3 dövme görseli ekleyebilirsiniz.'],
            ]);
        }

        return $paths;
    }
}
