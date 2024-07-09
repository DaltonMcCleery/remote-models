<?php
namespace Tests;

use RemoteModels\RemoteModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('loads data via pagination', function () {
    Http::fake([
        'yourdomain.com/api/celebrities*' => Http::sequence()
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

it('adds domain to config', function () {
    Config::set('remote-models.domain', 'https://yourdomain.com/');

    Http::fake(mockDefaultHttpResponse());

    expect(WithDomain::where('name', 'Dwayne Johnson')->first()->id)->toBe(999);
});

it('loads user from plain API', function () {
    Http::fake([
        'yourdomain.com/api/celebrities*' => Http::response([
            [
                'id' => 999,
                'name' => 'Dwayne Johnson',
            ]
        ]),
    ]);

    expect(Celebrity::where('name', 'Dwayne Johnson')->first()->id)->toBe(999)
        ->and(Celebrity::where('name', 'The Rock')->first())->toBeNull();
});

it('fails with no endpoint', function () {
    expect(NoEndpoint::where('name', 'Dwayne Johnson')->first())->toBeNull();
})->throws(\Exception::class, 'Remote Model property `$endpoint` cannot be empty.');

it('fails on API call', function () {
    Http::fake([
        'yourdomain.com/api/celebrities*' => Http::response(status: 500),
    ]);

    expect(Celebrity::where('name', 'Dwayne Johnson')->first())->toBeNull();
})->throws(\Exception::class, 'Access to Remote Model `$endpoint` failed.');

it('fails on empty API data', function () {
    Http::fake([
        'yourdomain.com/api/celebrities*' => Http::response([
            'total' => 0,
            'per_page' => 15,
            'current_page' => 1,
            'last_page' => 1,
            'data' => []
        ]),
    ]);

    expect(Celebrity::where('name', 'Dwayne Johnson')->first())->toBeNull();
})->throws(\Exception::class, 'No data returned from Remote Model `$endpoint`.');

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


class Celebrity extends Model {
    use RemoteModel;

    protected $endpoint = 'https://yourdomain.com/api/celebrities';
}

class WithDomain extends Model {
    use RemoteModel;

    protected $endpoint = '/api/celebrities';
}

class NoEndpoint extends Model {
    use RemoteModel;
}
