<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Studio;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, Response::HTTP_UNAUTHORIZED);
        abort_if($roles === [], Response::HTTP_FORBIDDEN, 'Yetkili rol tanimlanmadi.');

        $allowedRoles = array_map(
            static fn (string $role): UserRole => UserRole::fromValue($role),
            $roles,
        );

        $studio = $this->resolveStudio($request);

        if ($studio !== null) {
            // Stüdyo pivot rolünü kontrol et
            $canPass = $user->hasStudioRole($studio, $allowedRoles);

            // Platform admin her zaman geçer
            if (! $canPass && in_array(UserRole::Admin, $allowedRoles, true) && $user->hasRole(UserRole::Admin)) {
                $canPass = true;
            }

            // Yönetim rolleri için canManageStudioAppointments kontrolü
            $managementRoles = [UserRole::Yonetici, UserRole::Supervisor];
            if (! $canPass && array_intersect($allowedRoles, $managementRoles) !== []) {
                if ($user->canManageStudioAppointments($studio)) {
                    $canPass = true;
                }
            }

            // Randevu yönetimi gerektiren çalışan rolleri
            $staffRoles = [UserRole::Designer, UserRole::Info, UserRole::Sofor, UserRole::Calisan];
            if (! $canPass && array_intersect($allowedRoles, $staffRoles) !== []) {
                if ($user->canManageStudioAppointments($studio)) {
                    $canPass = true;
                }
            }

            // Şoför, çalıştığı şubedeki tüm stüdyoların randevu ekranlarına erişebilir.
            if (
                ! $canPass
                && in_array(UserRole::Sofor, $allowedRoles, true)
                && $this->isStudioAppointmentRequest($request)
                && $this->driverWorksInStudioBranch($request, $studio)
            ) {
                $canPass = true;
            }

            abort_if(! $canPass, Response::HTTP_FORBIDDEN);

            return $next($request);
        }

        abort_if(! $user->hasAnyRole($allowedRoles), Response::HTTP_FORBIDDEN);

        return $next($request);
    }

    private function isStudioAppointmentRequest(Request $request): bool
    {
        return $request->is('api/studios/*/appointment-support')
            || $request->is('api/studios/*/appointments')
            || $request->is('api/studios/*/appointments/*');
    }

    private function driverWorksInStudioBranch(Request $request, Studio $studio): bool
    {
        $user = $request->user();
        if (! $user?->hasRole(UserRole::Sofor) || $studio->shop_id === null) {
            return false;
        }

        $driverShopIds = Studio::query()
            ->whereIn('id', $user->studios()->pluck('studios.id'))
            ->pluck('shop_id')
            ->filter()
            ->unique();

        return $driverShopIds->contains((int) $studio->shop_id);
    }

    private function resolveStudio(Request $request): ?Studio
    {
        $studio = $request->route('studio');

        if ($studio instanceof Studio) {
            return $studio;
        }

        if (is_numeric($studio)) {
            return Studio::query()->find((int) $studio);
        }

        $studioId = $request->integer('studio_id');

        if ($studioId > 0) {
            return Studio::query()->find($studioId);
        }

        return null;
    }

}
