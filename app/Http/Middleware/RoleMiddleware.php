<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $parsedRoles = [];
        foreach ($roles as $role) {
            foreach (explode(',', $role) as $r) {
                $parsedRoles[] = strtolower(trim($r));
            }
        }

        $userRole = strtolower(trim($user?->role ?? ''));

        \Log::info('ROLE MIDDLEWARE', [
            'url' => $request->path(),
            'user_role' => $userRole,
            'allowed_roles' => $parsedRoles,
        ]);

        if (! $user || ! in_array($userRole, $parsedRoles, true)) {
            return response()->json([
                'message' => 'Anda tidak memiliki hak akses.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
