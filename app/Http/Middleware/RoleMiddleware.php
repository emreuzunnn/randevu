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
            $canPass = $user->hasStudioRole($studio, $allowedRoles);

            if (! $canPass && in_array(UserRole::Admin, $allowedRoles, true) && $user->hasRole(UserRole::Admin)) {
                $canPass = true;
            }

            if (
                ! $canPass
                && (
                    in_array(UserRole::Yonetici, $allowedRoles, true)
                    || in_array(UserRole::Supervisor, $allowedRoles, true)
                )
                && $user->canManageStudioAppointments($studio)
            ) {
                $canPass = true;
            }

            abort_if(! $canPass, Response::HTTP_FORBIDDEN);

            return $next($request);
        }

        abort_if(! $user->hasAnyRole($allowedRoles), Response::HTTP_FORBIDDEN);

        return $next($request);
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
