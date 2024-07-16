<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use RemoteModels\Interfaces\RemoteModelInterface;
use RemoteModels\RemoteModelManagement;

class CelebrityCustomModel extends Model implements RemoteModelInterface
{
    use RemoteModelManagement;

    // You'll need to properly set up Google Sheets API for an integration like this to work.
    protected string $remoteEndpoint = 'https://docs.google.com/spreadsheets/d/RANDOM_SHEET_ID/edit';

    protected array $remoteSchema = [
        'name' => 'string',
    ];

    public function migrate(): void
    {
        $this->createRemoteModelTable();

        $this->loadRemoteModelData();
    }

    public function loadRemoteModelData(int $page = 1): void
    {
        $response = Http::get($this->getRemoteModelEndpoint());

        $this->insertRemoteModelData($response->json());
    }
}
