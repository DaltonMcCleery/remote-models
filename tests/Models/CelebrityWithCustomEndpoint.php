<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RemoteModels\RemoteModel;

class CelebrityWithCustomEndpoint extends Model {
    use RemoteModel;

    protected string $remoteEndpoint = '/custom-endpoint';
}
