<?php

require __DIR__ . '/api/supabase.php';
require __DIR__ . '/api/session-options.php';
require __DIR__ . '/api/session-cleanup.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$quizzes = glob(__DIR__ . "/quizzes/*.json");
brainbananas_cleanup_old_sessions();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $quiz = basename($_POST["quiz"] ?? "");

    if ($quiz === "") {
        die("Geen quiz geselecteerd.");
    }

    $quizPath = __DIR__ . "/quizzes/" . $quiz;

    if (!file_exists($quizPath)) {
        die("Quizbestand niet gevonden.");
    }

    $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $showAnswerFeedback = isset($_POST['show_answer_feedback']);

    $result = supabase_request("POST", "brainbananas_sessions", [
        "code" => $code,
        "quiz_file" => $quiz,
        "status" => "active",
        "current_question" => 0
    ]);

    if (!$result["ok"]) {
        die("Kon sessie niet maken: " . h($result["raw"] ?? "Onbekende fout"));
    }

    brainbananas_write_session_options($code, [
        'show_answer_feedback' => $showAnswerFeedback
    ]);

    header("Location: live.php?code=" . urlencode($code));
    exit;
}

$activeCode = strtoupper(trim($_GET["code"] ?? ""));
?>

<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>BrainBananas Leraar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="tabler/core/dist/css/tabler.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-yellow-lt">

<div class="page page-center">
    <div class="container container-tight py-5">

        <div class="text-center mb-4">
            <h1 class="display-5">🍌 BrainBananas</h1>
            <div class="text-secondary">Lerarenoverzicht</div>
        </div>

        <?php if ($activeCode): ?>

            <div class="alert alert-success">
                <h2 class="alert-title">Sessie gestart</h2>

                <p class="mb-2">Geef deze code aan je leerlingen:</p>

                <div class="display-3 fw-bold mb-3">
                    <?= h($activeCode) ?>
                </div>

                <a
                    href="live.php?code=<?= urlencode($activeCode) ?>"
                    class="btn btn-yellow btn-lg w-100"
                >
                    Live overzicht openen
                </a>
            </div>

        <?php endif; ?>

        <div class="card">

            <div class="card-header">
                <h2 class="card-title">Quizsessie maken</h2>
            </div>

            <div class="card-body">

                <?php if (!$quizzes): ?>

                    <div class="alert alert-danger">
                        Geen quizzen gevonden in <strong>/quizzes</strong>.
                    </div>

                    <a href="upload-quiz.php" class="btn btn-yellow w-100">
                        Quiz JSON toevoegen
                    </a>

                    <a href="history.php" class="btn btn-outline-primary w-100">
                        Bekijk opgeslagen sessies
                    </a>

                <?php else: ?>

                    <form method="post">

                        <div class="mb-3">
                            <label class="form-label">Kies quiz</label>

                            <select name="quiz" class="form-select" required>
                                <?php foreach ($quizzes as $quizFile): ?>
                                    <?php $name = basename($quizFile); ?>

                                    <option value="<?= h($name) ?>">
                                        <?= h($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <label class="form-check mb-3">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="show_answer_feedback"
                                value="1"
                            >
                            <span class="form-check-label">
                                Leerlingen mogen na het antwoorden het juiste antwoord en de uitleg zien
                            </span>
                        </label>

                        <button class="btn btn-yellow w-100">
                            Sessie starten
                        </button>

                    </form>

                    <a href="history.php" class="btn btn-outline-primary w-100 mt-3">
                        Bekijk opgeslagen sessies
                    </a>

                    <a href="upload-quiz.php" class="btn btn-outline-secondary w-100 mt-3">
                        Quiz JSON toevoegen
                    </a>

                <?php endif; ?>

                <form method="post" action="api/git-pull.php" class="mt-3">
                    <button class="btn btn-outline-secondary w-100">
                        Update vanaf Git
                    </button>
                </form>

            </div>
        </div>

        <div class="text-center mt-4">
            <a href="index.php" class="text-secondary">
                Terug naar BrainBananas
            </a>
        </div>

    </div>
</div>

<script src="tabler/core/dist/js/tabler.min.js"></script>

</body>
</html>
