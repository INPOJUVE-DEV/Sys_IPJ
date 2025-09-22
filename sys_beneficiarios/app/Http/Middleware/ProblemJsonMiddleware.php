<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ProblemJsonMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (ValidationException $exception) {
            return $this->problem(
                request: $request,
                status: 422,
                title: 'Unprocessable Entity',
                detail: 'The given data was invalid.',
                errors: $exception->errors()
            );
        } catch (AuthenticationException $exception) {
            return $this->problem(
                request: $request,
                status: 401,
                title: 'Unauthorized',
                detail: $exception->getMessage() ?: 'Authentication required.'
            );
        } catch (AuthorizationException $exception) {
            return $this->problem(
                request: $request,
                status: 403,
                title: 'Forbidden',
                detail: $exception->getMessage() ?: 'This action is unauthorized.'
            );
        } catch (ModelNotFoundException $exception) {
            return $this->problem(
                request: $request,
                status: 404,
                title: 'Not Found',
                detail: 'Resource not found.'
            );
        } catch (Throwable $exception) {
            report($exception);

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            $title = match ($status) {
                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                409 => 'Conflict',
                422 => 'Unprocessable Entity',
                default => 'Internal Server Error',
            };

            $detail = $exception->getMessage() ?: $title;

            if (! app()->hasDebugModeEnabled() && $status >= 500) {
                $detail = 'An unexpected error occurred.';
            }

            return $this->problem(
                request: $request,
                status: $status,
                title: $title,
                detail: $detail
            );
        }
    }

    protected function problem(Request $request, int $status, string $title, string $detail, array $errors = []): JsonResponse
    {
        $payload = [
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->fullUrl(),
        ];

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
