<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class ExcelDownloadStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $token = $request->query('_excel_token');
        $disposition = $response->headers->get('Content-Disposition', '');
        $type = strtolower($response->headers->get('Content-Type', ''));

        if ($request->isMethod('GET') && is_string($token) && preg_match('/\A[a-f0-9]{32}\z/', $token)
            && $response->isSuccessful() && stripos($disposition, 'attachment') !== false
            && (str_contains($type, 'spreadsheetml') || str_contains($type, 'vnd.ms-excel')
                || preg_match('/\.xlsx?(?:[";\s]|$)/i', $disposition))) {
            // Global middleware runs outside web cookie encryption. This is a short-lived,
            // non-secret acknowledgement, not proof that bytes were saved to disk.
            $response->headers->setCookie(Cookie::create('whfg_excel_'.$token, 'ready', time() + 120)
                ->withSecure($request->isSecure())->withHttpOnly(false)->withSameSite(Cookie::SAMESITE_LAX));
        }

        return $response;
    }
}
