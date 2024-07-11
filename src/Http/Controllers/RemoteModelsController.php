<?php

namespace RemoteModels\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RemoteModelsController extends Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, string $model): JsonResponse
    {
        if (\in_array($model, config('remote-models.host_models'))) {
            return response()->json((new $model())::paginate());
        }

        return response()->json(status: 404);
    }
}
