<?php

require __DIR__ . '/supabase.php';
require __DIR__ . '/session-options.php';

$code = strtoupper(trim($_POST['code'] ?? $_GET['code'] ?? ''));

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
$sessionOptions = brainbananas_read_session_options($code);
$skippedQuestions = brainbananas_skipped_questions($sessionOptions);

$quizFile = basename($session['quiz_file']);
$quizPath = __DIR__ . '/../quizzes/' . $quizFile;

if (!file_exists($quizPath)) {
    die('Quizbestand niet gevonden.');
}

$quiz = json_decode(file_get_contents($quizPath), true);

$playersResult = supabase_request(
    'GET',
    'brainbananas_players?session_code=eq.' . urlencode($code) . '&select=*'
);

$answersResult = supabase_request(
    'GET',
    'brainbananas_answers?session_code=eq.' . urlencode($code) . '&select=*'
);

$players = $playersResult['data'] ?? [];
$answers = $answersResult['data'] ?? [];

$totalQuestions = count($quiz['questions']);
$countedQuestions = max(0, $totalQuestions - count($skippedQuestions));

$studentResults = [];

foreach ($players as $player) {
    $name = $player['student_name'];

    $studentResults[$name] = [
        'student_name' => $name,
        'correct' => 0,
        'answered' => 0,
        'percentage' => 0,
        'answers' => []
    ];

    foreach ($quiz['questions'] as $index => $question) {
        $correctIndex = intval($question['correct']);

        $studentResults[$name]['answers'][] = [
            'question_index' => $index,
            'question' => $question['question'],
            'given_answer' => null,
            'correct_answer' => $question['answers'][$correctIndex],
            'is_correct' => false,
            'answered' => false,
            'uitleg' => $question['uitleg'] ?? $question['explanation'] ?? null,
            'skipped_by_teacher' => in_array($index, $skippedQuestions, true)
        ];
    }
}

foreach ($answers as $answer) {
    $name = $answer['student_name'];
    $questionIndex = intval($answer['question_index']);
    $answerIndex = intval($answer['answer_index']);

    if (!isset($studentResults[$name])) {
        continue;
    }

    if (!isset($quiz['questions'][$questionIndex])) {
        continue;
    }

    if (in_array($questionIndex, $skippedQuestions, true)) {
        continue;
    }

    $question = $quiz['questions'][$questionIndex];
    $correctIndex = intval($question['correct']);

    $givenAnswer = $question['answers'][$answerIndex] ?? 'Onbekend';
    $correctAnswer = $question['answers'][$correctIndex] ?? 'Onbekend';
    $isCorrect = !empty($answer['is_correct']);

    $studentResults[$name]['answers'][$questionIndex] = [
        'question_index' => $questionIndex,
        'question' => $question['question'],
        'given_answer' => $givenAnswer,
        'correct_answer' => $correctAnswer,
        'is_correct' => $isCorrect,
        'answered' => true,
        'answered_at' => $answer['created_at'] ?? null,
        'uitleg' => $question['uitleg'] ?? $question['explanation'] ?? null,
        'skipped_by_teacher' => false
    ];

    $studentResults[$name]['answered']++;

    if ($isCorrect) {
        $studentResults[$name]['correct']++;
    }
}

foreach ($studentResults as &$result) {
    $result['percentage'] = $countedQuestions > 0
        ? round(($result['correct'] / $countedQuestions) * 100)
        : 0;
}

unset($result);

$historyDir = __DIR__ . '/../session-history';

if (!is_dir($historyDir)) {
    mkdir($historyDir, 0755, true);
}

$createdAt = date('Y-m-d_His');
$historyFile = $code . '_' . $createdAt . '.json';
$historyPath = $historyDir . '/' . $historyFile;

$archive = [
    'metadata' => [
        'session_code' => $code,
        'quiz_file' => $quizFile,
        'quiz_title' => $quiz['title'] ?? $quizFile,
        'created_at' => $session['created_at'] ?? null,
        'archived_at' => date('c'),
        'student_count' => count($players),
        'question_count' => $totalQuestions,
        'counted_question_count' => $countedQuestions,
        'skipped_questions' => $skippedQuestions,
        'show_answer_feedback' => !empty($sessionOptions['show_answer_feedback'])
    ],
    'quiz' => $quiz,
    'students' => array_values($studentResults)
];

file_put_contents(
    $historyPath,
    json_encode($archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

$indexPath = $historyDir . '/index.json';

if (!file_exists($indexPath)) {
    file_put_contents($indexPath, '[]');
}

$index = json_decode(file_get_contents($indexPath), true);

if (!is_array($index)) {
    $index = [];
}

$index[] = [
    'session_code' => $code,
    'quiz_file' => $quizFile,
    'quiz_title' => $quiz['title'] ?? $quizFile,
    'date' => date('Y-m-d H:i:s'),
    'file' => $historyFile,
    'student_count' => count($players),
    'question_count' => $totalQuestions,
    'counted_question_count' => $countedQuestions
];

file_put_contents(
    $indexPath,
    json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

header('Location: ../history.php');
exit;
