<?php

use Tests\TestCase;

uses(TestCase::class);

it('defaults to bearer token prefix', function () {
    expect(config('sanctum.token_prefix'))->toBe('Bearer');
});
