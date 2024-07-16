# Remote Models

Sometimes you want to use Eloquent, but that data is in another database on a different application.

This package is used for both the "host" application and the "remote" application. Both use cases are detailed below, or 
you can look at [these examples](EXAMPLES.md).

## Requirements

The [`pdo-sqlite` PHP extension](https://www.php.net/manual/en/ref.pdo-sqlite.php) must be installed on your system to use this package.

You will need to set up any endpoints on the host application where the data is stored.

## Install
```
composer require daltonmccleery/remote-models
```

### Publishing the Config

It is recommended to publish the config file; this will allow you to add a host domain, API pathing, and any Models.

```console
php artisan vendor:publish --provider="RemoteModels\RemoteModelsServiceProvider" --force
```

## Remote Use

Using this package consists of two steps:
1. Add the `RemoteModel` trait to a model.

That's it.

```php
class Celebrity extends Model
{
    use \RemoteModels\RemoteModel;
}
```

Now, you can use this model anywhere you like, and it will behave as if the table exists in your application.
```php
$celebrity = Celebrity::where('name', 'Dwayne Johnson')->first();
```

This will allow you to add a host domain, API pathing, and any Models.

### Custom Endpoint

You may provide a custom endpoint to your Remote Model that will be called when the model is loaded. If you do this and
have this package installed on your host application, you will need to create your own API endpoint.

```php
class Celebrity extends Model
{
    use \RemoteModels\RemoteModel;

    protected $remoteEndpoint = '/v1/celebrities';
}
```

### Custom Schema

Remote Models will auto-discover the schema from the first API call, however, if you want more control over the schema or
what fields are saved locally, you may add them to a `$schema` property with a type cast for the column.

```php
class Celebrity extends Model
{
    use \RemoteModels\RemoteModel;

    protected $remoteSchema = [
        'name' => 'string',
        'birthday' => 'datetime' 
    ];
}
```

## Host Use

This package is dependent on a separate Laravel application that "hosts" the Models and their data. You will need to either
install this package on the host application as well and enter the "remote" models you wish to expose to the "remote" application.

```php
// config/remote-models.php
'host-models' => [
    \App\Models\Celebrity::class
]
```

### Custom Endpoint

If you use a custom endpoint on the remote application, you will need to set up that custom route. You can use the following as an example:

```php
Route::post(
    config('remote-models.api-path') . '/v1/celebrities',
    fn (\RemoteModels\Http\Requests\CustomRemoteModelRequest $request) => $request->returnRemoteModels(Celebrity::class)
);
```

If you do **not** install this package on the "host" application, a custom endpoint will need to be set up for each Remote Model
you plan on using. You will also need to manually validate the given API Key. You can use the following as an example:

```php
Route::post('/api/_remote/_models/v1/celebrities', fn () => response()->json(Celebrity::paginate())));
```

You are not required to install this package on the "host" application. If you don't, you will need to set up your own API
endpoints for the models you wish to use. It is recommended to use Laravel's default [pagination](https://laravel.com/docs/11.x/pagination#paginating-eloquent-results).

## Cache Interval

You can set how long to cache the remote data for using the `cache-ttl` config option. These values follow the standard 
[DateTime Interval](https://www.php.net/manual/en/class.dateinterval.php properties. Below are a few examples:

```php
// config/remote-models.php
'cache-ttl' => '1m | 1w | 1d | 1h' // 1 month | 1 week | 1 day | 1 hour
```

## External Host Data

There may be instances where you do not control the "host" application, i.e. it could be a Google Spreadsheet or a 3rd 
party API, but would still like a way to query that data using Eloquent. 

You'll need to have your Model implement the `RemoteModelInterface` and include the base `RemoteModelManagement` trait.
This will have you implement 2 methods for setting up a custom schema (optional) and recursively fetching the data. In this instance, 
the `$remoteEndpoint` is optional.

You can create your own Remote Model by following this example:

```php
class Celebrity extends Model implements \RemoteModels\Interfaces\RemoteModelInterface
{
    use \RemoteModels\RemoteModelManagement;

    public function migrate(): void
    {
        $this->createRemoteModelTable(schemaCallback: function (array $schema) {
        
            // Make any modifications to the column schema before the sqlite table is created.

            return $schema;
        });

        $this->loadRemoteModelData();
    }
    
    public function loadRemoteModelData(int $page = 1): void
    {
        // Normal operation is a POST request with the config API key,
        // but you are free to modify the API call as you like.
        $response = \Illuminate\Support\Facades\Http::get($this->getRemoteModelEndpoint());
        
        $data = $response->json();

        // `insertRemoteModelData` is available and takes an array of data to be inserted.
        $this->insertRemoteModelData($data['data'], $data['per_page'] ?? 15);

        // Call the next page, if available.
        if ((int) $data['current_page'] < (int) $data['last_page']) {
            $this->loadRemoteModelData((int) $data['current_page'] + 1);
        }
    }
}
```

## How It Works

When a Model is called, it will make an API call to the either a custom endpoint or to a predefined endpoint using the Model's
name. This predefined API endpoint can be configured in the config file.

Under the hood, this package creates and caches a SQLite database just for this model and all its data.
It creates a table and populates it with the returned, paginated API data.
If, for whatever reason, it can't cache a .sqlite file, it will default to using an in-memory sqlite database.

This package was _heavily_ inspired by Caleb Porzio's [Sushi](https://github.com/calebporzio/sushi) package.

## Upcoming Features

- Add command for pre-caching all Remote Models for deployment.
- Add local database fallback
- Add support for other data sources (Spreadsheets, external APIs, etc)
