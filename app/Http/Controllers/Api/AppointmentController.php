<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Studio;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    // Randevu türü sabit listesi
    public const APPOINTMENT_TYPES = ['designer', 'tattoo'];

    /** Şoförün bağlı olduğu şubedeki TÜM randevuları döndürür */
    public function myAppointments(Request $request): JsonResponse
    {
        $user = $request->user();

        // Şoförün bulunduğu stüdyolar → şubeler → o şubelerdeki tüm stüdyolar
        $myStudioIds  = $user->studios()->pluck('studios.id');
        $shopIds      = Studio::query()->whereIn('id', $myStudioIds)->pluck('shop_id')->filter();
        $branchStudioIds = Studio::query()->whereIn('shop_id', $shopIds)->pluck('id');

        $appointments = Appointment::query()
            ->with(['studio', 'createdBy'])
            ->whereIn('studio_id', $branchStudioIds)
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
                'price'             => $appointment->price,
                'appointment_at'    => optional($appointment->appointment_at)->toIso8601String(),
                'appointment_type'  => $appointment->appointment_type,
                'status'            => $appointment->status,
                'driver_status'     => $appointment->driver_status,
                'source_image_path' => $appointment->source_image_path,
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

        $isIndependentArtist = $user->hasRole(UserRole::KullaniciRol);

        if ($artistStudioIds === [] && $designerStudioIds === [] && ! $isIndependentArtist) {
            return response()->json(['data' => []]);
        }

        $appointments = Appointment::query()
            ->with(['studio', 'createdBy'])
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
            ->where(function ($query) use ($user): void {
                $query
                    ->whereNull('assigned_artist_user_id')
                    ->orWhere('assigned_artist_user_id', $user->id);
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('artist_status')
                    ->orWhere('artist_status', '!=', 'rejected');
            })
            ->orderBy('appointment_at')
            ->get();

        return response()->json([
            'data' => $appointments->map(fn ($appointment): array => [
                'id'     => $appointment->id,
                'studio' => $appointment->studio ? [
                    'id'   => $appointment->studio->id,
                    'name' => $appointment->studio->name,
                ] : null,
                'customer'         => $this->formatCustomer($appointment),
                'place'            => $appointment->place,
                'pax'              => $appointment->pax,
                'price'            => $appointment->price,
                'appointment_at'   => optional($appointment->appointment_at)->toIso8601String(),
                'appointment_type' => $appointment->appointment_type,
                'status'           => $appointment->status,
                'artist_status'    => $appointment->artist_status,
                'notes'            => $appointment->notes,
                'created_at'       => optional($appointment->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function support(Studio $studio, Request $request): JsonResponse
    {
        abort_unless($request->user()?->canManageStudioAppointments($studio), 403);

        $artists = $studio->users()
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
                'statuses' => ['confirmed', 'completed', 'cancelled', 'rescheduled'],
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
            ($appointment->studio && $user->canManageStudioAppointments($appointment->studio));

        abort_unless($canAccess, 403);

        return $this->appointmentDetailResponse($appointment);
    }

    private function appointmentDetailResponse(Appointment $appointment): JsonResponse
    {
        $appointment->load(['createdBy', 'assignedArtist', 'studio']);

        return response()->json([
            'data' => [
                'id'               => $appointment->id,
                'appointment_type' => $appointment->appointment_type,
                'full_name'        => trim($appointment->first_name.' '.$appointment->last_name),
                'customer'         => $this->formatCustomer($appointment),
                'date'             => optional($appointment->appointment_at)->format('Y-m-d'),
                'time'             => optional($appointment->appointment_at)->format('H:i'),
                'place'            => $appointment->place,
                'pax'              => $appointment->pax,
                'price'            => $appointment->price,
                'status'           => $appointment->status,
                'driver_status'    => $appointment->driver_status,
                'artist_status'    => $appointment->artist_status,
                'notes'            => $appointment->notes,
                'source_image_path' => $this->imageUrl($appointment->source_image_path),
                'photo_path'        => $this->imageUrl($appointment->photo_path),
                'is_old_customer'  => $appointment->is_old_customer,
                'studio'           => $appointment->studio ? [
                    'id'   => $appointment->studio->id,
                    'name' => $appointment->studio->name,
                ] : null,
                'artist'           => $appointment->assignedArtist ? [
                    'id'            => $appointment->assignedArtist->id,
                    'name'          => $appointment->assignedArtist->fullName(),
                    'profile_image' => $appointment->assignedArtist->profile_image,
                    'rating'        => $appointment->assignedArtist->rating,
                ] : null,
                'created_by' => $appointment->createdBy ? [
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
            'customer.first_name'         => ['required', 'string', 'max:255'],
            'customer.last_name'          => ['required', 'string', 'max:255'],
            'customer.phone_country_code' => ['nullable', 'string', 'max:10'],
            'customer.phone_number'       => ['nullable', 'string', 'max:30'],
            'customer.hotel_name'         => ['nullable', 'string', 'max:255'],
        ]);

        $status = $appointmentService->checkCustomerStatus($studio, $validated['customer']);

        return response()->json([
            'data' => [
                'is_old_customer'     => $status['is_old_customer'],
                'last_appointment_id' => $status['matched_appointment']?->id,
                'customer_notes'      => $status['matched_appointment']?->customer_notes,
            ],
        ]);
    }

    /** Stüdyo randevuları — tüm stüdyo çalışanları tüm randevuları görür */
    public function index(Request $request, Studio $studio): JsonResponse
    {
        $appointments = $studio->appointments()
            ->with(['createdBy', 'assignedArtist'])
            ->orderBy('appointment_at')
            ->get();

        return response()->json([
            'data' => $appointments->map(fn ($appointment): array => [
                'id'               => $appointment->id,
                'customer'         => $this->formatCustomer($appointment),
                'pax'              => $appointment->pax,
                'price'            => $appointment->price,
                'appointment_at'   => optional($appointment->appointment_at)->toIso8601String(),
                'appointment_type' => $appointment->appointment_type,
                'status'           => $appointment->status,
                'artist_status'    => $appointment->artist_status,
                'notes'            => $appointment->notes,
                'source_image_path' => $appointment->source_image_path,
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
            ])->values(),
        ]);
    }

    /** Şoför şubedeki herhangi bir randevunun durumunu güncelleyebilir */
    public function driverAction(Request $request, Studio $studio, Appointment $appointment): JsonResponse
    {
        abort_if((int) $appointment->studio_id !== (int) $studio->id, 404);

        $user = $request->user();

        // Şoförün bu stüdyonun şubesine ait bir stüdyoda şoför olup olmadığını kontrol et
        $myStudioIds = $user->studios()->pluck('studios.id');
        $shopIds     = Studio::query()->whereIn('id', $myStudioIds)->pluck('shop_id')->filter();
        $inBranch    = Studio::query()
            ->where('id', $studio->id)
            ->whereIn('shop_id', $shopIds)
            ->exists();

        abort_unless($inBranch, 403);

        $validated = $request->validate([
            'driver_status' => ['required', 'string', 'in:picked_up,dropped_off,cancelled,customer_no_show'],
        ]);

        $appointment->driver_status = $validated['driver_status'];

        if (in_array($validated['driver_status'], ['picked_up', 'dropped_off'], true)) {
            $appointment->status = 'completed';
        } elseif (in_array($validated['driver_status'], ['cancelled', 'customer_no_show'], true)) {
            $appointment->status = 'cancelled';
        }

        $appointment->save();

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

    /** Artist randevuyu kabul veya reddeder */
    public function artistResponse(
        Request $request,
        Studio $studio,
        Appointment $appointment,
        AppointmentService $appointmentService
    ): JsonResponse {
        abort_if((int) $appointment->studio_id !== (int) $studio->id, 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if ($appointment->assigned_artist_user_id === null) {
            abort_unless($this->userCanRespondToArtistAppointment($user, $studio, $appointment), 403);
            $appointment->assigned_artist_user_id = $user->id;
            $appointment->artist_status = 'pending';
            $appointment->save();
        } else {
            abort_unless((int) $appointment->assigned_artist_user_id === (int) $user->id, 403);
        }

        $validated = $request->validate([
            'artist_status' => ['required', 'string', 'in:accepted,rejected'],
        ]);

        $appointment = $appointmentService->artistRespond($appointment, $validated['artist_status']);

        return response()->json([
            'message' => $validated['artist_status'] === 'accepted' ? 'Randevu kabul edildi.' : 'Randevu reddedildi.',
            'data'    => [
                'id'            => $appointment->id,
                'artist_status' => $appointment->artist_status,
            ],
        ]);
    }

    public function store(Request $request, Studio $studio, AppointmentService $appointmentService): JsonResponse
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
            'price'                       => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'appointment_at'              => ['required', 'date'],
            'appointment_type'            => ['nullable', 'string', 'in:designer,tattoo'],
            'notes'                       => ['nullable', 'string'],
            'source_image_path'           => ['nullable', 'string', 'max:2048'],
            'image'                       => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ]);

        $sourceImagePath = $validated['source_image_path'] ?? $validated['slip_image_path'] ?? null;
        if ($request->hasFile('image')) {
            $sourceImagePath = $this->storeAppointmentImage($request, $studio);
        }

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
            'appointment_type'  => $validated['appointment_type'] ?? 'tattoo',
            'pax'               => $validated['pax'],
            'price'             => $validated['price'],
            'appointment_at'    => $validated['appointment_at'],
            'notes'             => $validated['notes'] ?? null,
            'source_image_path' => $sourceImagePath,
        ]);

        return response()->json([
            'message' => 'Randevu oluşturuldu.',
            'data'    => ['id' => $appointment->id, 'status' => $appointment->status],
        ], 201);
    }

    public function update(
        Request $request,
        Studio $studio,
        Appointment $appointment,
        AppointmentService $appointmentService
    ): JsonResponse {
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
            'status'                      => ['sometimes', 'string', 'in:confirmed,completed,cancelled,rescheduled'],
            'notes'                       => ['nullable', 'string'],
            'source_image_path'           => ['nullable', 'string', 'max:2048'],
        ]);

        $appointment = $appointmentService->update($studio, $appointment, $validated);

        return response()->json([
            'message' => 'Randevu güncellendi.',
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

        return response()->json(['message' => 'Randevu silindi.']);
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
            'customer_notes'     => $appointment->customer_notes,
        ];
    }

    private function driverCanAccessAppointment(Studio $routeStudio, Appointment $appointment, Request $request): bool
    {
        $user = $request->user();
        if (! $user?->hasRole(UserRole::Sofor)) {
            return false;
        }

        $myStudioIds = $user->studios()->pluck('studios.id');
        $shopIds = Studio::query()->whereIn('id', $myStudioIds)->pluck('shop_id')->filter();

        return Studio::query()
            ->whereIn('id', [$routeStudio->id, $appointment->studio_id])
            ->whereIn('shop_id', $shopIds)
            ->count() === 2;
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

    private function userCanRespondToArtistAppointment(User $user, Studio $studio, Appointment $appointment): bool
    {
        return match ($appointment->appointment_type) {
            'designer' => $user->hasStudioRole($studio, [UserRole::Designer]),
            'tattoo' => $user->hasStudioRole($studio, [UserRole::Artist]),
            default => false,
        };
    }

    private function storeAppointmentImage(Request $request, Studio $studio): string
    {
        $file = $request->file('image');
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('appointments/' . $studio->id, $name, 'public');

        return Storage::disk('public')->url($path);
    }
}
