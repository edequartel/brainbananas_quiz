<?php

require_once __DIR__ . '/includes/theme.php';
require_once __DIR__ . '/includes/teacher-auth.php';
require_once __DIR__ . '/includes/pdf.php';

brainbananas_require_teacher_auth();

require __DIR__ . '/api/supabase.php';
require __DIR__ . '/api/session-options.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function grade_from_percentage($percentage): string
{
    $grade = 1 + ((float)$percentage / 100 * 9);
    return number_format(round($grade, 1), 1, ',', '');
}

$code = strtoupper(trim($_GET['code'] ?? ''));

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
$quizPath = __DIR__ . '/quizzes/' . $quizFile;

if (!file_exists($quizPath)) {
    die('Quizbestand niet gevonden.');
}

$quiz = json_decode(file_get_contents($quizPath), true);
$questions = $quiz['questions'];
$totalQuestions = count($questions);
$sessionOptions = brainbananas_read_session_options($code);
$teacherSkippedQuestions = brainbananas_skipped_questions($sessionOptions);
$countedQuestions = max(0, $totalQuestions - count($teacherSkippedQuestions));

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

$studentResults = [];

foreach ($players as $player) {

    $studentName = $player['student_name'];

    if (!isset($studentResults[$studentName])) {
        $studentResults[$studentName] = [
            'student_name' => $studentName,
            'correct' => 0,
            'answered' => 0,
            'percentage' => 0,
            'answers' => []
        ];
    }

    foreach ($questions as $index => $question) {
        $studentResults[$studentName]['answers'][$index] = [
            'question' => $question['question'],
            'given_answer' => '-',
            'correct_answer' => $question['answers'][$question['correct']],
            'is_correct' => false,
            'answered' => false
        ];
    }
}

foreach ($answers as $answer) {

    $studentName = $answer['student_name'];
    $questionIndex = intval($answer['question_index']);
    $answerIndex = intval($answer['answer_index']);

    if (in_array($questionIndex, $teacherSkippedQuestions, true)) {
        continue;
    }

    if (!isset($studentResults[$studentName])) {
        continue;
    }

    $question = $questions[$questionIndex] ?? null;

    if (!$question) {
        continue;
    }

    $givenAnswer = $question['answers'][$answerIndex] ?? 'Onbekend';
    $correctAnswer = $question['answers'][$question['correct']] ?? 'Onbekend';
    $isCorrect = !empty($answer['is_correct']);

    $studentResults[$studentName]['answers'][$questionIndex] = [
        'question' => $question['question'],
        'given_answer' => $givenAnswer,
        'correct_answer' => $correctAnswer,
        'is_correct' => $isCorrect,
        'answered' => true
    ];

    $studentResults[$studentName]['answered']++;

    if ($isCorrect) {
        $studentResults[$studentName]['correct']++;
    }
}

foreach ($studentResults as &$result) {
    $result['percentage'] = $countedQuestions > 0
        ? round(($result['correct'] / $countedQuestions) * 100)
        : 0;
}

unset($result);

usort($studentResults, function ($a, $b) {
    return $b['correct'] <=> $a['correct'];
});

$classAverage = 0;

if (count($studentResults) > 0) {
    $totalPercent = array_sum(array_column($studentResults, 'percentage'));
    $classAverage = round($totalPercent / count($studentResults));
}

if (isset($_GET['pdf'])) {
    ob_start();
    ?>
    <h1>BrainBananas Rapport</h1>
    <div class="muted">
        Sessie <?= h($code) ?> · <?= h($quiz['title'] ?? $quizFile) ?>
    </div>

    <table class="summary">
        <tr>
            <td><span class="value"><?= count($studentResults) ?></span>Leerlingen</td>
            <td><span class="value"><?= $countedQuestions ?></span>Meetellende vragen</td>
            <td><span class="value"><?= $classAverage ?>%</span>Klasgemiddelde</td>
        </tr>
    </table>

    <h2>Resultaten per leerling</h2>
    <table>
        <thead>
        <tr>
            <th>Leerling</th>
            <th>Goed</th>
            <th>Beantwoord</th>
            <th>Cijfer</th>
            <th>Resultaat</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($studentResults as $result): ?>
            <tr>
                <td><?= h($result['student_name']) ?></td>
                <td><?= $result['correct'] ?> / <?= $countedQuestions ?></td>
                <td><?= $result['answered'] ?> / <?= $countedQuestions ?></td>
                <td><?= h(grade_from_percentage($result['percentage'])) ?></td>
                <td><?= $result['percentage'] ?>%</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php foreach ($studentResults as $result): ?>
        <div class="page-break"></div>
        <h2>
            <?= h($result['student_name']) ?>
            · <?= $result['correct'] ?> / <?= $countedQuestions ?>
            · cijfer <?= h(grade_from_percentage($result['percentage'])) ?>
        </h2>
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Vraag</th>
                <th>Gegeven antwoord</th>
                <th>Juiste antwoord</th>
                <th>Resultaat</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($result['answers'] as $index => $answer): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= h($answer['question']) ?></td>
                    <td><?= h($answer['given_answer']) ?></td>
                    <td><?= h($answer['correct_answer']) ?></td>
                    <td>
                        <?php if (!$answer['answered']): ?>
                            <span class="badge neutral">Niet beantwoord</span>
                        <?php elseif ($answer['is_correct']): ?>
                            <span class="badge good">Goed</span>
                        <?php else: ?>
                            <span class="badge bad">Volgende keer beter</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
    <?php
    $pdfBody = ob_get_clean();
    brainbananas_stream_pdf(
        brainbananas_pdf_document('BrainBananas Rapport', $pdfBody),
        'brainbananas-rapport-' . strtolower($code) . '.pdf'
    );
}

?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>BrainBananas Rapport</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="tabler/core/dist/css/tabler.min.css"
        rel="stylesheet"
    >
    <?php brainbananas_theme_head(); ?>
</head>

<body class="bg-yellow-lt">

<div class="page">
    <div class="container container-tight py-4">
        <?php brainbananas_theme_picker(); ?>

        <div class="row align-items-center mb-4">
            <div class="col">
                <h1>🍌 BrainBananas Rapport</h1>
                <div class="text-secondary">
                    Sessie <?= h($code) ?> · <?= h($quiz['title'] ?? $quizFile) ?>
                </div>
            </div>

            <div class="col-auto d-print-none">
                <a href="report.php?code=<?= urlencode($code) ?>&pdf=1" class="btn btn-yellow">
                    Download PDF
                </a>

                <a
                    href="live.php?code=<?= urlencode($code) ?>"
                    class="btn btn-outline-secondary"
                >
                    Terug naar live
                </a>
            </div>
        </div>

        <div class="row row-cards mb-4">

            <div class="col-sm-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="h1"><?= count($studentResults) ?></div>
                        <div class="text-secondary">Leerlingen</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="h1"><?= $countedQuestions ?></div>
                        <div class="text-secondary">Meetellende vragen</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="h1"><?= $classAverage ?>%</div>
                        <div class="text-secondary">Klasgemiddelde</div>
                    </div>
                </div>
            </div>

        </div>

        <div class="card mb-4">

            <div class="card-header">
                <h2 class="card-title">Resultaten per leerling</h2>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Leerling</th>
                        <th>Goed</th>
                        <th>Beantwoord</th>
                        <th>Cijfer</th>
                        <th>Resultaat</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($studentResults as $result): ?>
                        <tr>
                            <td class="fw-bold"><?= h($result['student_name']) ?></td>
                            <td><?= $result['correct'] ?> / <?= $countedQuestions ?></td>
                            <td><?= $result['answered'] ?> / <?= $countedQuestions ?></td>
                            <td>
                                <span class="badge bg-green text-green-fg">
                                    <?= h(grade_from_percentage($result['percentage'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-yellow text-yellow-fg">
                                    <?= $result['percentage'] ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <?php foreach ($studentResults as $result): ?>

            <div class="card mb-4">

                <div class="card-header">
                    <h2 class="card-title">
                        <?= h($result['student_name']) ?>
                        · <?= $result['correct'] ?> / <?= $countedQuestions ?>
                        · cijfer <?= h(grade_from_percentage($result['percentage'])) ?>
                        · <?= $result['percentage'] ?>%
                    </h2>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Vraag</th>
                            <th>Gegeven antwoord</th>
                            <th>Juiste antwoord</th>
                            <th>Resultaat</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($result['answers'] as $index => $answer): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= h($answer['question']) ?></td>
                                <td><?= h($answer['given_answer']) ?></td>
                                <td><?= h($answer['correct_answer']) ?></td>
                                <td>
                                    <?php if (!$answer['answered']): ?>
                                        <span class="badge bg-secondary text-secondary-fg">
                                            Niet beantwoord
                                        </span>
                                    <?php elseif ($answer['is_correct']): ?>
                                        <span class="badge bg-green text-green-fg">
                                            Goed
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-red text-red-fg">
                                            Volgende keer beter
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        <?php endforeach; ?>

    </div>
</div>

<script src="tabler/core/dist/js/tabler.min.js"></script>

</body>
</html>
