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
        abort_unless($user->hasAnyRole([UserRole::Admin, UserRole::Yonetici, UserRole::Supervisor]), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
