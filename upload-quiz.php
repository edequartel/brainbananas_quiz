<?php

require_once __DIR__ . '/includes/theme.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function validate_quiz_json(array $quiz): array
{
    if (!isset($quiz['questions']) || !is_array($quiz['questions']) || count($quiz['questions']) === 0) {
        return ['ok' => false, 'message' => 'De JSON moet een lijst met vragen bevatten onder "questions".'];
    }

    foreach ($quiz['questions'] as $index => $question) {
        $number = $index + 1;

        if (!is_array($question)) {
            return ['ok' => false, 'message' => "Vraag {$number} is geen geldig object."];
        }

        if (trim((string)($question['question'] ?? '')) === '') {
            return ['ok' => false, 'message' => "Vraag {$number} mist het veld \"question\"."];
        }

        if (!isset($question['answers']) || !is_array($question['answers']) || count($question['answers']) === 0) {
            return ['ok' => false, 'message' => "Vraag {$number} mist een geldige lijst met antwoorden."];
        }

        if (!array_key_exists('correct', $question)) {
            return ['ok' => false, 'message' => "Vraag {$number} mist het veld \"correct\"."];
        }

        $correct = $question['correct'];

        if (!is_int($correct)) {
            return ['ok' => false, 'message' => "Vraag {$number} heeft geen numerieke index in \"correct\"."];
        }

        if (!array_key_exists($correct, $question['answers'])) {
            return ['ok' => false, 'message' => "Vraag {$number} verwijst met \"correct\" naar een antwoord dat niet bestaat."];
        }
    }

    return ['ok' => true, 'message' => ''];
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

$filename = '';
$jsonText = '';
$message = '';
$messageType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $filename = trim((string)($_POST['filename'] ?? ''));
    $jsonText = trim((string)($_POST['quiz_json'] ?? ''));

    if ($filename === '') {
        $message = 'Vul een bestandsnaam in.';
        $messageType = 'danger';
    } elseif ($jsonText === '') {
        $message = 'Plak de quiz-JSON in het tekstveld.';
        $messageType = 'danger';
    } else {
        $safeName = normalize_quiz_filename($filename);

        if (!preg_match('/^[a-zA-Z0-9._-]+\.json$/', $safeName)) {
            $message = 'Gebruik alleen letters, cijfers, spaties, punten, streepjes en underscores in de bestandsnaam.';
            $messageType = 'danger';
        } else {
            $quiz = json_decode($jsonText, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($quiz)) {
                $message = 'De geplakte tekst is geen geldige JSON: ' . json_last_error_msg();
                $messageType = 'danger';
            } else {
                $validation = validate_quiz_json($quiz);

                if (!$validation['ok']) {
                    $message = $validation['message'];
                    $messageType = 'danger';
                } else {
                    $quizDir = __DIR__ . '/quizzes';
                    $quizPath = $quizDir . '/' . $safeName;

                    if (!is_dir($quizDir)) {
                        mkdir($quizDir, 0755, true);
                    }

                    if (file_exists($quizPath)) {
                        $message = 'Er bestaat al een quiz met deze bestandsnaam.';
                        $messageType = 'danger';
                    } else {
                        file_put_contents(
                            $quizPath,
                            json_encode($quiz, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        );

                        $message = 'Quiz opgeslagen als ' . $safeName . '.';
                        $messageType = 'success';
                        $filename = '';
                        $jsonText = '';
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Quiz JSON toevoegen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="tabler/core/dist/css/tabler.min.css"
        rel="stylesheet"
    >
    <?php brainbananas_theme_head(); ?>
</head>

<body class="bg-yellow-lt">

<div class="page page-center">
    <div class="container container-tight py-5">
        <?php brainbananas_theme_picker(); ?>

        <div class="text-center mb-4">
            <h1 class="display-5">🍌 BrainBananas</h1>
            <div class="text-secondary">Quiz JSON toevoegen</div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= h($messageType) ?>">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Nieuwe quiz opslaan</h2>
            </div>

            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Bestandsnaam</label>
                        <input
                            type="text"
                            name="filename"
                            class="form-control"
                            value="<?= h($filename) ?>"
                            placeholder="mijn-quiz.json"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quiz JSON</label>
                        <textarea
                            name="quiz_json"
                            class="form-control font-monospace"
                            rows="18"
                            spellcheck="false"
                            required
                        ><?= h($jsonText) ?></textarea>
                    </div>

                    <button class="btn btn-yellow w-100">
                        Quiz opslaan
                    </button>
                </form>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="teacher.php" class="text-secondary">
                Terug naar lerarenoverzicht
            </a>
        </div>

    </div>
</div>

<script src="tabler/core/dist/js/tabler.min.js"></script>

</body>
</html>
