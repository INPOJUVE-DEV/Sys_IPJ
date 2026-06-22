<?php

use Tests\TestCase;

uses(TestCase::class);

it('returns health ok', function () {
    $response = $this->get('/api/health');

    $response->assertOk()
        ->assertJson(['status' => 'ok'])
        ->assertHeader('ETag');

    $etag = $response->headers->get('ETag');

    $cached = $this->get('/api/health', [
        'If-None-Match' => $etag,
    ]);

    expect($cached->getStatusCode())->toBe(304);
    expect($cached->getContent())->toBe('');
});
