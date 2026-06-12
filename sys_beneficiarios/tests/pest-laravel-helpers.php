<?php

namespace Pest\Laravel;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Testing\TestResponse;

function actingAs(Authenticatable $user, ?string $guard = null)
{
    return test()->actingAs($user, $guard);
}

function get(string $uri, array $headers = []): TestResponse
{
    return test()->get($uri, $headers);
}

function getJson(string $uri, array $headers = [], int $options = 0): TestResponse
{
    return test()->getJson($uri, $headers, $options);
}

function postJson(string $uri, array $data = [], array $headers = [], int $options = 0): TestResponse
{
    return test()->postJson($uri, $data, $headers, $options);
}

function putJson(string $uri, array $data = [], array $headers = [], int $options = 0): TestResponse
{
    return test()->putJson($uri, $data, $headers, $options);
}
