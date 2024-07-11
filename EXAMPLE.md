# Remote Models

Example use case.

It is important to note that this package is recommended to be installed on both the "host" application, the one that actually
holds the original Model and its data, and the "remote" application, the one that needs to reuse/share that Model's data.

- [Standard Implementation](#standard-implementation)
- [Custom Schema](#custom-schema-implementation)

---

## Standard Implementation

### Host

```env
REMOTE_MODELS_API_KEY="abcdefghijklmnopqrstuvwxyz"
```

```php
// config/remote-models.php
return [
    'host_models' => [
        \App\Models\Celebrity::class,
    ],
];
```

```php
// app/Models/Celebrity.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Celebrity extends Model
{    
    protected $fillable = [
        'name',
        'birthday',
    ];
}
```

### Remote

```env
REMOTE_MODELS_API_KEY="abcdefghijklmnopqrstuvwxyz"
```

```php
// config/remote-models.php
return [
    'domain' => 'https://host-application-domain.com', // http://127.0.0.1:8000
];
```

```php
// app/Models/Celebrity.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Celebrity extends Model
{
    use \RemoteModels\RemoteModel;
}
```
---

## Custom Schema Implementation

### Host

```env
REMOTE_MODELS_API_KEY="abcdefghijklmnopqrstuvwxyz"
```

```php
// config/remote-models.php
return [
    'host_models' => [
        \App\Models\Celebrity::class,
    ],
];
```

```php
// app/Models/Celebrity.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Celebrity extends Model
{    
    protected $fillable = [
        'name',
        'birthday',
        'stage_name',
    ];
}
```

### Remote

```env
REMOTE_MODELS_API_KEY="abcdefghijklmnopqrstuvwxyz"
```

```php
// config/remote-models.php
return [
    'domain' => 'https://host-application-domain.com', // http://127.0.0.1:8000
];
```

```php
// app/Models/Celebrity.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Celebrity extends Model
{
    use \RemoteModels\RemoteModel;    
    
    protected array $remoteSchema = [
        'name' => 'string',
        'birthday' => 'datetime',
        // stage_name not given, therefore it will not be stored on the "remote" application.
    ];
}
```