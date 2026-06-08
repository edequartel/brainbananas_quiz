<?php
session_start();

require __DIR__ . '/supabase.php';
require __DIR__ . '/session-options.php';

$student = $_SESSION['student'] ?? '';
$code = $_SESSION['code'] ?? '';

if ($student === '' || $code === '') {
    die('Geen actieve leerlingensessie.');
}

$questionIndex = intval($_POST['question_index'] ?? -1);
$answerIndex = intval($_POST['answer_index'] ?? -1);

if ($questionIndex < 0 || $answerIndex < 0) {
    die('Ongeldig antwoord.');
}

$sessionResult = supabase_request(
    'GET',
    'brainbananas_sessions?code=eq.' . urlencode($code) . '&status=eq.active&select=*'
);

if (!$sessionResult['ok'] || empty($sessionResult['data'])) {
    die('Sessie niet gevonden.');
}

$session = $sessionResult['data'][0];
$sessionOptions = brainbananas_read_session_options($code);
$selfPaced = !empty($sessionOptions['self_paced']);

if (!$selfPaced && intval($session['current_question']) !== $questionIndex) {
    die('Deze vraag is niet meer actief.');
}

$quizFile = basename($session['quiz_file']);
$quizPath = __DIR__ . '/../quizzes/' . $quizFile;

if (!file_exists($quizPath)) {
    die('Quizbestand niet gevonden.');
}

$quiz = json_decode(file_get_contents($quizPath), true);

if (!isset($quiz['questions'][$questionIndex]['answers'][$answerIndex])) {
    die('Ongeldig antwoord.');
}

$existingEndpoint =
    'brainbananas_answers?session_code=eq.' . urlencode($code) .
    '&student_name=eq.' . urlencode($student);

if ($selfPaced) {
    $studentAnswersResult = supabase_request(
        'GET',
        $existingEndpoint . '&select=question_index'
    );
    $answeredQuestionIndexes = [];

    foreach (($studentAnswersResult['data'] ?? []) as $answer) {
        $answeredQuestionIndexes[intval($answer['question_index'])] = true;
    }

    if (isset($answeredQuestionIndexes[$questionIndex])) {
        header('Location: ../quiz.php?question=' . $questionIndex);
        exit;
    }

    $nextQuestionIndex = count($quiz['questions']);

    foreach (array_keys($quiz['questions']) as $index) {
        if (!isset($answeredQuestionIndexes[$index])) {
            $nextQuestionIndex = $index;
            break;
        }
    }

    if ($questionIndex !== $nextQuestionIndex) {
        die('Beantwoord eerst de huidige vraag.');
    }
}

$correctIndex = intval($quiz['questions'][$questionIndex]['correct']);
$isCorrect = $answerIndex === $correctIndex;

$existing = supabase_request(
    'GET',
    $existingEndpoint .
    '&question_index=eq.' . $questionIndex .
    '&select=*'
);

if (!empty($existing['data'])) {
    header('Location: ../quiz.php');
    exit;
}

$result = supabase_request('POST', 'brainbananas_answers', [
    'session_code' => $code,
    'student_name' => $student,
    'question_index' => $questionIndex,
    'answer_index' => $answerIndex,
    'is_correct' => $isCorrect
]);

if (!$result['ok']) {
    die('Kon antwoord niet opslaan: ' . htmlspecialchars($result['raw'] ?? 'Onbekende fout'));
}

header('Location: ../quiz.php' . ($selfPaced ? '?question=' . $questionIndex : ''));
exit;
