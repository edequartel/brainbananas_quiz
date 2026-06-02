<?php

require __DIR__ . '/supabase.php';
require __DIR__ . '/session-cleanup.php';

header('Content-Type: application/json; charset=utf-8');

brainbananas_cleanup_old_sessions();

$code = strtoupper(trim($_GET['code'] ?? ''));

if ($code === '') {
    echo json_encode([
        'ok' => false,
        'error' => 'Sessiecode ontbreekt.'
    ]);
    exit;
}

$result = supabase_request(
    'GET',
    'brainbananas_sessions?code=eq.' . urlencode($code) . '&select=current_question,status'
);

if (!$result['ok'] || empty($result['data'])) {
    echo json_encode([
        'ok' => false,
        'error' => 'Sessie niet gevonden.'
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'current_question' => intval($result['data'][0]['current_question']),
    'status' => $result['data'][0]['status'] ?? ''
]);
