<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impede que a sessão web tente hidratar App\Models\User no banco central.
 * User vive só no DB do tenant; sessão poluída por Auth::login quebra csrf/login.
 */
class ForgetTenantUserFromCentralSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            $session = $request->session();
            $loginKey = Auth::guard('web')->getName();

            if ($session->has($loginKey)) {
                $session->forget($loginKey);
            }

            // Laravel também pode guardar "password_hash_web"
            $session->forget('password_hash_'.Auth::guard('web')->getName());
            $session->forget('password_hash_web');
        }

        return $next($request);
    }
}
