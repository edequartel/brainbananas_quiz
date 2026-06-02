<?php

function brainbananas_config(): array
{
    $home = $_SERVER['HOME'] ?? getenv('HOME') ?: '';
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';

    $paths = [];

    $paths[] = __DIR__ . '/../private/supabase_brainbananas.php';

    if ($home !== '') {
        $paths[] = $home . '/private/supabase_brainbananas.php';
    }

    if ($documentRoot !== '') {
        $paths[] = dirname($documentRoot) . '/private/supabase_brainbananas.php';
    }

    $paths[] = '/home3/kydjgrmy/private/supabase_brainbananas.php';

    foreach ($paths as $path) {

        if (file_exists($path)) {
            return require $path;
        }
    }

    http_response_code(500);

    die('BrainBananas-configuratie niet gevonden.');
}

function supabase_request(
    string $method,
    string $endpoint,
    ?array $data = null
): array {

    $config = brainbananas_config();

    $url =
        rtrim($config['SUPABASE_URL'], '/') .
        '/rest/v1/' .
        ltrim($endpoint, '/');

    $headers = [

        'apikey: ' .
        $config['SUPABASE_SERVICE_ROLE_KEY'],

        'Authorization: Bearer ' .
        $config['SUPABASE_SERVICE_ROLE_KEY'],

        'Content-Type: application/json',

        'Prefer: return=representation'
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_CUSTOMREQUEST => $method,

        CURLOPT_HTTPHEADER => $headers

    ]);

    if ($data !== null) {

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($data)
        );
    }

    $response = curl_exec($ch);

    $error = curl_error($ch);

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return [

        'ok' => $status >= 200 && $status < 300,

        'status' => $status,

        'error' => $error,

        'data' => json_decode($response, true),

        'raw' => $response
    ];
}
