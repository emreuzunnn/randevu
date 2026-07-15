<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Studio;
use App\Models\User;
use App\Services\AppointmentNotificationService;
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
            $managedStudioIds = collect($user->staffScopeStudioIds())
                ->merge($user->studios()
                    ->wherePivot('is_active', true)
                    ->wherePivotIn('role', [
                        UserRole::Supervisor->value,
                        UserRole::Yonetici->value,
                        UserRole::Info->value,
                        UserRole::Designer->value,
                    ])
                    ->pluck('studios.id')
                    ->map(static fn ($id): int => (int) $id))
                ->unique()
                ->values();

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
            'deposit_amount'     => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'payment_method'     => ['required_if:type,tattoo', 'nullable', 'string', 'in:'.implode(',', array_keys(AppointmentController::PAYMENT_METHODS))],
            'ticket_types'       => ['required_if:type,tattoo', 'nullable', 'array', 'max:4'],
            'ticket_types.*'     => ['string', 'in:'.implode(',', array_keys(AppointmentController::TICKET_TYPES))],
            'tattoo_type'        => ['required_if:type,tattoo', 'nullable', 'string', 'in:'.implode(',', array_keys(AppointmentController::TATTOO_TYPES))],
            'image'              => ['required_without:image_path', 'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'image_path'         => ['required_without:image', 'nullable', 'string', 'max:2048'],
            'tattoo_image_paths' => ['nullable', 'array'],
            'tattoo_image_paths.*' => ['string', 'max:2048'],
            'tattoo_images'      => ['nullable', 'array'],
            'tattoo_images.*'    => ['image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'pickup_required'    => ['sometimes', 'boolean'],
        ]);
        $validated = $this->withoutUnauthorizedPrice($validated, $authUser);

        $isStudioTargetedRequest = empty($validated['artist_id']);
        $target = ! empty($validated['artist_id'])
            ? User::query()->findOrFail($validated['artist_id'])
            : null;
        abort_if(
            $target !== null && (int) $target->id === (int) $authUser->id,
            422,
            'Kendinize talep gönderemezsiniz.'
        );
        abort_if($target?->banned_at !== null, 422, 'Banlı kullanıcıya talep gönderilemez.');
        $studio = $this->resolveStudio($target, $validated['studio_id'] ?? null);
        $type = $this->resolveRequestType($target, $studio, $validated['type'] ?? null);
        $validated = $this->normalizeTicketAttributes($validated, $type, true);

        if (
            $target !== null
            && $studio !== null
            && ! $target->isIndependentProfessional()
            && $target->hasStudioRole($studio, [$type === 'designer' ? UserRole::Designer : UserRole::Artist])
        ) {
            $target = null;
            $isStudioTargetedRequest = true;
        }

        abort_unless(
            $this->canSendRequestToTarget($authUser, $target, $studio, $type, $isStudioTargetedRequest),
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
            $validated['tattoo_image_paths'] ?? [],
            $type
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
            'deposit_amount'     => $validated['deposit_amount'] ?? null,
            'payment_method'     => $validated['payment_method'] ?? null,
            'ticket_types'       => $validated['ticket_types'] ?? [],
            'tattoo_type'        => $validated['tattoo_type'] ?? null,
            'status'             => 'pending',
        ]);

        if ($target !== null) {
            $this->notifyTarget($target, $authUser, $appointmentRequest);
        }

        if ($studio !== null && $isStudioTargetedRequest) {
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
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'payment_method' => ['nullable', 'string', 'in:'.implode(',', array_keys(AppointmentController::PAYMENT_METHODS))],
            'ticket_types'   => ['nullable', 'array', 'max:4'],
            'ticket_types.*' => ['string', 'in:'.implode(',', array_keys(AppointmentController::TICKET_TYPES))],
            'tattoo_type'    => ['nullable', 'string', 'in:'.implode(',', array_keys(AppointmentController::TATTOO_TYPES))],
        ]);
        $validated = $this->withoutUnauthorizedPrice($validated, $request->user(), $appointmentRequest);
        $validated = $this->normalizeTicketAttributes($validated, $appointmentRequest->request_type, false);

        $appointment = DB::transaction(function () use ($appointmentRequest, $validated): Appointment {
            $requestedAt = $this->resolveRequestedAt($validated, $appointmentRequest->requested_at);
            $target = $appointmentRequest->target;
            $isIndependentProfessional = $target?->isIndependentProfessional() === true;
            $studio = $isIndependentProfessional
                ? null
                : ($appointmentRequest->studio ?? $target?->studios()->wherePivot('is_active', true)->first());
            $assignedProfessional = $target ?? ($studio ? $this->resolveStudioTargetByType($studio, $appointmentRequest->request_type) : null);

            if (! $isIndependentProfessional && $studio === null) {
                throw ValidationException::withMessages([
                    'studio_id' => ['Talebi randevuya çevirmek için stüdyo bilgisi gerekli.'],
                ]);
            }

            $appointment = Appointment::query()->create([
                'studio_id'               => $studio?->id,
                'created_by_user_id'      => $appointmentRequest->requester_user_id,
                'assigned_artist_user_id' => $assignedProfessional?->id,
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
                'pickup_required'         => $appointmentRequest->request_type === 'designer'
                    ? true
                    : $appointmentRequest->pickup_required,
                'is_old_customer'         => false,
                'pax'                     => $validated['pax'] ?? $appointmentRequest->pax ?? 1,
                'price'                   => $validated['price'] ?? $appointmentRequest->price,
                'deposit_amount'          => $validated['deposit_amount'] ?? $appointmentRequest->deposit_amount,
                'payment_method'          => $validated['payment_method'] ?? $appointmentRequest->payment_method,
                'ticket_types'            => $validated['ticket_types'] ?? $appointmentRequest->ticket_types ?? [],
                'tattoo_type'             => $validated['tattoo_type'] ?? $appointmentRequest->tattoo_type,
            ]);

            $appointmentRequest->fill([
                'studio_id'       => $studio?->id,
                'target_user_id'  => $assignedProfessional?->id,
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
                'deposit_amount'  => $validated['deposit_amount'] ?? $appointmentRequest->deposit_amount,
                'payment_method'  => $validated['payment_method'] ?? $appointmentRequest->payment_method,
                'ticket_types'    => $validated['ticket_types'] ?? $appointmentRequest->ticket_types,
                'tattoo_type'     => $validated['tattoo_type'] ?? $appointmentRequest->tattoo_type,
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

        app(AppointmentNotificationService::class)
            ->notifyStudioAppointmentCreated($appointment, $request->user());

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

        if ($target === null && $studio !== null) {
            throw ValidationException::withMessages([
                'type' => ['Stüdyo talebi için talep türü seçilmelidir.'],
            ]);
        }

        if ($target && ($target->profileRole() === UserRole::Designer || ($studio && $target->hasStudioRole($studio, [UserRole::Designer])))) {
            return 'designer';
        }

        return 'tattoo';
    }

    private function resolveStudioTargetByType(Studio $studio, string $type): User
    {
        $role = $type === 'designer' ? UserRole::Designer : UserRole::Artist;
        $users = $studio->users()
            ->wherePivot('is_active', true)
            ->wherePivot('role', $role->value)
            ->get();

        if ($users->count() === 0) {
            throw ValidationException::withMessages([
                'type' => ["Bu stüdyoda aktif {$role->label()} bulunmuyor."],
            ]);
        }

        if ($users->count() > 1) {
            throw ValidationException::withMessages([
                'type' => ["Bu stüdyoda birden fazla aktif {$role->label()} var."],
            ]);
        }

        return $users->first();
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

    private function canSendRequestToTarget(
        User $sender,
        ?User $target,
        ?Studio $studio,
        string $type,
        bool $isStudioTargetedRequest = false
    ): bool
    {
        if ($target === null) {
            return $studio !== null;
        }

        $independentRole = $type === 'designer' ? UserRole::Designer : UserRole::Artist;
        $isIndependentProfessional = $target->isIndependentProfessionalFor($independentRole);
        $isDesigner = $target->profileRole() === UserRole::Designer || ($studio && $target->hasStudioRole($studio, [UserRole::Designer]));

        if (
            $isStudioTargetedRequest
            && $studio !== null
            && $target->hasStudioRole($studio, [$independentRole])
        ) {
            return true;
        }

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
            ($appointmentRequest->studio && (
                $user->canManageStudioAppointments($appointmentRequest->studio)
                || $user->studios()
                    ->where('studios.id', $appointmentRequest->studio->id)
                    ->wherePivot('is_active', true)
                    ->exists()
            ))
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
        $canViewCustomerContact = $this->canViewCustomerContactFor(request()->user(), $appointmentRequest);

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
                'phone_country_code' => $canViewCustomerContact ? $appointmentRequest->phone_country_code : null,
                'phone_number' => $canViewCustomerContact ? $appointmentRequest->phone_number : null,
            ],
            'can_view_customer_contact' => $canViewCustomerContact,
            'first_name'     => $appointmentRequest->first_name,
            'last_name'      => $appointmentRequest->last_name,
            'hotel_name'     => $appointmentRequest->hotel_name,
            'room_number'    => $appointmentRequest->room_number,
            'place'          => $appointmentRequest->place,
            'pax'            => $appointmentRequest->pax,
            'price'          => $this->visiblePriceFor($appointmentRequest),
            'deposit_amount' => $this->visibleDepositFor($appointmentRequest),
            'payment_method' => $appointmentRequest->payment_method,
            'payment_method_label' => $appointmentRequest->payment_method
                ? (AppointmentController::PAYMENT_METHODS[$appointmentRequest->payment_method] ?? $appointmentRequest->payment_method)
                : null,
            'ticket_types' => $appointmentRequest->request_type === 'tattoo'
                ? array_values($appointmentRequest->ticket_types ?? [])
                : [],
            'ticket_type_labels' => $appointmentRequest->request_type === 'tattoo'
                ? collect($appointmentRequest->ticket_types ?? [])
                    ->map(fn (string $type): string => AppointmentController::TICKET_TYPES[$type] ?? $type)
                    ->values()
                    ->all()
                : [],
            'tattoo_type' => $appointmentRequest->request_type === 'tattoo' ? $appointmentRequest->tattoo_type : null,
            'tattoo_type_label' => $appointmentRequest->request_type === 'tattoo' && $appointmentRequest->tattoo_type
                ? (AppointmentController::TATTOO_TYPES[$appointmentRequest->tattoo_type] ?? $appointmentRequest->tattoo_type)
                : null,
            'phone_country_code' => $canViewCustomerContact ? $appointmentRequest->phone_country_code : null,
            'phone_number'   => $canViewCustomerContact ? $appointmentRequest->phone_number : null,
            'requester'      => $this->formatUser($appointmentRequest->requester, $canViewCustomerContact),
            'target'         => $this->formatUser($appointmentRequest->target),
            'studio'         => $appointmentRequest->studio ? [
                'id'   => $appointmentRequest->studio->id,
                'name' => $appointmentRequest->studio->name,
            ] : null,
            'appointment_id' => $appointmentRequest->appointment_id,
            'created_at'     => optional($appointmentRequest->created_at)->toIso8601String(),
        ];
    }

    private function formatUser(?User $user, bool $includePhone = false): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id'    => $user->id,
            'name'  => $user->fullName(),
            'phone' => $includePhone ? $user->phone : null,
            'role'  => $user->role?->value,
        ];
    }

    private function canViewCustomerContactFor(?User $user, AppointmentRequest $appointmentRequest): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ((int) $appointmentRequest->requester_user_id === (int) $user->id) {
            return true;
        }

        if ($appointmentRequest->studio && $user->canManageStudio($appointmentRequest->studio)) {
            return true;
        }

        if ((int) $appointmentRequest->target_user_id === (int) $user->id) {
            $targetRole = $appointmentRequest->request_type === 'designer'
                ? UserRole::Designer
                : UserRole::Artist;

            return $user->isIndependentProfessionalFor($targetRole);
        }

        return false;
    }

    private function visiblePriceFor(AppointmentRequest $appointmentRequest): mixed
    {
        return $this->canViewMoneyFor($appointmentRequest)
            ? $appointmentRequest->price
            : ($appointmentRequest->appointment?->status === 'completed'
                ? ($appointmentRequest->appointment->price ?? $appointmentRequest->price)
                : null);
    }

    private function visibleDepositFor(AppointmentRequest $appointmentRequest): mixed
    {
        return $this->canViewMoneyFor($appointmentRequest)
            ? $appointmentRequest->deposit_amount
            : ($appointmentRequest->appointment?->status === 'completed'
                ? ($appointmentRequest->appointment->deposit_amount ?? $appointmentRequest->deposit_amount)
                : null);
    }

    private function canViewMoneyFor(AppointmentRequest $appointmentRequest): bool
    {
        if ($this->canManagePrice(request()->user(), $appointmentRequest)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function withoutUnauthorizedPrice(array $validated, ?User $user, ?AppointmentRequest $appointmentRequest = null): array
    {
        if (! $this->canManagePrice($user, $appointmentRequest)) {
            unset($validated['price']);
            unset($validated['deposit_amount']);
        }

        return $validated;
    }

    private function canManagePrice(?User $user, ?AppointmentRequest $appointmentRequest = null): bool
    {
        if ($user?->hasAnyRole([
            UserRole::Admin,
            UserRole::Yonetici,
            UserRole::Supervisor,
        ]) === true) {
            return true;
        }

        return $appointmentRequest !== null
            && (int) $appointmentRequest->target_user_id === (int) $user?->id
            && $user?->isIndependentProfessional() === true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeTicketAttributes(array $attributes, string $requestType, bool $creating): array
    {
        if ($requestType !== 'tattoo') {
            $attributes['ticket_types'] = [];
            $attributes['tattoo_type'] = null;
            $attributes['payment_method'] = null;
            $attributes['deposit_amount'] = null;
            $attributes['price'] = null;
            $attributes['pickup_required'] = true;

            return $attributes;
        }

        if (array_key_exists('ticket_types', $attributes)) {
            $attributes['ticket_types'] = collect($attributes['ticket_types'] ?? [])
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if ($creating) {
            $missing = [];
            if (empty($attributes['ticket_types'])) {
                $missing['ticket_types'] = ['Bilet türü seçilmelidir.'];
            }
            if (empty($attributes['tattoo_type'])) {
                $missing['tattoo_type'] = ['Dövme türü seçilmelidir.'];
            }
            if (empty($attributes['payment_method'])) {
                $missing['payment_method'] = ['Ödeme yöntemi seçilmelidir.'];
            }
            if ($missing !== []) {
                throw ValidationException::withMessages($missing);
            }
        }

        return $attributes;
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
        array $existingPaths,
        string $requestType
    ): array {
        $paths = array_values($existingPaths);
        $folder = $target !== null ? 'user-' . $target->id : 'studio-' . $studio?->id;

        foreach ($request->file('tattoo_images', []) as $file) {
            $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('appointment-requests/' . $folder . '/tattoo-images', $name, 'public');
            $paths[] = Storage::disk('public')->url($path);
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
        $studio->loadMissing('company.manager');
        $studioUsers = $studio->users()
            ->wherePivot('is_active', true)
            ->wherePivotIn('role', [
                UserRole::Admin->value,
                UserRole::Supervisor->value,
                UserRole::Yonetici->value,
                UserRole::Info->value,
                UserRole::Designer->value,
            ])
            ->get();
        $admins = User::query()
            ->where('role', UserRole::Admin->value)
            ->whereNull('banned_at')
            ->get();
        $users = $studioUsers
            ->merge($admins)
            ->when(
                $studio->company?->manager instanceof User,
                fn ($items) => $items->push($studio->company->manager)
            )
            ->filter(fn ($user): bool => $user instanceof User && $user->banned_at === null)
            ->unique('id')
            ->values();

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
