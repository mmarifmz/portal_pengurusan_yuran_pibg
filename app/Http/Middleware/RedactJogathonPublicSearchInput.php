<?php

namespace App\Http\Middleware;

use Barryvdh\Debugbar\Facades\Debugbar;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedactJogathonPublicSearchInput
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->is('jogathon/*/cari-peserta')) {
            config(['debugbar.enabled' => false]);

            if (app()->bound('debugbar')) {
                app('debugbar')->disable();
            } elseif (class_exists(Debugbar::class)) {
                Debugbar::disable();
            }

            $request->request->remove('student_name');
        }

        return $next($request);
    }
}
