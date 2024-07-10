<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RemoteModels\RemoteModel;

class WithCustomEndpoint extends Model {
    use RemoteModel;

    protected string $endpoint = '/custom-endpoint';
}
