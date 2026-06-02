<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Studio;
use App\Models\User;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $direction = $request->query('direction', 'incoming');
        $query = AppointmentRequest::query()
            ->with(['requester', 'target', 'studio', 'appointment'])
            ->latest();

        if ($direction === 'outgoing') {
            $query->where('requester_user_id', $user->id);
        } else {
            $managedStudioIds = $user->studios()
                ->wherePivot('is_active', true)
                ->wherePivotIn('role', [
                    UserRole::Supervisor->value,
                    UserRole::Yonetici->value,
                    UserRole::Info->value,
                ])
                ->pluck('studios.id');

            $query->where(function ($q) use ($user, $managedStudioIds): void {
                $q->where('target_user_id', $user->id);

                if ($managedStudioIds->isNotEmpty()) {
                    $q->orWhereIn('studio_id', $managedStudioIds);
                }
            });
        }

        return response()->json([
            'data' => $query->get()->map(fn (AppointmentRequest $appointmentRequest): array => $this->formatRequest($appointmentRequest))->values(),
        ]);
    }

    public function show(Request $request, AppointmentRequest $appointmentRequest): JsonResponse
    {
        abort_unless($this->canView($request->user(), $appointmentRequest), 403);

        return response()->json([
            'data' => $this->formatRequest($appointmentRequest->load(['requester', 'target', 'studio', 'appointment'])),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();
        abort_unless($authUser instanceof User, 401);

        $validated = $request->validate([
            'studio_id'          => ['required_without:artist_id', 'nullable', 'integer', 'exists:studios,id'],
            'artist_id'          => ['required_without:studio_id', 'nullable', 'integer', 'exists:users,id'],
            'requested_at'       => ['required_without:preferred_date', 'nullable', 'date', 'after:now'],
            'preferred_date'     => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'preferred_time'     => ['nullable', 'date_format:H:i'],
            'type'               => ['nullable', 'string', 'in:designer,tattoo'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'first_name'         => ['required', 'string', 'max:255'],
            'last_name'          => ['required', 'string', 'max:255'],
            'phone_country_code' => ['required', 'string', 'max:10'],
            'phone_number'       => ['required', 'string', 'max:30'],
            'hotel_name'         => ['required', 'string', 'max:255'],
            'room_number'        => ['required', 'string', 'max:100'],
            'place'              => ['required', 'string', 'max:255'],
            'pax'                => ['required', 'integer', 'min:1', 'max:50'],
            'price'              => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'image'              => ['required_without:image_path', 'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'image_path'         => ['required_without:image', 'nullable', 'string', 'max:2048'],
            'tattoo_image_paths' => ['nullable', 'array', 'max:3'],
            'tattoo_image_paths.*' => ['string', 'max:2048'],
            'tattoo_images'      => ['nullable', 'array', 'max:3'],
            'tattoo_images.*'    => ['image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'pickup_required'    => ['sometimes', 'boolean'],
        ]);
        $validated = $this->withoutUnauthorizedPrice($validated, $authUser);

        $target = ! empty($validated['artist_id'])
            ? User::query()->findOrFail($validated['artist_id'])
            : null;
        abort_if($target?->banned_at !== null, 422, 'Banlı kullanıcıya talep gönderilemez.');
        $studio = $this->resolveStudio($target, $validated['studio_id'] ?? null);
        $type = $this->resolveRequestType($target, $studio, $validated['type'] ?? null);

        abort_unless(
            $this->canSendRequestToTarget($authUser, $target, $studio, $type),
            403,
            'Bu talebi gönderme yetkiniz yok.'
        );

        $requestedAt = $this->resolveRequestedAt($validated);
        $imagePath = $validated['image_path'] ?? null;
        if ($request->hasFile('image')) {
            $imagePath = $this->storeRequestImage($request, $target, $studio);
        }
        $tattooImagePaths = $this->resolveTattooImagePaths(
            $request,
            $target,
            $studio,
            $validated['tattoo_image_paths'] ?? []
        );

        $appointmentRequest = AppointmentRequest::query()->create([
            'requester_user_id'  => $authUser->id,
            'target_user_id'     => $target?->id,
            'studio_id'          => $studio?->id,
            'request_type'       => $type,
            'requested_at'       => $requestedAt,
            'image_path'         => $imagePath,
            'tattoo_image_paths' => $tattooImagePaths,
            'pickup_required'    => $validated['pickup_required'] ?? false,
            'notes'              => $validated['notes'] ?? null,
            'first_name'         => $validated['first_name'] ?? ($authUser->name ?: null),
            'last_name'          => $validated['last_name'] ?? ($authUser->surname ?: null),
            'phone_country_code' => $validated['phone_country_code'],
            'phone_number'       => $validated['phone_number'],
            'hotel_name'         => $validated['hotel_name'] ?? null,
            'room_number'        => $validated['room_number'] ?? null,
            'place'              => $validated['place'] ?? $validated['hotel_name'] ?? null,
            'pax'                => $validated['pax'] ?? 1,
            'price'              => $validated['price'] ?? null,
            'status'             => 'pending',
        ]);

        if ($target !== null) {
            $this->notifyTarget($target, $authUser, $appointmentRequest);
        } elseif ($studio !== null) {
            $this->notifyStudio($studio, $authUser, $appointmentRequest);
        }

        return response()->json([
            'message' => 'Talebiniz gönderildi.',
            'data'    => $this->formatRequest($appointmentRequest->load(['requester', 'target', 'studio'])),
        ], 201);
    }

    public function accept(Request $request, AppointmentRequest $appointmentRequest): JsonResponse
    {
        abort_unless($this->canRespond($request->user(), $appointmentRequest), 403);
        abort_unless($appointmentRequest->status === 'pending', 422, 'Bu talep artık beklemede değil.');

        $validated = $request->validate([
            'requested_at'   => ['nullable', 'date'],
            'preferred_date' => ['nullable', 'date_format:Y-m-d'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'notes'          => ['nullable', 'string', 'max:2000'],
            'first_name'     => ['nullable', 'string', 'max:255'],
            'last_name'      => ['nullable', 'string', 'max:255'],
            'hotel_name'     => ['nullable', 'string', 'max:255'],
            'room_number'    => ['nullable', 'string', 'max:100'],
            'place'          => ['nullable', 'string', 'max:255'],
            'pax'            => ['nullable', 'integer', 'min:1', 'max:50'],
            'price'          => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);
        $validated = $this->withoutUnauthorizedPrice($validated, $request->user());

        $appointment = DB::transaction(function () use ($appointmentRequest, $validated): Appointment {
            $requestedAt = $this->resolveRequestedAt($validated, $appointmentRequest->requested_at);
            $target = $appointmentRequest->target;
            $isIndependentProfessional = $target?->isIndependentProfessional() === true;
            $studio = $isIndependentProfessional
                ? null
                : ($appointmentRequest->studio ?? $target?->studios()->wherePivot('is_active', true)->first());

            if (! $isIndependentProfessional && $studio === null) {
                throw ValidationException::withMessages([
                    'studio_id' => ['Talebi randevuya çevirmek için stüdyo bilgisi gerekli.'],
                ]);
            }

            $appointment = Appointment::query()->create([
                'studio_id'               => $studio?->id,
                'created_by_user_id'      => $appointmentRequest->requester_user_id,
                'assigned_artist_user_id' => $appointmentRequest->target_user_id,
                'appointment_type'        => $appointmentRequest->request_type,
                'first_name'              => $validated['first_name'] ?? $appointmentRequest->first_name ?? '-',
                'last_name'               => $validated['last_name'] ?? $appointmentRequest->last_name ?? '-',
                'phone_country_code'      => $appointmentRequest->phone_country_code,
                'phone_number'            => $appointmentRequest->phone_number,
                'hotel_name'              => $validated['hotel_name'] ?? $appointmentRequest->hotel_name,
                'room_number'             => $validated['room_number'] ?? $appointmentRequest->room_number,
                'place'                   => $validated['place'] ?? $appointmentRequest->place ?? $appointmentRequest->hotel_name,
                'appointment_at'          => $requestedAt,
                'status'                  => 'confirmed',
                'artist_status'           => null,
                'customer_notes'          => $validated['notes'] ?? $appointmentRequest->notes,
                'notes'                   => null,
                'photo_path'              => $appointmentRequest->image_path,
                'tattoo_image_paths'      => $appointmentRequest->tattoo_image_paths ?? [],
                'pickup_required'         => $appointmentRequest->pickup_required,
                'is_old_customer'         => false,
                'pax'                     => $validated['pax'] ?? $appointmentRequest->pax ?? 1,
                'price'                   => $validated['price'] ?? $appointmentRequest->price,
            ]);

            $appointmentRequest->fill([
                'studio_id'       => $studio?->id,
                'appointment_id'  => $appointment->id,
                'requested_at'    => $requestedAt,
                'notes'           => $validated['notes'] ?? $appointmentRequest->notes,
                'first_name'      => $validated['first_name'] ?? $appointmentRequest->first_name,
                'last_name'       => $validated['last_name'] ?? $appointmentRequest->last_name,
                'hotel_name'      => $validated['hotel_name'] ?? $appointmentRequest->hotel_name,
                'room_number'     => $validated['room_number'] ?? $appointmentRequest->room_number,
                'place'           => $validated['place'] ?? $appointmentRequest->place,
                'pax'             => $validated['pax'] ?? $appointmentRequest->pax,
                'price'           => $validated['price'] ?? $appointmentRequest->price,
                'status'          => 'accepted',
                'responded_at'    => now(),
            ])->save();

            return $appointment;
        });

        $requester = $appointmentRequest->requester;
        if ($requester instanceof User) {
            app(FcmService::class)->sendToUser(
                $requester,
                'Talep Kabul Edildi',
                "{$appointmentRequest->requested_at?->format('d.m.Y H:i')} tarihli talebiniz randevuya dönüştürüldü.",
                'appointment_request_accepted',
                [
                    'appointment_request_id' => $appointmentRequest->id,
                    'appointment_id'         => $appointment->id,
                    'studio_id'              => $appointment->studio_id,
                ],
            );
        }

        return response()->json([
            'message' => 'Talep kabul edildi ve randevu oluşturuldu.',
            'data'    => [
                'request'     => $this->formatRequest($appointmentRequest->fresh(['requester', 'target', 'studio', 'appointment'])),
                'appointment' => ['id' => $appointment->id, 'studio_id' => $appointment->studio_id],
            ],
        ]);
    }

    public function reject(Request $request, AppointmentRequest $appointmentRequest): JsonResponse
    {
        abort_unless($this->canRespond($request->user(), $appointmentRequest), 403);
        abort_unless($appointmentRequest->status === 'pending', 422, 'Bu talep artık beklemede değil.');

        $validated = $request->validate([
            'response_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $appointmentRequest->fill([
            'status'         => 'rejected',
            'response_notes' => $validated['response_notes'] ?? null,
            'responded_at'   => now(),
        ])->save();

        $requester = $appointmentRequest->requester;
        if ($requester instanceof User) {
            app(FcmService::class)->sendToUser(
                $requester,
                'Talep Reddedildi',
                "{$appointmentRequest->requested_at?->format('d.m.Y H:i')} tarihli talebiniz reddedildi.",
                'appointment_request_rejected',
                [
                    'appointment_request_id' => $appointmentRequest->id,
                    'studio_id'              => $appointmentRequest->studio_id,
                    'target_user_id'         => $appointmentRequest->target_user_id,
                ],
            );
        }

        return response()->json([
            'message' => 'Talep reddedildi.',
            'data'    => $this->formatRequest($appointmentRequest->fresh(['requester', 'target', 'studio'])),
        ]);
    }

    private function resolveStudio(?User $target, ?int $studioId): ?Studio
    {
        if ($studioId !== null) {
            return Studio::query()->findOrFail($studioId);
        }

        return $target?->studios()->wherePivot('is_active', true)->first();
    }

    private function resolveRequestType(?User $target, ?Studio $studio, ?string $type): string
    {
        if ($type !== null) {
            return $type;
        }

        if ($target && ($target->hasRole(UserRole::Designer) || ($studio && $target->hasStudioRole($studio, [UserRole::Designer])))) {
            return 'designer';
        }

        return 'tattoo';
    }

    private function resolveRequestedAt(array $validated, ?Carbon $fallback = null): Carbon
    {
        if (! empty($validated['requested_at'])) {
            return Carbon::parse($validated['requested_at']);
        }

        if (! empty($validated['preferred_date'])) {
            return Carbon::createFromFormat(
                'Y-m-d H:i',
                $validated['preferred_date'] . ' ' . ($validated['preferred_time'] ?? '09:00')
            );
        }

        return $fallback ?? now()->addDay()->setTime(9, 0);
    }

    private function canSendRequestToTarget(User $sender, ?User $target, ?Studio $studio, string $type): bool
    {
        if ($target === null) {
            return $studio !== null;
        }

        $independentRole = $type === 'designer' ? UserRole::Designer : UserRole::Artist;
        $isIndependentProfessional = $target->isIndependentProfessionalFor($independentRole);
        $isDesigner = $target->hasRole(UserRole::Designer) || ($studio && $target->hasStudioRole($studio, [UserRole::Designer]));

        if ($isIndependentProfessional || $isDesigner) {
            return true;
        }

        if ($type === 'tattoo' && $studio !== null && $target->hasStudioRole($studio, [UserRole::Artist])) {
            return $sender->canManageStudioAppointments($studio);
        }

        return false;
    }

    private function canView(?User $user, AppointmentRequest $appointmentRequest): bool
    {
        return $user instanceof User && (
            (int) $appointmentRequest->requester_user_id === (int) $user->id ||
            (int) $appointmentRequest->target_user_id === (int) $user->id ||
            ($appointmentRequest->studio && $user->canManageStudioAppointments($appointmentRequest->studio))
        );
    }

    private function canRespond(?User $user, AppointmentRequest $appointmentRequest): bool
    {
        return $user instanceof User && (
            (int) $appointmentRequest->target_user_id === (int) $user->id ||
            ($appointmentRequest->studio && $user->canManageStudioAppointments($appointmentRequest->studio))
        );
    }

    private function formatRequest(AppointmentRequest $appointmentRequest): array
    {
        return [
            'id'             => $appointmentRequest->id,
            'status'         => $appointmentRequest->status,
            'request_type'   => $appointmentRequest->request_type,
            'requested_at'   => optional($appointmentRequest->requested_at)->toIso8601String(),
            'image_path'     => $this->imageUrl($appointmentRequest->image_path),
            'tattoo_image_paths' => $this->imageUrls($appointmentRequest->tattoo_image_paths),
            'pickup_required' => (bool) $appointmentRequest->pickup_required,
            'notes'          => $appointmentRequest->notes,
            'response_notes' => $appointmentRequest->response_notes,
            'customer'       => [
                'first_name' => $appointmentRequest->first_name,
                'last_name' => $appointmentRequest->last_name,
                'hotel_name' => $appointmentRequest->hotel_name,
                'room_number' => $appointmentRequest->room_number,
                'place' => $appointmentRequest->place,
                'pax' => $appointmentRequest->pax,
                'phone_country_code' => $appointmentRequest->phone_country_code,
                'phone_number' => $appointmentRequest->phone_number,
            ],
            'first_name'     => $appointmentRequest->first_name,
            'last_name'      => $appointmentRequest->last_name,
            'hotel_name'     => $appointmentRequest->hotel_name,
            'room_number'    => $appointmentRequest->room_number,
            'place'          => $appointmentRequest->place,
            'pax'            => $appointmentRequest->pax,
            'price'          => $this->visiblePriceFor($appointmentRequest),
            'phone_country_code' => $appointmentRequest->phone_country_code,
            'phone_number'   => $appointmentRequest->phone_number,
            'requester'      => $this->formatUser($appointmentRequest->requester),
            'target'         => $this->formatUser($appointmentRequest->target),
            'studio'         => $appointmentRequest->studio ? [
                'id'   => $appointmentRequest->studio->id,
                'name' => $appointmentRequest->studio->name,
            ] : null,
            'appointment_id' => $appointmentRequest->appointment_id,
            'created_at'     => optional($appointmentRequest->created_at)->toIso8601String(),
        ];
    }

    private function formatUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id'    => $user->id,
            'name'  => $user->fullName(),
            'phone' => $user->phone,
            'role'  => $user->role?->value,
        ];
    }

    private function visiblePriceFor(AppointmentRequest $appointmentRequest): mixed
    {
        if ($this->canManagePrice(request()->user())) {
            return $appointmentRequest->price;
        }

        return $appointmentRequest->appointment?->status === 'completed'
            ? ($appointmentRequest->appointment->price ?? $appointmentRequest->price)
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

    private function storeRequestImage(Request $request, ?User $target, ?Studio $studio): string
    {
        $file = $request->file('image');
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $folder = $target !== null ? 'user-' . $target->id : 'studio-' . $studio?->id;
        $path = $file->storeAs('appointment-requests/' . $folder, $name, 'public');

        return Storage::disk('public')->url($path);
    }

    private function imageUrl(?string $path): ?string
    {
        if ($path === null || $path === '' || str_starts_with($path, 'http')) {
            return $path;
        }

        return str_starts_with($path, 'storage/') || str_starts_with($path, '/storage/')
            ? url($path)
            : url('storage/' . $path);
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

    /**
     * @param  array<int, string>  $existingPaths
     * @return array<int, string>
     */
    private function resolveTattooImagePaths(
        Request $request,
        ?User $target,
        ?Studio $studio,
        array $existingPaths
    ): array {
        $paths = array_values($existingPaths);
        $folder = $target !== null ? 'user-' . $target->id : 'studio-' . $studio?->id;

        foreach ($request->file('tattoo_images', []) as $file) {
            $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('appointment-requests/' . $folder . '/tattoo-images', $name, 'public');
            $paths[] = Storage::disk('public')->url($path);
        }

        if (count($paths) > 3) {
            throw ValidationException::withMessages([
                'tattoo_images' => ['En fazla 3 dövme görseli ekleyebilirsiniz.'],
            ]);
        }

        return $paths;
    }

    private function notifyTarget(User $target, User $requester, AppointmentRequest $appointmentRequest): void
    {
        app(FcmService::class)->sendToUser(
            $target,
            'Yeni Talep',
            "{$requester->fullName()}, {$appointmentRequest->requested_at?->format('d.m.Y H:i')} için talep gönderdi.",
            'appointment_request',
            [
                'appointment_request_id' => $appointmentRequest->id,
                'requester_id'            => $requester->id,
                'studio_id'               => $appointmentRequest->studio_id,
            ],
        );
    }

    private function notifyStudio(Studio $studio, User $requester, AppointmentRequest $appointmentRequest): void
    {
        $users = $studio->users()
            ->wherePivot('is_active', true)
            ->wherePivotIn('role', [
                UserRole::Supervisor->value,
                UserRole::Yonetici->value,
                UserRole::Info->value,
            ])
            ->get(['users.id']);

        foreach ($users as $user) {
            app(FcmService::class)->sendToUser(
                $user,
                'Yeni Stüdyo Talebi',
                "{$requester->fullName()}, {$studio->name} için {$appointmentRequest->requested_at?->format('d.m.Y H:i')} tarihli talep gönderdi.",
                'appointment_request',
                [
                    'appointment_request_id' => $appointmentRequest->id,
                    'studio_id'              => $studio->id,
                    'requester_id'           => $requester->id,
                ],
            );
        }
    }
}
