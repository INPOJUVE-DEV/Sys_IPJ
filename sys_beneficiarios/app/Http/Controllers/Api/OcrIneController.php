<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrIneController extends Controller
{
    /**
     * POST /api/ocr/ine/extract
     *
     * Receives front and back INE images, forwards them to the
     * external OCR Python service, and returns the extracted data.
     */
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'front_image' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
            'back_image' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
        ]);

        $config = config('services.ocr_ine');
        $url = rtrim($config['url'], '/') . '/v1/ine/extract';

        try {
            $frontFile = $request->file('front_image');
            $backFile = $request->file('back_image');
            $frontExt = $frontFile->getClientOriginalExtension() ?: ($frontFile->guessExtension() ?: 'jpg');
            $backExt = $backFile->getClientOriginalExtension() ?: ($backFile->guessExtension() ?: 'jpg');

            $response = Http::timeout($config['timeout'])
                ->withHeaders(array_filter([
                    'X-Api-Key' => $config['api_key'] ?: null,
                ]))
                ->attach('front_image', fopen($frontFile->getRealPath(), 'r'), 'front.' . $frontExt)
                ->attach('back_image', fopen($backFile->getRealPath(), 'r'), 'back.' . $backExt)
                ->post($url);
        } catch (ConnectionException $e) {
            Log::warning('OCR INE service unreachable', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error_code' => 'OCR_SERVICE_UNAVAILABLE',
                'message' => 'El servicio OCR no está disponible en este momento. Intenta de nuevo más tarde.',
            ], 502);
        } catch (\Throwable $e) {
            Log::error('OCR INE unexpected error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error_code' => 'OCR_INTERNAL_ERROR',
                'message' => 'Error interno al procesar la solicitud OCR.',
            ], 500);
        }

        // Forward OCR service errors
        if ($response->failed()) {
            $status = $response->status();
            $body = $response->json() ?? [];

            Log::warning('OCR INE service error', [
                'status' => $status,
                'body' => $body,
            ]);

            // Map OCR service errors to appropriate HTTP status
            $mappedStatus = match (true) {
                $status === 429 => 429,
                $status >= 500 => 502,
                default => $status,
            };

            return response()->json([
                'error_code' => $body['error_code'] ?? 'OCR_SERVICE_ERROR',
                'message' => $body['message'] ?? 'Error en el servicio OCR.',
                'details' => $body['details'] ?? null,
            ], $mappedStatus);
        }

        // Return successful OCR result
        return response()->json($response->json());
    }
}
