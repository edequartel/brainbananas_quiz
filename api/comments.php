<?php

session_start();

require __DIR__ . '/supabase.php';
require __DIR__ . '/session-cleanup.php';

header('Content-Type: application/json; charset=utf-8');

function brainbananas_comments_json(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function brainbananas_comments_session_exists(string $code): bool
{
    $sessionResult = supabase_request(
        'GET',
        'brainbananas_sessions?code=eq.' . urlencode($code) . '&select=code,status'
    );

    return $sessionResult['ok'] && !empty($sessionResult['data']);
}

function brainbananas_comments_limit(string $value, int $maxLength): string
{
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value) > $maxLength
            ? mb_substr($value, 0, $maxLength)
            : $value;
    }

    return strlen($value) > $maxLength
        ? substr($value, 0, $maxLength)
        : $value;
}

brainbananas_cleanup_old_sessions();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$sessionStudent = trim((string)($_SESSION['student'] ?? ''));
$sessionCode = strtoupper(trim((string)($_SESSION['code'] ?? '')));

if ($method === 'POST') {
    if ($sessionStudent === '' || $sessionCode === '') {
        brainbananas_comments_json([
            'ok' => false,
            'error' => 'Geen actieve leerlingensessie.'
        ]);
    }

    $comment = trim((string)($_POST['comment'] ?? ''));
    $comment = preg_replace('/\s+/u', ' ', $comment) ?? $comment;

    if ($comment === '') {
        brainbananas_comments_json([
            'ok' => false,
            'error' => 'Schrijf eerst een reactie.'
        ]);
    }

    $comment = brainbananas_comments_limit($comment, 280);

    if (!brainbananas_comments_session_exists($sessionCode)) {
        brainbananas_comments_json([
            'ok' => false,
            'error' => 'Sessie niet gevonden.'
        ]);
    }

    $insertResult = supabase_request('POST', 'brainbananas_comments', [
        'session_code' => $sessionCode,
        'student_name' => $sessionStudent,
        'comment_text' => $comment
    ]);

    if (!$insertResult['ok']) {
        brainbananas_comments_json([
            'ok' => false,
            'error' => 'Kon reactie niet opslaan. Controleer of supabase-comments.sql is uitgevoerd.'
        ]);
    }

    brainbananas_comments_json([
        'ok' => true,
        'comment' => $insertResult['data'][0] ?? null
    ]);
}

$code = strtoupper(trim((string)($_GET['code'] ?? $sessionCode)));

if ($code === '') {
    brainbananas_comments_json([
        'ok' => false,
        'error' => 'Sessiecode ontbreekt.'
    ]);
}

if (!brainbananas_comments_session_exists($code)) {
    brainbananas_comments_json([
        'ok' => false,
        'error' => 'Sessie niet gevonden.'
    ]);
}

$commentsResult = supabase_request(
    'GET',
    'brainbananas_comments' .
    '?session_code=eq.' . urlencode($code) .
    '&select=id,student_name,comment_text,created_at' .
    '&order=created_at.asc' .
    '&limit=120'
);

if (!$commentsResult['ok']) {
    brainbananas_comments_json([
        'ok' => false,
        'error' => 'Kon reacties niet laden. Controleer of supabase-comments.sql is uitgevoerd.'
    ]);
}

brainbananas_comments_json([
    'ok' => true,
    'session_code' => $code,
    'can_post' => $sessionCode === $code && $sessionStudent !== '',
    'student_name' => $sessionCode === $code ? $sessionStudent : '',
    'comments' => $commentsResult['data'] ?? []
]);
