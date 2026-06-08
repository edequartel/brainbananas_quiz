<?php

require_once __DIR__ . '/includes/theme.php';
require_once __DIR__ . '/includes/teacher-auth.php';
require_once __DIR__ . '/includes/pdf.php';

brainbananas_require_teacher_auth();

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

function quiz_pdf_filename(string $filename): string
{
    return preg_replace('/\.json$/i', '', $filename) . '.pdf';
}

$quizDir = __DIR__ . '/quizzes';
$message = '';
$messageType = 'success';

if (!is_dir($quizDir)) {
    mkdir($quizDir, 0755, true);
}

if (($_GET['action'] ?? '') === 'download_pdf') {
    $file = normalize_quiz_filename((string)($_GET['file'] ?? ''));
    $quizPath = $quizDir . '/' . $file;

    if (!is_valid_quiz_filename($file) || !file_exists($quizPath)) {
        die('Quizbestand niet gevonden.');
    }

    $quiz = json_decode(file_get_contents($quizPath), true);

    if (!is_array($quiz) || !isset($quiz['questions']) || !is_array($quiz['questions'])) {
        die('Ongeldige quiz-JSON.');
    }

    ob_start();
    ?>
    <h1><?= h($quiz['title'] ?? $file) ?></h1>
    <div class="muted">
        BrainBananas quiz · <?= h($file) ?>
    </div>

    <?php foreach ($quiz['questions'] as $questionIndex => $question): ?>
        <?php $answers = array_values($question['answers'] ?? []); ?>
        <h2>Vraag <?= h($questionIndex + 1) ?></h2>
        <p><?= h($question['question'] ?? '') ?></p>

        <?php if ($answers): ?>
            <table>
                <tbody>
                <?php foreach ($answers as $answerIndex => $answer): ?>
                    <tr>
                        <td style="width: 28px; font-weight: bold;">
                            <?= h(chr(65 + $answerIndex)) ?>
                        </td>
                        <td><?= h($answer) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php
    $body = ob_get_clean();
    brainbananas_stream_pdf(
        brainbananas_pdf_document((string)($quiz['title'] ?? $file), $body),
        quiz_pdf_filename($file)
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    $oldName = normalize_quiz_filename((string)($_POST['old_name'] ?? ''));
    $oldPath = $quizDir . '/' . $oldName;

    if (!is_valid_quiz_filename($oldName) || !file_exists($oldPath)) {
        $message = 'Quizbestand niet gevonden.';
        $messageType = 'danger';
    } elseif ($action === 'rename') {
        $newName = normalize_quiz_filename((string)($_POST['new_name'] ?? ''));
        $newPath = $quizDir . '/' . $newName;

        if (!is_valid_quiz_filename($newName)) {
            $message = 'Gebruik alleen letters, cijfers, spaties, punten, streepjes en underscores in de nieuwe bestandsnaam.';
            $messageType = 'danger';
        } elseif ($newName === $oldName) {
            $message = 'De nieuwe naam is hetzelfde als de oude naam.';
            $messageType = 'warning';
        } elseif (file_exists($newPath)) {
            $message = 'Er bestaat al een quiz met deze bestandsnaam.';
            $messageType = 'danger';
        } elseif (!rename($oldPath, $newPath)) {
            $message = 'Kon quizbestand niet hernoemen.';
            $messageType = 'danger';
        } else {
            $message = 'Quiz hernoemd naar ' . $newName . '.';
            $messageType = 'success';
        }
    } elseif ($action === 'delete') {
        if (!unlink($oldPath)) {
            $message = 'Kon quizbestand niet verwijderen.';
            $messageType = 'danger';
        } else {
            $message = 'Quiz verwijderd: ' . $oldName . '.';
            $messageType = 'success';
        }
    } else {
        $message = 'Onbekende actie.';
        $messageType = 'danger';
    }
}

$quizzes = glob($quizDir . '/*.json') ?: [];
sort($quizzes);
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Quizzen beheren</title>
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
                <h1>🍌 Quizzen beheren</h1>
                <div class="text-secondary">
                    JSON-bestanden in de map quizzes
                </div>
            </div>

            <div class="col-auto">
                <a href="upload-quiz.php" class="btn btn-yellow">
                    Quiz JSON toevoegen
                </a>

                <a href="teacher.php" class="btn btn-outline-secondary">
                    Terug
                </a>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= h($messageType) ?>">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body border-bottom">
                <div class="btn-group w-100" role="group" aria-label="Quizfilter">
                    <button type="button" class="btn btn-outline-secondary active" data-quiz-filter="all">
                        Alles
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-quiz-filter="BK">
                        BK
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-quiz-filter="GT">
                        GT
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-quiz-filter="H">
                        HAVO
                    </button>
                </div>
                <div class="text-secondary small mt-2" id="quiz-filter-status"></div>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Bestandsnaam</th>
                            <th>Nieuwe naam</th>
                            <th>Acties</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (!$quizzes): ?>
                        <tr>
                            <td colspan="3" class="text-center text-secondary py-4">
                                Er zijn nog geen quizbestanden.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($quizzes as $quizPath): ?>
                        <?php $name = basename($quizPath); ?>
                        <tr data-quiz-filename="<?= h(strtoupper($name)) ?>">
                            <td class="fw-bold">
                                <?= h($name) ?>
                            </td>

                            <td>
                                <form method="post" class="d-flex gap-2">
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="rename"
                                    >
                                    <input
                                        type="hidden"
                                        name="old_name"
                                        value="<?= h($name) ?>"
                                    >
                                    <input
                                        type="text"
                                        name="new_name"
                                        class="form-control"
                                        value="<?= h($name) ?>"
                                        autocomplete="off"
                                        required
                                    >
                                    <button class="btn btn-outline-primary">
                                        Hernoemen
                                    </button>
                                </form>
                            </td>

                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a
                                        href="edit-quiz.php?file=<?= urlencode($name) ?>"
                                        class="btn btn-outline-primary"
                                    >
                                        Bewerken
                                    </a>

                                    <a
                                        href="manage-quizzes.php?action=download_pdf&amp;file=<?= urlencode($name) ?>"
                                        class="btn btn-outline-secondary"
                                    >
                                        PDF
                                    </a>

                                    <form method="post">
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >
                                        <input
                                            type="hidden"
                                            name="old_name"
                                            value="<?= h($name) ?>"
                                        >
                                        <button class="btn btn-outline-danger">
                                            Verwijderen
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="d-none" id="quiz-filter-empty-row">
                            <td colspan="3" class="text-center text-secondary py-4">
                                Geen quizzen gevonden voor dit filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="tabler/core/dist/js/tabler.min.js"></script>
<script>
const quizRows = Array.from(document.querySelectorAll("[data-quiz-filename]"));
const quizFilterStatus = document.getElementById("quiz-filter-status");
const quizFilterEmptyRow = document.getElementById("quiz-filter-empty-row");

function applyQuizFilter(filter) {
    let visibleCount = 0;

    quizRows.forEach((row) => {
        const filename = row.dataset.quizFilename || "";
        const isVisible = filter === "all" || filename.startsWith(filter);

        row.classList.toggle("d-none", !isVisible);

        if (isVisible) {
            visibleCount += 1;
        }
    });

    if (quizFilterEmptyRow) {
        quizFilterEmptyRow.classList.toggle("d-none", visibleCount !== 0 || quizRows.length === 0);
    }

    if (quizFilterStatus) {
        quizFilterStatus.textContent = visibleCount === 0
            ? "Geen quizzen gevonden voor dit filter."
            : `${visibleCount} quiz${visibleCount === 1 ? "" : "zen"} gevonden.`;
    }
}

document.querySelectorAll("[data-quiz-filter]").forEach((button) => {
    button.addEventListener("click", () => {
        document.querySelectorAll("[data-quiz-filter]").forEach((otherButton) => {
            otherButton.classList.remove("active");
        });

        button.classList.add("active");
        applyQuizFilter(button.dataset.quizFilter);
    });
});

applyQuizFilter("all");
</script>

</body>
</html>
