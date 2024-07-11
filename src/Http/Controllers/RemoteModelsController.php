<?php

namespace RemoteModels\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use RemoteModels\Http\Requests\RemoteModelRequest;

class RemoteModelsController extends Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(RemoteModelRequest $request): JsonResponse
    {
        return $request->returnRemoteModels();
    }
}
