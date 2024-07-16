<?php

namespace RemoteModels;

use Illuminate\Support\Facades\Http;

trait RemoteModel
{
    use RemoteModelManagement;

	public function migrate(): void
    {
        $this->createRemoteModelTable(schemaCallback: function (array $schema) {
            // If no schema given, load initial data from endpoint to gather columns.
            if (\count($schema) === 0) {
                $response = $this->callRemoteModelEndpoint();

                if (\array_key_exists('data', $response)) {
                    // Paginated
                    if (\count($response['data']) > 0) {
                        $schema = $response['data'][0];
                    }
                } else if (\count($response) > 0) {
                    // Default to array of data.
                    $schema = $response[0];
                }
            }

            return $schema;
        });

        $this->loadRemoteModelData();
    }

    private function loadRemoteModelData(int $page = 1): void
    {
        $response = $this->callRemoteModelEndpoint($page);

        if (\array_key_exists('data', $response)) {
            // Paginated
            $this->insertRemoteModelData($response['data'], $response['per_page'] ?? 15);

            if (\array_key_exists('current_page', $response) && \array_key_exists('last_page', $response)) {
                // Call the next page from the paginated data.
                if ((int) $response['current_page'] < (int) $response['last_page']) {
                    $this->loadRemoteModelData((int) $response['current_page'] + 1);
                }
            }
        } else if (\count($response) > 0) {
            // Default to array of data.
            $this->insertRemoteModelData($response);
        }
    }

    public function callRemoteModelEndpoint(int $page = 1): array
    {
        $response = Http::timeout(10)->post($this->getRemoteModelEndpoint() . '?page=' . $page, [
            'model' => static::class,
            'api_key' => config('remote-models.api-key'),
        ]);

        if ($response->failed()) {
            throw new \Exception('Access to Remote Model `$remoteEndpoint` failed.');
        }

        return $response->json();
    }
}
