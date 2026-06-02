<?php
require __DIR__ . '/supabase.php';
require __DIR__ . '/session-options.php';
require __DIR__ . '/session-cleanup.php';

$code = strtoupper(trim($_POST['code'] ?? ''));
$action = $_POST['action'] ?? 'next';

brainbananas_cleanup_old_sessions();

if ($code === '') {
    die('Sessiecode ontbreekt.');
}

$sessionResult = supabase_request(
    'GET',
    'brainbananas_sessions?code=eq.' . urlencode($code) . '&select=*'
);

if (!$sessionResult['ok'] || empty($sessionResult['data'])) {
    die('Sessie niet gevonden.');
}

$session = $sessionResult['data'][0];

$quizFile = basename($session['quiz_file']);
$quizPath = __DIR__ . '/../quizzes/' . $quizFile;

if (!file_exists($quizPath)) {
    die('Quizbestand niet gevonden.');
}

$quiz = json_decode(file_get_contents($quizPath), true);
$totalQuestions = count($quiz['questions']);

$currentQuestion = intval($session['current_question']);
$nextQuestion = $currentQuestion + 1;
$isFinishing = $nextQuestion >= $totalQuestions;

if ($nextQuestion > $totalQuestions) {
    $nextQuestion = $totalQuestions;
}

if ($action === 'skip' && $currentQuestion >= 0 && $currentQuestion < $totalQuestions) {
    brainbananas_skip_session_question($code, $currentQuestion);
}

$result = supabase_request(
    'PATCH',
    'brainbananas_sessions?code=eq.' . urlencode($code),
    [
        'current_question' => $nextQuestion,
        'status' => $isFinishing ? 'finished' : 'active'
    ]
);

if (!$result['ok']) {
    die('Kon vraag niet bijwerken: ' . htmlspecialchars($result['raw'] ?? 'Onbekende fout'));
}

header('Location: ../live.php?code=' . urlencode($code));
exit;
