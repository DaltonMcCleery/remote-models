<?php

namespace RemoteModels\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class CustomRemoteModelRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'api_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function returnRemoteModels(string $model): JsonResponse
    {
        if ($this->api_key !== config('remote-models.api-key')) {
            return response()->json(status: 403);
        }

        return response()->json((new $model())::paginate());
    }
}
