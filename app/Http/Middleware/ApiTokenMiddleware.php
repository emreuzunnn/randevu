<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        $plainToken = $request->bearerToken();

        if (! is_string($plainToken) || trim($plainToken) === '') {
            abort(Response::HTTP_UNAUTHORIZED, 'Yetkisiz istek.');
        }

        $hashedToken = hash('sha256', $plainToken);

        $user = User::query()
            ->where('api_token', $hashedToken)
            ->first();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Token gecersiz.');
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn (): User => $user);

        return $next($request);
    }
}
