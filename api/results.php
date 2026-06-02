<?php

require __DIR__ . '/supabase.php';
require __DIR__ . '/session-options.php';
require __DIR__ . '/session-cleanup.php';

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('Europe/Amsterdam');
brainbananas_cleanup_old_sessions();

function archive_session_if_complete(
    string $code,
    array $session,
    array $quiz,
    array $players,
    array $allAnswers
): array {
    $sessionOptions = brainbananas_read_session_options($code);
    $skippedQuestions = brainbananas_skipped_questions($sessionOptions);
    $historyDir = __DIR__ . '/../session-history';

    if (!is_dir($historyDir)) {
        mkdir($historyDir, 0755, true);
    }

    $indexPath = $historyDir . '/index.json';

    if (!file_exists($indexPath)) {
        file_put_contents($indexPath, '[]');
    }

    $index = json_decode(file_get_contents($indexPath), true);

    if (!is_array($index)) {
        $index = [];
    }

    foreach ($index as $item) {
        if (($item['session_code'] ?? '') === $code) {
            return [
                'archived' => true,
                'already_saved' => true,
                'file' => $item['file'] ?? null
            ];
        }
    }

    $totalQuestions = count($quiz['questions']);
    $countedQuestions = max(0, $totalQuestions - count($skippedQuestions));
    $playerCount = count($players);

    if ($playerCount === 0 || $totalQuestions === 0) {
        return [
            'archived' => false,
            'reason' => 'Geen leerlingen of vragen.'
        ];
    }

    foreach ($players as $player) {
        $name = $player['student_name'];
        $answeredQuestions = [];

        foreach ($allAnswers as $answer) {
            $questionIndex = intval($answer['question_index']);

            if (in_array($questionIndex, $skippedQuestions, true)) {
                continue;
            }

            if (($answer['student_name'] ?? '') === $name) {
                $answeredQuestions[$questionIndex] = true;
            }
        }

        if (count($answeredQuestions) < $countedQuestions) {
            return [
                'archived' => false,
                'reason' => 'Niet alle leerlingen hebben alle meetellende vragen beantwoord.'
            ];
        }
    }

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

            $studentResults[$name]['answers'][$index] = [
                'question_index' => $index,
                'question' => $question['question'],
                'given_answer' => null,
                'correct_answer' => $question['answers'][$correctIndex] ?? 'Onbekend',
                'is_correct' => false,
                'answered' => false,
                'uitleg' => $question['uitleg'] ?? $question['explanation'] ?? null,
                'skipped_by_teacher' => in_array($index, $skippedQuestions, true)
            ];
        }
    }

    foreach ($allAnswers as $answer) {
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
        $isCorrect = !empty($answer['is_correct']);

        $studentResults[$name]['answers'][$questionIndex] = [
            'question_index' => $questionIndex,
            'question' => $question['question'],
            'given_answer' => $question['answers'][$answerIndex] ?? 'Onbekend',
            'correct_answer' => $question['answers'][$correctIndex] ?? 'Onbekend',
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

    foreach ($studentResults as &$student) {
        $student['percentage'] = $countedQuestions > 0
            ? round(($student['correct'] / $countedQuestions) * 100)
            : 0;
    }

    unset($student);

    $quizFile = basename($session['quiz_file']);

    $serverDateForFile = date('Y-m-d_His');
    $serverDateReadable = date('Y-m-d H:i:s');
    $serverDateIso = date('c');

    $historyFile = $code . '_' . $serverDateForFile . '.json';
    $historyPath = $historyDir . '/' . $historyFile;

    $archive = [
        'metadata' => [
            'session_code' => $code,
            'quiz_file' => $quizFile,
            'quiz_title' => $quiz['title'] ?? $quizFile,
            'created_at' => $session['created_at'] ?? null,
            'archived_at' => $serverDateReadable,
            'archived_at_iso' => $serverDateIso,
            'timezone' => 'Europe/Amsterdam',
            'date_source' => 'php_server_with_dutch_timezone',
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

    $index[] = [
        'session_code' => $code,
        'quiz_file' => $quizFile,
        'quiz_title' => $quiz['title'] ?? $quizFile,
        'date' => $serverDateReadable,
        'date_iso' => $serverDateIso,
        'timezone' => 'Europe/Amsterdam',
        'date_source' => 'php_server_with_dutch_timezone',
        'file' => $historyFile,
        'student_count' => count($players),
        'question_count' => $totalQuestions,
        'counted_question_count' => $countedQuestions
    ];

    file_put_contents(
        $indexPath,
        json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    return [
        'archived' => true,
        'already_saved' => false,
        'file' => $historyFile,
        'timezone' => 'Europe/Amsterdam'
    ];
}

$code = strtoupper(trim($_GET['code'] ?? ''));

if ($code === '') {
    echo json_encode([
        'ok' => false,
        'error' => 'Sessiecode ontbreekt.'
    ]);
    exit;
}

$sessionResult = supabase_request(
    'GET',
    'brainbananas_sessions?code=eq.' . urlencode($code) . '&select=*'
);

if (!$sessionResult['ok'] || empty($sessionResult['data'])) {
    echo json_encode([
        'ok' => false,
        'error' => 'Sessie niet gevonden.'
    ]);
    exit;
}

$session = $sessionResult['data'][0];

$quizFile = basename($session['quiz_file']);
$quizPath = __DIR__ . '/../quizzes/' . $quizFile;

if (!file_exists($quizPath)) {
    echo json_encode([
        'ok' => false,
        'error' => 'Quizbestand niet gevonden.'
    ]);
    exit;
}

$quiz = json_decode(file_get_contents($quizPath), true);

if (!$quiz || !isset($quiz['questions'])) {
    echo json_encode([
        'ok' => false,
        'error' => 'Ongeldige quiz-JSON.'
    ]);
    exit;
}

$currentQuestion = intval($session['current_question']);
$totalQuestions = count($quiz['questions']);

if ($currentQuestion < 0) {
    $currentQuestion = 0;
}

$answersResult = supabase_request(
    'GET',
    'brainbananas_answers?session_code=eq.' . urlencode($code) . '&select=*'
);

$playersResult = supabase_request(
    'GET',
    'brainbananas_players?session_code=eq.' . urlencode($code) . '&select=*'
);

$allAnswers = $answersResult['data'] ?? [];
$players = $playersResult['data'] ?? [];

if ($currentQuestion >= $totalQuestions) {
    echo json_encode([
        'ok' => true,
        'session_code' => $code,
        'quiz_title' => $quiz['title'] ?? $quizFile,
        'current_question' => $currentQuestion,
        'total_questions' => $totalQuestions,
        'quiz_finished' => true,
        'player_count' => count($players),
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => 'Europe/Amsterdam'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$question = $quiz['questions'][$currentQuestion];

$currentQuestionAnswers = array_filter($allAnswers, function ($answer) use ($currentQuestion) {
    return intval($answer['question_index']) === $currentQuestion;
});

$correctIndex = intval($question['correct']);
$correctAnswer = $question['answers'][$correctIndex] ?? 'Onbekend';

$answerRows = [];

foreach ($players as $player) {
    $studentName = $player['student_name'];
    $studentAnswer = null;

    foreach ($currentQuestionAnswers as $answer) {
        if (($answer['student_name'] ?? '') === $studentName) {
            $studentAnswer = $answer;
            break;
        }
    }

    if ($studentAnswer) {
        $answerIndex = intval($studentAnswer['answer_index']);

        $answerRows[] = [
            'student_name' => $studentName,
            'status' => 'answered',
            'given_answer' => $question['answers'][$answerIndex] ?? 'Onbekend',
            'correct_answer' => $correctAnswer,
            'is_correct' => !empty($studentAnswer['is_correct']),
            'answered_at' => $studentAnswer['created_at'] ?? ''
        ];
    } else {
        $answerRows[] = [
            'student_name' => $studentName,
            'status' => 'waiting',
            'given_answer' => '',
            'correct_answer' => $correctAnswer,
            'is_correct' => null,
            'answered_at' => ''
        ];
    }
}

$archiveStatus = archive_session_if_complete(
    $code,
    $session,
    $quiz,
    $players,
    $allAnswers
);

echo json_encode([
    'ok' => true,
    'session_code' => $code,
    'quiz_title' => $quiz['title'] ?? $quizFile,
    'current_question' => $currentQuestion,
    'total_questions' => $totalQuestions,
    'quiz_finished' => false,
    'question' => $question,
    'answer_rows' => $answerRows,
    'answered_count' => count($currentQuestionAnswers),
    'player_count' => count($players),
    'is_last_question' => $currentQuestion >= $totalQuestions - 1,
    'server_time' => date('Y-m-d H:i:s'),
    'timezone' => 'Europe/Amsterdam',
    'archive' => $archiveStatus
], JSON_UNESCAPED_UNICODE);
