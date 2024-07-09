# Remote Models

Sometimes you want to use Eloquent, but that data is in another database on a different application.

## Requirements

The [`pdo-sqlite` PHP extension](https://www.php.net/manual/en/ref.pdo-sqlite.php) must be installed on your system to use this package.

You will need to set up any endpoints on the host application where the data is stored.

## Install
```
composer require daltonmccleery/remote-models
```

## Use

Using this package consists of two steps:
1. Add the `RemoteModel` trait to a model.
2. Add a `$endpoint` property to the model.

That's it.

```php
class Project extends Model
{
    use \RemoteModels\RemoteModel;

    protected $endpoint = 'https://yourdomain.com/api/celebrities';
}
```

Now, you can use this model anywhere you like, and it will behave as if the table exists in your application.
```php
$celebrityId = Celebrity::where('name', 'Dwayne Johnson')->first()->id;
```

## How It Works
Under the hood, this package creates and caches a SQLite database just for this model and all its data.
It creates a table and populates it with the returned, paginated API data.
If, for whatever reason, it can't cache a .sqlite file, it will default to using an in-memory sqlite database.

This package was _heavily_ inspired by Caleb Porzio's [Sushi](https://github.com/calebporzio/sushi) package.

## Upcoming Features

- Set and control caching times.
- Custom schema for mapping columns.
- Add command for pre-caching all Remote Models for deployment.
- Add supplemental middleware for formatting API calls for the host application.
