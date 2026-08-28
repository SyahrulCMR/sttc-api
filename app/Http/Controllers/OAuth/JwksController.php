<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Passport\JwksKey;
use Illuminate\Http\JsonResponse;

/**
 * JSON Web Key Set — kunci publik untuk verifikasi lokal access token di
 * resource server (sttc-siakad / sttc-website). Passport 13 tidak menyediakan
 * endpoint ini secara bawaan (ADR-0003).
 */
class JwksController extends Controller
{
    public function __invoke(JwksKey $key): JsonResponse
    {
        return response()->json(['keys' => [$key->jwk()]])
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
