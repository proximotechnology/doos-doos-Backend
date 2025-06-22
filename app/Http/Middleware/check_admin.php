<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class check_admin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, ...$types)
    {
        $user = $request->user();

        if ($user->type != 1) {
            return response()->json(['message' => 'you are not admin'], 403);
        }

        return $next($request);
    }
}
