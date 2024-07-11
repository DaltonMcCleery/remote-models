<?php

use Illuminate\Support\Facades\Route;
use RemoteModels\Http\Controllers\RemoteModelsController;

Route::post(config('remote-models.api-path'), RemoteModelsController::class)
    ->name('remote-models.endpoint');
