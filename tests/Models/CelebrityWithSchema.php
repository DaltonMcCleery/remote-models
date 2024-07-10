<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RemoteModels\RemoteModel;

class CelebrityWithSchema extends Model
{
    use RemoteModel;

    protected array $remoteSchema = [
        'name' => 'string',
        'birthday' => 'datetime',
    ];
}
