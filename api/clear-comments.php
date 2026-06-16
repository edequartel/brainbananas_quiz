<?php

require __DIR__ . '/../includes/teacher-auth.php';
require __DIR__ . '/supabase.php';

brainbananas_require_teacher_auth('../teacher.php', '../');

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$code = strtoupper(trim((string)($_POST['code'] ?? '')));

if ($code === '') {
    die('Sessiecode ontbreekt.');
}

$result = supabase_request(
    'DELETE',
    'brainbananas_comments?session_code=eq.' . urlencode($code)
);

if (!$result['ok']) {
    die('Kon reactiebord niet wissen: ' . h($result['raw'] ?? 'Onbekende fout'));
}

header('Location: ../comment-board.php?code=' . urlencode($code));
exit;
