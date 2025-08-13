<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BasicAuth
{
    public function handle(Request $request, Closure $next)
    {
        $user = env('BASIC_AUTH_USER');
        $pass = env('BASIC_AUTH_PASS');
        if (!$user || !$pass) {
            return $next($request);
        }

        if ($request->getUser() !== $user || $request->getPassword() !== $pass) {
            $headers = ['WWW-Authenticate' => 'Basic realm="Restricted"'];
            return response('Unauthorized', 401, $headers);
        }
        return $next($request);
    }
}
