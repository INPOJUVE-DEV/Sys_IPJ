<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ETagMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');
        if (stripos($contentType, 'application/json') === false) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || $content === null || $content === '') {
            return $response;
        }

        $hash = hash('sha256', $content);
        $etag = '"'.$hash.'"';
        $response->headers->set('ETag', $etag);

        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($ifNoneMatch !== null) {
            $candidates = array_map('trim', explode(',', $ifNoneMatch));

            foreach ($candidates as $candidate) {
                $candidate = trim(str_ireplace('W/', '', $candidate), '"');

                if (hash_equals($hash, $candidate)) {
                    $response->setStatusCode(Response::HTTP_NOT_MODIFIED);
                    $response->setContent('');
                    break;
                }
            }
        }

        return $response;
    }
}
