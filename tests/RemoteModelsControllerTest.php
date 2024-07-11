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
    $response = post(route('remote-models.endpoint'), [
        'api_key' => config('remote-models.api-key'),
    ]);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->exception->getMessage())->toBe('The model field is required.');
});

it('fails without giving an api key', function () {
    $response = post(route('remote-models.endpoint'), [
        'model' => Celebrity::class,
    ]);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->exception->getMessage())->toBe('The api key field is required.');
});

it('throws 404 on model not in config', function () {
    $response = post(route('remote-models.endpoint'), [
        'model' => '\\App\\Models\\NonExistent',
        'api_key' => config('remote-models.api-key'),
    ]);

    expect($response->getStatusCode())->toBe(404);
});

it('has api endpoint', function () {
    expect(route('remote-models.endpoint'))->not->toBeNull();
});

it('calls api controller', function () {
    // Add some data into "database."
    Http::fake(mockDefaultHttpResponse());

    $response = post(route('remote-models.endpoint'), [
        'model' => Celebrity::class,
        'api_key' => config('remote-models.api-key'),
    ]);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->json())->toHaveKeys([
            'current_page',
            'last_page',
            'data',
            'total',
        ]);
});

it('calls api controller with pagination', function () {
    $people = [];
    for ($i = 0; $i < 15; $i++) {
        $id = $i + 1;

        $people[] = [
            'id' => $id,
            'name' => 'Person ' . $id
        ];
    }

    Http::fake([
        '*' . mockApiPath() => Http::sequence()
            // Initial schema call.
            ->push([
                'total' => 16,
                'per_page' => 1,
                'current_page' => 1,
                'last_page' => 2,
                'data' => $people
            ])
            // Loading data API calls.
            ->push([
                'total' => 16,
                'per_page' => 1,
                'current_page' => 1,
                'last_page' => 2,
                'data' => $people
            ])
            ->push([
                'total' => 16,
                'per_page' => 1,
                'current_page' => 2,
                'last_page' => 2,
                'data' => [
                    [
                        'id' => 999,
                        'name' => 'Dwayne Johnson',
                    ]
                ]
            ])
            // Page 1 - Controller API call.
            ->push([
                'total' => 16,
                'per_page' => 1,
                'current_page' => 1,
                'last_page' => 2,
                'data' => $people
            ])
            // Page 2 - Controller API call.
            ->push([
                'total' => 16,
                'per_page' => 1,
                'current_page' => 2,
                'last_page' => 2,
                'data' => [
                    [
                        'id' => 999,
                        'name' => 'Dwayne Johnson',
                    ]
                ]
            ]),
    ]);

    // Load the Celebrity's into the database.
    expect(Celebrity::count())->toBe(16);

    // Call the API to return paginated results, i.e. the first 15 people.
    $response = post(route('remote-models.endpoint'), [
        'model' => Celebrity::class,
        'api_key' => config('remote-models.api-key'),
    ]);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->json())->toHaveKeys([
            'current_page',
            'last_page',
            'data',
            'total',
        ])
        ->and(
            // First pagination dataset does not include the 16th person, yet.
            collect($response->json('data'))
                ->where('name', 'Dwayne Johnson')
                ->count()
        )->toBeEmpty();

    // Call next page of data.
    $response = post(route('remote-models.endpoint') . '?page=2', [
        'model' => Celebrity::class,
        'api_key' => config('remote-models.api-key'),
    ]);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->json())->toHaveKeys([
            'current_page',
            'last_page',
            'data',
            'total',
        ])
        ->and(
            // Second pagination dataset does have the 16th person.
            collect($response->json('data'))
                ->where('name', 'Dwayne Johnson')
                ->count()
        )->toBe(1);
});
