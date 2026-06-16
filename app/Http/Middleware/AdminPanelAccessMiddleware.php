<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminPanelAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, Response::HTTP_UNAUTHORIZED);
        abort_if($user->banned_at !== null, Response::HTTP_FORBIDDEN, 'Hesabınız banlanmıştır.');
        abort_unless($user->hasAnyRole([
            UserRole::Admin,
            UserRole::Yonetici,
            UserRole::Supervisor,
            UserRole::Artist,
            UserRole::Info,
            UserRole::Designer,
            UserRole::Sofor,
            UserRole::Calisan,
            UserRole::KullaniciRol,
            UserRole::Kullanici,
        ]), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
