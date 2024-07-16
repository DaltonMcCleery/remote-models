<?php

namespace Tests;

uses()->group('trait');

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Models\Celebrity;
use Tests\Models\CelebrityCustomModel;
use Tests\Models\CelebrityWithCustomEndpoint;
use Tests\Models\CelebrityWithSchema;

beforeEach(function () {
    Config::set('remote-models.domain', 'https://yourdomain.com/');
});

it('loads data via pagination', function () {
    Http::fake([
        '*' . mockApiPath() => Http::sequence()
            // Initial schema call.
            ->push([
                'total' => 2,
                'per_page' => 1,
                'current_page' => 1,
                'last_page' => 2,
                'data' => [
                    [
                        'id' => 888,
                        'name' => 'The Rock',
                    ]
                ]
            ])
            // Loading data API calls.
            ->push([
                'total' => 2,
                'per_page' => 1,
                'current_page' => 1,
                'last_page' => 2,
                'data' => [
                    [
                        'id' => 888,
                        'name' => 'The Rock',
                    ]
                ]
            ])
            ->push([
                'total' => 2,
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

    expect(Celebrity::where('name', 'Dwayne Johnson')->first()->id)->toBe(999)
        ->and(Celebrity::where('name', 'The Rock')->first()->id)->toBe(888);
});

it('loads user from where query', function () {
    Http::fake(mockDefaultHttpResponse());

    expect(Celebrity::where('name', 'Dwayne Johnson')->first()->id)->toBe(999)
        ->and(Celebrity::where('name', 'The Rock')->first())->toBeNull();
});

it('changes domain in config', function () {
    Config::set('remote-models.domain', 'https://mydomain.com/');

    Http::fake(mockDefaultHttpResponse(domain: 'mydomain.com'));

    expect(Celebrity::where('name', 'Dwayne Johnson')->first()->id)->toBe(999);
});

it('loads user from plain API', function () {
    Http::fake([
        '*' . mockApiPath() => Http::response([
            [
                'id' => 999,
                'name' => 'Dwayne Johnson',
            ]
        ]),
    ]);

    expect(Celebrity::where('name', 'Dwayne Johnson')->first()->id)->toBe(999)
        ->and(Celebrity::where('name', 'The Rock')->first())->toBeNull();
});

it('explicitly adds endpoint', function () {
    Http::fake([
        '*' . mockApiPath(endpoint: '/custom-endpoint') => Http::response([
            [
                'id' => 999,
                'name' => 'Dwayne Johnson',
            ]
        ]),
    ]);

    expect(CelebrityWithCustomEndpoint::where('name', 'Dwayne Johnson')->first()->id)->toBe(999)
        ->and(CelebrityWithCustomEndpoint::where('name', 'The Rock')->first())->toBeNull();
});

it('correctly maps datetimes', function () {
    Http::fake([
        '*' . mockApiPath() => Http::response([
            'total' => 1,
            'per_page' => 15,
            'current_page' => 1,
            'last_page' => 1,
            'data' => [
                [
                    'id' => 999,
                    'name' => 'Dwayne Johnson',
                    'birthday' => new \DateTime('1972-05-02')
                ]
            ]
        ]),
    ]);

    expect(Celebrity::where('name', 'Dwayne Johnson')->first()->birthday)->toBe('1972-05-02 00:00:00');
});

it('sets custom cache ttl', function () {
    Config::set('remote-models.cache-ttl', '1d');

    Http::fake(mockDefaultHttpResponse());

    expect(Celebrity::where('name', 'Dwayne Johnson')->first()->id)->toBe(999);

    // Should have cached
    $cacheName = (new Celebrity())->remoteModelCacheFileName();
    expect(cache()->get($cacheName))->not->toBeNull();

    // Time travel to invalidate cache.
    $this->travel(1)->days();
    expect(cache()->get($cacheName))->toBeNull();
});

it('has custom schema', function () {
    Http::fake([
        '*' . mockApiPath() => Http::response([
            'total' => 1,
            'per_page' => 15,
            'current_page' => 1,
            'last_page' => 1,
            'data' => [
                [
                    'id' => 999,
                    'name' => 'Dwayne Johnson',
                    'birthday' => '1972-05-02',
                ]
            ]
        ]),
    ]);

    $celebrity = CelebrityWithSchema::find(999)->first();

    expect($celebrity->name)->toBe('Dwayne Johnson')
        ->and($celebrity->birthday)->toBe('1972-05-02 00:00:00');
});

it('ignores columns not in custom schema', function () {
    Http::fake([
        '*' . mockApiPath() => Http::response([
            'total' => 1,
            'per_page' => 15,
            'current_page' => 1,
            'last_page' => 1,
            'data' => [
                [
                    'id' => 999,
                    'name' => 'Dwayne Johnson',
                    'birthday' => '1972-05-02',
                    'best_movie' => 'Jumanji'
                ]
            ]
        ]),
    ]);

    $bestMovie = CelebrityWithSchema::where('name', 'Dwayne Johnson')->first()->best_movie;

    expect($bestMovie)->toBeNull()
        ->and($bestMovie)->not->toBe('Jumanji');
});

it('fails on API call', function () {
    Http::fake([
        '*' . mockApiPath() => Http::response(status: 500),
    ]);

    expect(Celebrity::where('name', 'Dwayne Johnson')->first())->toBeNull();
})->throws(\Exception::class, 'Access to Remote Model `$remoteEndpoint` failed.');

it('fails on empty API data', function () {
    Http::fake([
        '*' . mockApiPath() => Http::response([
            'total' => 0,
            'per_page' => 15,
            'current_page' => 1,
            'last_page' => 1,
            'data' => []
        ]),
    ]);

    expect(Celebrity::where('name', 'Dwayne Johnson')->first())->toBeNull();
})->throws(\Exception::class, 'No data returned from Remote Model `$remoteEndpoint`.');

it('uses memory if the cache directory is not writeable or not found', function () {
    Http::fake(mockDefaultHttpResponse());

    config(['remote-models.cache-path' => $path = __DIR__ . '/non-existant-path']);

    $count = Celebrity::count();

    expect(\file_exists($path))->toBeFalse()
        ->and($count)->toBe(1)
        ->and((new Celebrity)->getConnection()->getDatabaseName())->toBe(':memory:');
});

it('caches sqlite file if storage cache folder is available', function () {
    Http::fake(mockDefaultHttpResponse());

    $count = Celebrity::count();

    expect(\file_exists(__DIR__ . '/cache'))->toBeTrue()
        ->and($count)->toBe(1);
});

it('uses same cache between requests', function () {
    // TODO
})->skip();

it('loads data from a custom remote model setup', function () {
    Http::fake([
        '*' => Http::response([
            [
                'id' => 999,
                'name' => 'Dwayne Johnson',
            ]
        ]),
    ]);

    expect(CelebrityCustomModel::where('name', 'Dwayne Johnson')->first()->id)->toBe(999);
});
