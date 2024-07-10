<?php

namespace Tests;

uses()->group('controller');

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Models\Celebrity;
use function Pest\Laravel\{post};

it('does not register api endpoint with no models in config', function () {
    Config::set('remote-models.host_models', []);

    $routeDoesntExist = route('remote-models.endpoint', ['model' => Celebrity::class]);
})
    ->throws(\Exception::class, 'Route [remote-models.endpoint] not defined.')
    ->skip('Have to set models config in TestCase for others to work...');

it('fails without giving a model class', function () {
    Config::set('remote-models.host_models', []);

    $routeFails = route('remote-models.endpoint');
})->throws(\Exception::class);

it('has api endpoint', function () {
    $route = route('remote-models.endpoint', ['model' => Celebrity::class]);

    expect($route)->not->toBeNull();
});

it('calls api controller', function () {
    // Add some data into "database."
    Http::fake(mockDefaultHttpResponse(endpoint: '/celebrity'));

    $response = post(route('remote-models.endpoint', ['model' => Celebrity::class]));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->json())->toHaveKeys([
            'current_page',
            'last_page',
            'data',
            'total',
        ]);
});

it('throws 404 on model not in config', function () {
    $response = post(route('remote-models.endpoint', ['model' => '\\App\\Models\\NonExistent']));

    expect($response->getStatusCode())->toBe(404);
});
