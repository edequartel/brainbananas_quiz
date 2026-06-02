<?php

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalize_quiz_filename(string $filename): string
{
    $filename = trim($filename);
    $filename = preg_replace('/\s+/', '_', $filename);

    if ($filename !== '' && !str_ends_with(strtolower($filename), '.json')) {
        $filename .= '.json';
    }

    return basename($filename);
}

function is_valid_quiz_filename(string $filename): bool
{
    return preg_match('/^[a-zA-Z0-9._-]+\.json$/', $filename) === 1;
}

$quizDir = __DIR__ . '/quizzes';
$file = normalize_quiz_filename((string)($_GET['file'] ?? $_POST['file'] ?? ''));
$message = '';
$messageType = 'success';

if (!is_valid_quiz_filename($file)) {
    die('Ongeldige bestandsnaam.');
}

$quizPath = $quizDir . '/' . $file;

if (!file_exists($quizPath)) {
    die('Quizbestand niet gevonden.');
}

$quiz = json_decode(file_get_contents($quizPath), true);

if (!is_array($quiz) || !isset($quiz['questions']) || !is_array($quiz['questions'])) {
    die('Ongeldige quiz-JSON.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $quiz['title'] = trim((string)($_POST['title'] ?? $quiz['title'] ?? ''));
    $postedQuestions = $_POST['questions'] ?? [];

    if (!is_array($postedQuestions) || count($postedQuestions) === 0) {
        $message = 'Er moet minimaal één vraag zijn.';
        $messageType = 'danger';
    } else {
        $updatedQuestions = [];
        $hasError = false;

        foreach ($postedQuestions as $index => $postedQuestion) {
            $originalQuestion = $quiz['questions'][$index] ?? [];
            $questionText = trim((string)($postedQuestion['question'] ?? ''));
            $explanation = trim((string)($postedQuestion['uitleg'] ?? ''));
            $answers = $postedQuestion['answers'] ?? [];
            $correct = intval($postedQuestion['correct'] ?? -1);

            if ($questionText === '') {
                $message = 'Vraag ' . ($index + 1) . ' heeft geen vraagtekst.';
                $messageType = 'danger';
                $hasError = true;
                break;
            }

            if (!is_array($answers)) {
                $answers = [];
            }

            $answers = array_values(array_map(function ($answer) {
                return trim((string)$answer);
            }, $answers));

            $answers = array_values(array_filter($answers, function ($answer) {
                return $answer !== '';
            }));

            if (count($answers) < 2) {
                $message = 'Vraag ' . ($index + 1) . ' moet minimaal twee antwoorden hebben.';
                $messageType = 'danger';
                $hasError = true;
                break;
            }

            if (!array_key_exists($correct, $answers)) {
                $message = 'Vraag ' . ($index + 1) . ' heeft geen geldig juist antwoord.';
                $messageType = 'danger';
                $hasError = true;
                break;
            }

            $updatedQuestion = is_array($originalQuestion) ? $originalQuestion : [];
            $updatedQuestion['question'] = $questionText;
            $updatedQuestion['answers'] = $answers;
            $updatedQuestion['correct'] = $correct;

            if ($explanation !== '') {
                $updatedQuestion['uitleg'] = $explanation;
            } else {
                unset($updatedQuestion['uitleg'], $updatedQuestion['explanation']);
            }

            $updatedQuestions[] = $updatedQuestion;
        }

        if (!$hasError) {
            $quiz['questions'] = $updatedQuestions;

            file_put_contents(
                $quizPath,
                json_encode($quiz, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $message = 'Quiz opgeslagen.';
            $messageType = 'success';
        }
    }
}

?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Quiz bewerken</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="tabler/core/dist/css/tabler.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-yellow-lt">

<div class="page">
    <div class="container-xl py-4">

        <div class="row align-items-center mb-4">
            <div class="col">
                <h1>🍌 Quiz bewerken</h1>
                <div class="text-secondary">
                    <?= h($file) ?>
                </div>
            </div>

            <div class="col-auto">
                <a href="manage-quizzes.php" class="btn btn-outline-secondary">
                    Terug
                </a>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= h($messageType) ?>">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="file" value="<?= h($file) ?>">

            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="card-title">Algemeen</h2>
                </div>

                <div class="card-body">
                    <label class="form-label">Titel</label>
                    <input
                        type="text"
                        name="title"
                        class="form-control"
                        value="<?= h($quiz['title'] ?? '') ?>"
                    >
                </div>
            </div>

            <?php foreach ($quiz['questions'] as $questionIndex => $question): ?>
                <?php
                    $answers = array_values($question['answers'] ?? []);
                    for ($i = count($answers); $i < 4; $i++) {
                        $answers[] = '';
                    }
                    $correct = intval($question['correct'] ?? 0);
                ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="card-title">Vraag <?= h($questionIndex + 1) ?></h2>
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Vraag</label>
                            <textarea
                                name="questions[<?= h($questionIndex) ?>][question]"
                                class="form-control"
                                rows="2"
                                required
                            ><?= h($question['question'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Uitleg</label>
                            <textarea
                                name="questions[<?= h($questionIndex) ?>][uitleg]"
                                class="form-control"
                                rows="2"
                            ><?= h($question['uitleg'] ?? $question['explanation'] ?? '') ?></textarea>
                        </div>

                        <div class="row g-3">
                            <?php foreach ($answers as $answerIndex => $answer): ?>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">
                                        Antwoord <?= h($answerIndex + 1) ?>
                                    </label>

                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <input
                                                type="radio"
                                                name="questions[<?= h($questionIndex) ?>][correct]"
                                                value="<?= h($answerIndex) ?>"
                                                <?= $correct === $answerIndex ? 'checked' : '' ?>
                                            >
                                        </span>

                                        <input
                                            type="text"
                                            name="questions[<?= h($questionIndex) ?>][answers][]"
                                            class="form-control"
                                            value="<?= h($answer) ?>"
                                        >
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <button class="btn btn-yellow btn-lg w-100">
                Quiz opslaan
            </button>
        </form>

    </div>
</div>

<script src="tabler/core/dist/js/tabler.min.js"></script>

</body>
</html>
