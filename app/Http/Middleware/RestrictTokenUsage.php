<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class RestrictTokenUsage
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Coba untuk mem-parsing token
            $token = JWTAuth::parseToken();
            $claims = $token->getPayload()->toArray();

            // Periksa apakah klaim 'restricted_to' ada
            if (isset($claims['restricted_to'])) {
                $allowedRoutes = $claims['restricted_to'];
                $currentRouteName = $request->route()->getName();

                // Jika rute saat ini tidak diizinkan, kembalikan respon error
                if (!in_array($currentRouteName, $allowedRoutes)) {
                    return response()->json([
                        'is_correct' => false,
                        'message' => 'Tidak diizinkan untuk rute ini'
                    ], 403);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'is_correct' => false,
                'message' => 'Token tidak valid atau tidak ditemukan'
            ], 401);
        }

        return $next($request);
    }
}
