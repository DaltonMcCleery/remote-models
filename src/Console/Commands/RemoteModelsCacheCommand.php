<?php

namespace RemoteModels\Console\Commands;

use Illuminate\Console\Command;

class RemoteModelsCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remote-models:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-cache all Remote Models by setting up and loading their data all at once.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        foreach (config('remote-models.remote-models-namespaces', []) as $namespace) {
            // TODO
        }
    }
}
