<?php

use Tests\TestCase;
use function Pest\Laravel\get;

uses(TestCase::class);

it('returns health ok', function () {
    $response = get('/api/v1/health');

    $response->assertOk()
        ->assertJson(['status' => 'ok'])
        ->assertHeader('ETag');

    $etag = $response->headers->get('ETag');

    $cached = get('/api/v1/health', [
        'If-None-Match' => $etag,
    ]);

    expect($cached->getStatusCode())->toBe(304);
    expect($cached->getContent())->toBe('');
});