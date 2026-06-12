<?php

use Tests\TestCase;

uses(TestCase::class);

it('returns health ok', function () {
    $response = $this->get('/api/v1/health');

    $response->assertOk()
        ->assertJson(['status' => 'ok'])
        ->assertHeader('ETag');

    $etag = $response->headers->get('ETag');

    $cached = $this->get('/api/v1/health', [
        'If-None-Match' => $etag,
    ]);

    expect($cached->getStatusCode())->toBe(304);
    expect($cached->getContent())->toBe('');
});
