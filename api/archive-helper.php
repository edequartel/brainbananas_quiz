<?php

function brainbananas_archive_session(string $code): array
{
    $code = strtoupper(trim($code));

    if ($code === '') {
        return ['ok' => false, 'error' => 'Sessiecode ontbreekt.'];
    }

    $sessionResult = supabase_request(
        'GET',
        'brainbananas_sessions?code=eq.' . urlencode($code) . '&select=*'
    );

    if (!$sessionResult['ok'] || empty($sessionResult['data'])) {
        return ['ok' => false, 'error' => 'Sessie niet gevonden.'];
    }

    $session = $sessionResult['data'][0];
    $sessionOptions = brainbananas_read_session_options($code);
    $skippedQuestions = brainbananas_skipped_questions($sessionOptions);

    $quizFile = basename($session['quiz_file']);
    $quizPath = __DIR__ . '/../quizzes/' . $quizFile;

    if (!file_exists($quizPath)) {
        return ['ok' => false, 'error' => 'Quizbestand niet gevonden.'];
    }

    $quiz = json_decode(file_get_contents($quizPath), true);

    if (!is_array($quiz) || !isset($quiz['questions']) || !is_array($quiz['questions'])) {
        return ['ok' => false, 'error' => 'Ongeldige quiz-JSON.'];
    }

    $playersResult = supabase_request(
        'GET',
        'brainbananas_players?session_code=eq.' . urlencode($code) . '&select=*'
    );

    $answersResult = supabase_request(
        'GET',
        'brainbananas_answers?session_code=eq.' . urlencode($code) . '&select=*'
    );

    $commentsResult = supabase_request(
        'GET',
        'brainbananas_comments' .
        '?session_code=eq.' . urlencode($code) .
        '&select=student_name,author_role,comment_text,created_at' .
        '&order=created_at.asc'
    );

    if (!$playersResult['ok'] || !$answersResult['ok']) {
        return ['ok' => false, 'error' => 'Kon niet alle leerlingen en antwoorden ophalen.'];
    }

    $players = $playersResult['data'] ?? [];
    $answers = $answersResult['data'] ?? [];
    $comments = $commentsResult['ok'] ? ($commentsResult['data'] ?? []) : [];
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
                'correct_answer' => $question['answers'][$correctIndex] ?? 'Onbekend',
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

        if (!isset($studentResults[$name]) || !isset($quiz['questions'][$questionIndex])) {
            continue;
        }

        $question = $quiz['questions'][$questionIndex];
        $correctIndex = intval($question['correct']);
        $isCorrect = !empty($answer['is_correct']);
        $isSkipped = in_array($questionIndex, $skippedQuestions, true);
        $wasAlreadyAnswered = !empty($studentResults[$name]['answers'][$questionIndex]['answered']);

        $studentResults[$name]['answers'][$questionIndex] = [
            'question_index' => $questionIndex,
            'question' => $question['question'],
            'given_answer' => $question['answers'][$answerIndex] ?? 'Onbekend',
            'correct_answer' => $question['answers'][$correctIndex] ?? 'Onbekend',
            'is_correct' => $isCorrect,
            'answered' => true,
            'answered_at' => $answer['created_at'] ?? null,
            'uitleg' => $question['uitleg'] ?? $question['explanation'] ?? null,
            'skipped_by_teacher' => $isSkipped
        ];

        if (!$isSkipped && !$wasAlreadyAnswered) {
            $studentResults[$name]['answered']++;

            if ($isCorrect) {
                $studentResults[$name]['correct']++;
            }
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

    $indexPath = $historyDir . '/index.json';

    if (!file_exists($indexPath)) {
        file_put_contents($indexPath, '[]');
    }

    $index = json_decode(file_get_contents($indexPath), true);

    if (!is_array($index)) {
        $index = [];
    }

    $existingIndex = null;
    $historyFile = null;

    foreach ($index as $itemIndex => $item) {
        if (($item['session_code'] ?? '') === $code) {
            $existingIndex = $itemIndex;
            $historyFile = basename((string)($item['file'] ?? ''));
            break;
        }
    }

    date_default_timezone_set('Europe/Amsterdam');

    if ($historyFile === '') {
        $historyFile = null;
    }

    if ($historyFile === null) {
        $historyFile = $code . '_' . date('Y-m-d_His') . '.json';
    }

    $historyPath = $historyDir . '/' . $historyFile;
    $dateReadable = date('Y-m-d H:i:s');
    $dateIso = date('c');

    $archive = [
        'metadata' => [
            'session_code' => $code,
            'quiz_file' => $quizFile,
            'quiz_title' => $quiz['title'] ?? $quizFile,
            'created_at' => $session['created_at'] ?? null,
            'archived_at' => $dateReadable,
            'archived_at_iso' => $dateIso,
            'timezone' => 'Europe/Amsterdam',
            'date_source' => 'php_server_with_dutch_timezone',
            'student_count' => count($players),
            'question_count' => $totalQuestions,
            'counted_question_count' => $countedQuestions,
            'skipped_questions' => $skippedQuestions,
            'show_answer_feedback' => !empty($sessionOptions['show_answer_feedback']),
            'self_paced' => !empty($sessionOptions['self_paced'])
        ],
        'quiz' => $quiz,
        'students' => array_values($studentResults),
        'comments' => $comments
    ];

    $archiveWritten = file_put_contents(
        $historyPath,
        json_encode($archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    if ($archiveWritten === false) {
        return ['ok' => false, 'error' => 'Kon sessiebestand niet opslaan.'];
    }

    $indexItem = [
        'session_code' => $code,
        'quiz_file' => $quizFile,
        'quiz_title' => $quiz['title'] ?? $quizFile,
        'date' => $dateReadable,
        'date_iso' => $dateIso,
        'timezone' => 'Europe/Amsterdam',
        'date_source' => 'php_server_with_dutch_timezone',
        'file' => $historyFile,
        'student_count' => count($players),
        'question_count' => $totalQuestions,
        'counted_question_count' => $countedQuestions
    ];

    if ($existingIndex === null) {
        $index[] = $indexItem;
    } else {
        $index[$existingIndex] = $indexItem;
    }

    $indexWritten = file_put_contents(
        $indexPath,
        json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    if ($indexWritten === false) {
        return ['ok' => false, 'error' => 'Kon sessie-index niet opslaan.'];
    }

    return [
        'ok' => true,
        'already_saved' => $existingIndex !== null,
        'updated' => $existingIndex !== null,
        'file' => $historyFile
    ];
}
