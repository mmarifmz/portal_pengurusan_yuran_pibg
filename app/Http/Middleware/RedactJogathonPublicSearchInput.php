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

            app()->instance('jogathon.student_name_search', trim((string) $request->input('student_name')));
            $request->request->set('student_name', '[redacted]');
        }

        return $next($request);
    }
}
