<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

it('only exposes the versioned inbound integration route', function () {
    $route = Route::getRoutes()->match(
        \Illuminate\Http\Request::create('/api/v1/integrations/api-tj/staging/accept', 'POST')
    );

    expect($route->uri())->toBe('api/v1/integrations/api-tj/staging/accept');

    $legacyRequest = \Illuminate\Http\Request::create('/api/integrations/api-tj/staging/accept', 'POST');

    expect(fn () => Route::getRoutes()->match($legacyRequest))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
});
