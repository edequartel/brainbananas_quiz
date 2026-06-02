<?php

function brainbananas_cleanup_old_sessions(int $maxAgeHours = 6): void
{
    $markerPath = __DIR__ . '/../session-options/.cleanup-last-run';

    if (file_exists($markerPath) && (time() - filemtime($markerPath)) < 600) {
        return;
    }

    $markerDir = dirname($markerPath);

    if (!is_dir($markerDir)) {
        mkdir($markerDir, 0755, true);
    }

    touch($markerPath);

    $threshold = gmdate('c', time() - ($maxAgeHours * 60 * 60));

    $sessionsResult = supabase_request(
        'GET',
        'brainbananas_sessions?created_at=lt.' . urlencode($threshold) . '&select=code'
    );

    if (!$sessionsResult['ok'] || empty($sessionsResult['data'])) {
        return;
    }

    foreach ($sessionsResult['data'] as $session) {
        $code = strtoupper(trim((string)($session['code'] ?? '')));

        if ($code === '') {
            continue;
        }

        supabase_request(
            'DELETE',
            'brainbananas_answers?session_code=eq.' . urlencode($code)
        );

        supabase_request(
            'DELETE',
            'brainbananas_players?session_code=eq.' . urlencode($code)
        );

        supabase_request(
            'DELETE',
            'brainbananas_sessions?code=eq.' . urlencode($code)
        );

        $optionsPath = __DIR__ . '/../session-options/' . $code . '.json';

        if (file_exists($optionsPath)) {
            unlink($optionsPath);
        }
    }
}
