<?php

it('defaults to bearer token prefix', function () {
    expect(config('sanctum.token_prefix'))->toBe('Bearer');
});
