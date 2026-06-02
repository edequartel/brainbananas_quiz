<?php

require __DIR__ . '/supabase.php';

header('Content-Type: application/json; charset=utf-8');

$config = brainbananas_config();
$url = rtrim((string)($config['SUPABASE_URL'] ?? ''), '/');
$anonKey = (string)($config['SUPABASE_ANON_KEY'] ?? '');

if ($url === '' || $anonKey === '') {
    echo json_encode([
        'ok' => false,
        'error' => 'Supabase Realtime is niet ingesteld.'
    ]);
    exit;
}

$websocketUrl = preg_replace('/^http/', 'ws', $url);

echo json_encode([
    'ok' => true,
    'websocket_url' => $websocketUrl . '/realtime/v1/websocket',
    'anon_key' => $anonKey
]);
