<?php

namespace Studio\Actions;

use Studio\Container;

class SyncAction
{
    public function __construct(private Container $c) {}

    public function launch(): never
    {
        $c = $this->c;
        $syncStatusPath = $c->dataDir . '/sync-status.json';
        $raw = is_file($syncStatusPath) ? @file_get_contents($syncStatusPath) : false;
        $currentStatus = $raw ? json_decode($raw, true) : null;
        if (($currentStatus['status'] ?? '') !== 'running') {
            file_put_contents($syncStatusPath, json_encode(['status' => 'running', 'synced' => 0, 'total' => 0]));
            $c->launcher->launchSync($syncStatusPath);
        }
        header('Location: ' . $c->baseUrl);
        exit;
    }

    public function status(): never
    {
        ini_set('display_errors', '0');
        header('Content-Type: application/json');
        $syncStatusPath = $this->c->dataDir . '/sync-status.json';
        echo is_file($syncStatusPath) ? (file_get_contents($syncStatusPath) ?: '{}') : json_encode(['status' => 'idle']);
        exit;
    }
}
