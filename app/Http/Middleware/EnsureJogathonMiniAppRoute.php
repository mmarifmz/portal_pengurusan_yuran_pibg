<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJogathonMiniAppRoute
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $routeName = (string) ($route?->getName() ?? '');

        if ($route === null || $this->isAllowedRoute($routeName)) {
            return $next($request);
        }

        abort(404);
    }

    private function isAllowedRoute(string $routeName): bool
    {
        $allowedExact = [
            'dashboard',
            'home',
            'login',
            'login.store',
            'logout',
            'profile.edit',
            'register',
            'security.edit',
        ];

        if (in_array($routeName, $allowedExact, true)) {
            return true;
        }

        foreach ([
            '_debugbar.',
            'jogathon.',
            'livewire.',
            'password.',
            'profile.',
            'system.jogathon.',
            'teacher.jogathon.',
            'two-factor.',
            'verification.',
        ] as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
