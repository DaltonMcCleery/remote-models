<?php

namespace RemoteModels\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class RemoteModelRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'model' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function returnRemoteModels(): JsonResponse
    {
        if ($this->api_key !== config('remote-models.api-key')) {
            return response()->json(status: 403);
        }

        if (\in_array($this->model, config('remote-models.host-models'))) {
            return response()->json((new $this->model())::paginate());
        }

        return response()->json(status: 404);
    }
}
