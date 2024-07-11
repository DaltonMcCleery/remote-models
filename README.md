# Remote Models

Sometimes you want to use Eloquent, but that data is in another database on a different application.

This package is used for both the "host" application and the "remote" application. Both use cases are detailed below or 
you can look at [this example](EXAMPLE.md).

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
'host_models' => [
    \App\Models\Celebrity::class
]
```

### Custom Endpoint

If you do not install this package on the "host" application, a custom endpoint will need to be set up for each Remote Model
you plan on using. You can use the following as an example:

```php
Route::post(config('remote-models.api-path') . '/v1/celebrities', fn () => Celebrity::paginate());
```

You are not required to install this package on the "host" application. If you don't, you will need to set up your own API
endpoints for the models you wish to use. It is recommended to use Laravel's default [pagination](https://laravel.com/docs/11.x/pagination#paginating-eloquent-results).

## How It Works

When a Model is called, it will make an API call to the either a custom endpoint or to a predefined endpoint using the Model's
name. This predefined API endpoint can be configured in the config file.

Under the hood, this package creates and caches a SQLite database just for this model and all its data.
It creates a table and populates it with the returned, paginated API data.
If, for whatever reason, it can't cache a .sqlite file, it will default to using an in-memory sqlite database.

This package was _heavily_ inspired by Caleb Porzio's [Sushi](https://github.com/calebporzio/sushi) package.

## Upcoming Features

- Set and control caching times.
- Add command for pre-caching all Remote Models for deployment.
- Add local database fallback
