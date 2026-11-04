<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ApplyLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale')
            ?? $request->user()?->locale
            ?? 'az';

        if (! in_array($locale, ['az', 'ru', 'en'], true)) {
            $locale = 'az';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
