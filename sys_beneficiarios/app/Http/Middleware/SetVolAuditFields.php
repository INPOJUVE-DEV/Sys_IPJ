<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetVolAuditFields
{
    public function handle(Request $request, Closure $next)
    {
        $userId = Auth::id();
        if (! $userId) {
            return $next($request);
        }

        $path = $request->path();
        $method = strtolower($request->method());

        if ($method === 'post') {
            if ($this->isGroupRoute($path)) {
                $request->merge(['created_by' => $userId]);
            }
            if ($this->isEnrollmentRoute($path)) {
                $request->merge(['created_by' => $userId]);
            }
        }

        if (in_array($method, ['put', 'patch'], true) && $this->isGroupRoute($path)) {
            $request->merge(['updated_by' => $userId]);
        }

        return $next($request);
    }

    private function isGroupRoute(string $path): bool
    {
        return str_contains($path, 'bienestar/volante/groups');
    }

    private function isEnrollmentRoute(string $path): bool
    {
        return str_contains($path, 'bienestar/volante/groups') && str_contains($path, 'enrollments');
    }
}