<?php
session_start();

require __DIR__ . '/supabase.php';
require __DIR__ . '/session-options.php';
require __DIR__ . '/archive-helper.php';

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
    'brainbananas_sessions?code=eq.' . urlencode($code) . '&select=*'
);

if (!$sessionResult['ok'] || empty($sessionResult['data'])) {
    die('Sessie niet gevonden.');
}

$session = $sessionResult['data'][0];
$sessionOptions = brainbananas_read_session_options($code);
$selfPaced = !empty($sessionOptions['self_paced']);

$quizFile = basename($session['quiz_file']);
$quizPath = __DIR__ . '/../quizzes/' . $quizFile;

if (!file_exists($quizPath)) {
    die('Quizbestand niet gevonden.');
}

$quiz = json_decode(file_get_contents($quizPath), true);

if (!isset($quiz['questions'][$questionIndex]['answers'][$answerIndex])) {
    die('Ongeldig antwoord.');
}

$sessionStatus = $session['status'] ?? '';
$currentQuestion = intval($session['current_question']);
$isLateFinalAnswer =
    !$selfPaced &&
    $sessionStatus === 'finished' &&
    $currentQuestion >= count($quiz['questions']) &&
    $questionIndex === count($quiz['questions']) - 1;

if ($sessionStatus !== 'active' && !$isLateFinalAnswer) {
    die('Deze sessie is niet meer actief.');
}

if (!$selfPaced && $sessionStatus === 'active' && $currentQuestion !== $questionIndex) {
    die('Deze vraag is niet meer actief.');
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

if ($isLateFinalAnswer) {
    $archiveResult = brainbananas_archive_session($code);

    if (!$archiveResult['ok']) {
        die('Je antwoord is opgeslagen, maar de sessiegeschiedenis kon niet worden bijgewerkt.');
    }
}

if ($selfPaced) {
    $playersResult = supabase_request(
        'GET',
        'brainbananas_players?session_code=eq.' . urlencode($code) . '&select=student_name'
    );
    $answersResult = supabase_request(
        'GET',
        'brainbananas_answers?session_code=eq.' . urlencode($code) .
        '&select=student_name,question_index'
    );

    if ($playersResult['ok'] && $answersResult['ok'] && !empty($playersResult['data'])) {
        $answeredByStudent = [];

        foreach (($answersResult['data'] ?? []) as $answer) {
            $answeredByStudent[$answer['student_name']][intval($answer['question_index'])] = true;
        }

        $allStudentsComplete = true;

        foreach ($playersResult['data'] as $player) {
            $studentName = $player['student_name'];

            if (count($answeredByStudent[$studentName] ?? []) < count($quiz['questions'])) {
                $allStudentsComplete = false;
                break;
            }
        }

        if ($allStudentsComplete) {
            brainbananas_archive_session($code);
        }
    }
}

header('Location: ../quiz.php' . ($selfPaced ? '?question=' . $questionIndex : ''));
exit;
