<?php

require __DIR__ . '/../includes/teacher-auth.php';

brainbananas_require_teacher_auth('../teacher.php', '../');

require __DIR__ . '/supabase.php';
require __DIR__ . '/session-options.php';
require __DIR__ . '/session-cleanup.php';

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('Europe/Amsterdam');
brainbananas_cleanup_old_sessions();

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
$sessionOptions = brainbananas_read_session_options($code);
$selfPaced = !empty($sessionOptions['self_paced']);

if ($selfPaced) {
    $progressRows = [];
    $completedCount = 0;

    foreach ($players as $player) {
        $studentName = $player['student_name'];
        $answeredQuestions = [];
        $correctCount = 0;

        foreach ($allAnswers as $answer) {
            if (($answer['student_name'] ?? '') !== $studentName) {
                continue;
            }

            $answeredQuestions[intval($answer['question_index'])] = true;

            if (!empty($answer['is_correct'])) {
                $correctCount++;
            }
        }

        $answeredCount = count($answeredQuestions);
        $isComplete = $answeredCount >= $totalQuestions;

        if ($isComplete) {
            $completedCount++;
        }

        $progressRows[] = [
            'student_name' => $studentName,
            'answered_count' => $answeredCount,
            'correct_count' => $correctCount,
            'is_complete' => $isComplete
        ];
    }

    echo json_encode([
        'ok' => true,
        'session_code' => $code,
        'quiz_title' => $quiz['title'] ?? $quizFile,
        'total_questions' => $totalQuestions,
        'quiz_finished' => false,
        'self_paced' => true,
        'player_count' => count($players),
        'completed_count' => $completedCount,
        'progress_rows' => $progressRows,
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => 'Europe/Amsterdam'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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
    'timezone' => 'Europe/Amsterdam'
], JSON_UNESCAPED_UNICODE);
