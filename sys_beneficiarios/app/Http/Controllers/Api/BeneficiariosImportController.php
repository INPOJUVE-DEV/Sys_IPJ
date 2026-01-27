<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBeneficiariosCacheRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BeneficiariosImportController extends Controller
{
    private const CACHE_TTL_SECONDS = 86400;
    private const CACHE_PREFIX = 'beneficiarios.import.';

    public function store(StoreBeneficiariosCacheRequest $request)
    {
        $data = $request->validated();
        $beneficiarios = $data['beneficiarios'];

        $cacheKey = self::CACHE_PREFIX . (string) Str::uuid();
        $payload = [
            'source' => $data['source'] ?? null,
            'submitted_by' => $request->user()?->uuid,
            'received_at' => now()->toIso8601String(),
            'beneficiarios' => $beneficiarios,
        ];

        $expiresAt = now()->addSeconds(self::CACHE_TTL_SECONDS);
        Cache::put($cacheKey, $payload, $expiresAt);

        return response()->json([
            'cache_key' => $cacheKey,
            'expires_at' => $expiresAt->toIso8601String(),
            'count' => count($beneficiarios),
        ], 201);
    }
}
