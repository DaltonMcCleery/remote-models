<?php

namespace RemoteModels\Interfaces;

interface RemoteModelInterface
{
    public function migrate(): void;

    public function loadRemoteModelData(int $page = 1): void;
}
