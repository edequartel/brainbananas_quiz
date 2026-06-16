<?php

require_once __DIR__ . '/includes/theme.php';
require_once __DIR__ . '/includes/teacher-auth.php';

brainbananas_require_teacher_auth();

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
    $teacherAction = (string)($_POST['teacher_action'] ?? '');

    if ($teacherAction === 'start_blackboard') {
        $blackboardCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        $blackboardResult = supabase_request("POST", "brainbananas_comment_sessions", [
            "code" => $blackboardCode,
            "status" => "active"
        ]);

        if (!$blackboardResult["ok"]) {
            die("Kon reactiebord niet maken: " . h($blackboardResult["raw"] ?? "Onbekende fout"));
        }

        header("Location: teacher.php?blackboard_code=" . urlencode($blackboardCode));
        exit;
    }

    if ($teacherAction === 'end_blackboard') {
        $blackboardCode = strtoupper(trim((string)($_POST['blackboard_code'] ?? '')));

        if ($blackboardCode === '') {
            die("Reactiebord-code ontbreekt.");
        }

        $endResult = supabase_request(
            "PATCH",
            "brainbananas_comment_sessions?code=eq." . urlencode($blackboardCode),
            [
                "status" => "ended",
                "ended_at" => gmdate('c')
            ]
        );

        if (!$endResult["ok"]) {
            die("Kon reactiebord niet beëindigen: " . h($endResult["raw"] ?? "Onbekende fout"));
        }

        header("Location: teacher.php");
        exit;
    }

    if ($teacherAction === 'restart_blackboard') {
        $blackboardCode = strtoupper(trim((string)($_POST['blackboard_code'] ?? '')));

        if ($blackboardCode !== '') {
            supabase_request(
                "PATCH",
                "brainbananas_comment_sessions?code=eq." . urlencode($blackboardCode),
                [
                    "status" => "ended",
                    "ended_at" => gmdate('c')
                ]
            );
        }

        $newBlackboardCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        $newBlackboardResult = supabase_request("POST", "brainbananas_comment_sessions", [
            "code" => $newBlackboardCode,
            "status" => "active"
        ]);

        if (!$newBlackboardResult["ok"]) {
            die("Kon nieuw reactiebord niet maken: " . h($newBlackboardResult["raw"] ?? "Onbekende fout"));
        }

        header("Location: teacher.php?blackboard_code=" . urlencode($newBlackboardCode));
        exit;
    }

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
    $selfPaced = isset($_POST['self_paced']);

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
        'show_answer_feedback' => $showAnswerFeedback,
        'self_paced' => $selfPaced
    ]);

    header("Location: live.php?code=" . urlencode($code));
    exit;
}

$activeCode = strtoupper(trim($_GET["code"] ?? ""));
$activeBlackboardCode = strtoupper(trim($_GET["blackboard_code"] ?? ""));

if ($activeBlackboardCode === '') {
    $activeBlackboardResult = supabase_request(
        'GET',
        'brainbananas_comment_sessions?status=eq.active&select=*&order=created_at.desc&limit=1'
    );

    if ($activeBlackboardResult['ok'] && !empty($activeBlackboardResult['data'])) {
        $activeBlackboardCode = strtoupper((string)($activeBlackboardResult['data'][0]['code'] ?? ''));
    }
}
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
    <link
        href="tabler/icons-webfont/dist/tabler-icons.min.css"
        rel="stylesheet"
    >
    <?php brainbananas_theme_head(); ?>
</head>

<body class="bg-yellow-lt">

<div class="page">
    <div class="container container-tight py-4">
        <?php brainbananas_theme_picker(); ?>

        <div class="text-center mb-4">
            <h1 class="display-5">🍌 BrainBananas</h1>
            <div class="text-secondary">Lerarenoverzicht</div>
            <div class="mt-2">
                <span class="badge bg-secondary text-secondary-fg" id="connection-status">
                    Verbinding controleren...
                </span>
            </div>
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

        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title">Reactiebord</h2>
            </div>

            <div class="card-body">
                <?php if ($activeBlackboardCode !== ''): ?>
                    <div class="alert alert-success">
                        <h3 class="alert-title">Reactiebord actief</h3>
                        <p class="mb-2">Geef deze code aan je leerlingen:</p>
                        <div class="h2 fw-bold mb-3">
                            <?= h($activeBlackboardCode) ?>
                        </div>

                        <div class="row row-cols-1 row-cols-sm-2 g-2">
                            <div class="col">
                                <a
                                    href="comment-board.php?code=<?= urlencode($activeBlackboardCode) ?>"
                                    class="btn btn-sm btn-yellow w-100 d-inline-flex align-items-center justify-content-center gap-2"
                                    target="_blank"
                                >
                                    <i class="ti ti-message-circle" aria-hidden="true"></i>
                                    Reactiebord openen
                                </a>
                            </div>

                            <div class="col">
                                <a
                                    href="comment-student.php?code=<?= urlencode($activeBlackboardCode) ?>"
                                    class="btn btn-sm btn-outline-secondary w-100 d-inline-flex align-items-center justify-content-center gap-2"
                                    target="_blank"
                                >
                                    <i class="ti ti-user-plus" aria-hidden="true"></i>
                                    Leerlinglogin openen
                                </a>
                            </div>

                            <div class="col">
                                <form method="post" data-confirm-end-blackboard>
                                    <input type="hidden" name="teacher_action" value="end_blackboard">
                                    <input type="hidden" name="blackboard_code" value="<?= h($activeBlackboardCode) ?>">
                                    <button class="btn btn-sm btn-outline-danger w-100 d-inline-flex align-items-center justify-content-center gap-2">
                                        <i class="ti ti-player-stop" aria-hidden="true"></i>
                                        Reactiebord beëindigen
                                    </button>
                                </form>
                            </div>

                            <div class="col">
                                <form method="post" data-confirm-new-blackboard>
                                    <input type="hidden" name="teacher_action" value="restart_blackboard">
                                    <input type="hidden" name="blackboard_code" value="<?= h($activeBlackboardCode) ?>">
                                    <button class="btn btn-sm btn-outline-primary w-100 d-inline-flex align-items-center justify-content-center gap-2">
                                        <i class="ti ti-refresh" aria-hidden="true"></i>
                                        Nieuw reactiebord starten
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="teacher_action" value="start_blackboard">
                        <button class="btn btn-yellow w-100 d-inline-flex align-items-center justify-content-center gap-2">
                            <i class="ti ti-message-circle-plus" aria-hidden="true"></i>
                            Reactiebord starten
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">

            <div class="card-header">
                <h2 class="card-title">Quizsessie maken</h2>
            </div>

            <div class="card-body">

                <?php if (!$quizzes): ?>

                    <div class="alert alert-danger">
                        Geen quizzen gevonden in <strong>/quizzes</strong>.
                    </div>

                    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-2">
                        <div class="col">
                            <a href="upload-quiz.php" class="btn btn-yellow w-100 h-100">
                                Quiz JSON toevoegen
                            </a>
                        </div>

                        <div class="col">
                            <a href="manage-quizzes.php" class="btn btn-outline-secondary w-100 h-100">
                                Quizzen beheren
                            </a>
                        </div>

                        <div class="col">
                            <a href="history.php" class="btn btn-outline-primary w-100 h-100">
                                Bekijk opgeslagen sessies
                            </a>
                        </div>

                        <div class="col">
                            <form method="post" action="api/git-pull.php" class="h-100">
                                <button class="btn btn-outline-secondary w-100 h-100">
                                    Update vanaf Git
                                </button>
                            </form>
                        </div>
                    </div>

                <?php else: ?>

                    <form method="post" id="session-form">

                        <div class="mb-3">
                            <label class="form-label">Kies quiz</label>

                            <div class="btn-group w-100 mb-2" role="group" aria-label="Quizfilter">
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
                                    H
                                </button>
                            </div>

                            <select name="quiz" class="form-select" id="quiz-select" required>
                                <?php foreach ($quizzes as $quizFile): ?>
                                    <?php $name = basename($quizFile); ?>
                                    <?php $displayName = str_replace('_', ' ', pathinfo($name, PATHINFO_FILENAME)); ?>

                                    <option
                                        value="<?= h($name) ?>"
                                        data-filename="<?= h(strtoupper($name)) ?>"
                                    >
                                        <?= h($displayName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="text-secondary small mt-2" id="quiz-filter-status"></div>
                        </div>

                        <label class="form-check mb-3">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="self_paced"
                                value="1"
                            >
                            <span class="form-check-label">
                                Leerlingen werken zelfstandig en gaan zelf naar de volgende vraag
                            </span>
                        </label>

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

                    </form>

                    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-2">
                        <div class="col">
                            <button class="btn btn-yellow w-100 h-100" type="submit" form="session-form">
                                Sessie starten
                            </button>
                        </div>

                        <div class="col">
                            <a href="history.php" class="btn btn-outline-primary w-100 h-100">
                                Bekijk opgeslagen sessies
                            </a>
                        </div>

                        <div class="col">
                            <a href="upload-quiz.php" class="btn btn-outline-secondary w-100 h-100">
                                Quiz JSON toevoegen
                            </a>
                        </div>

                        <div class="col">
                            <a href="manage-quizzes.php" class="btn btn-outline-secondary w-100 h-100">
                                Quizzen beheren
                            </a>
                        </div>

                        <div class="col">
                            <form method="post" action="api/git-pull.php" class="h-100">
                                <button class="btn btn-outline-secondary w-100 h-100">
                                    Update vanaf Git
                                </button>
                            </form>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>

        <div class="text-center mt-4">
            <a href="index.php" class="text-secondary">
                Terug naar BrainBananas
            </a>
        </div>

    </div>
</div>

<script>
const connectionStatus = document.getElementById("connection-status");
const quizSelect = document.getElementById("quiz-select");
const quizFilterStatus = document.getElementById("quiz-filter-status");
const quizOptions = quizSelect
    ? Array.from(quizSelect.options).map((option) => ({
        value: option.value,
        text: option.text,
        filename: option.dataset.filename || option.value.toUpperCase()
    }))
    : [];

function applyQuizFilter(filter) {
    if (!quizSelect) {
        return;
    }

    const visibleOptions = quizOptions.filter((option) => {
        return filter === "all" || option.filename.startsWith(filter);
    });

    quizSelect.innerHTML = "";

    visibleOptions.forEach((option) => {
        const newOption = document.createElement("option");
        newOption.value = option.value;
        newOption.textContent = option.text;
        newOption.dataset.filename = option.filename;
        quizSelect.appendChild(newOption);
    });

    quizSelect.disabled = visibleOptions.length === 0;

    if (quizFilterStatus) {
        quizFilterStatus.textContent = visibleOptions.length === 0
            ? "Geen quizzen gevonden voor dit filter."
            : `${visibleOptions.length} quiz${visibleOptions.length === 1 ? "" : "zen"} gevonden.`;
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

async function checkRealtimeStatus() {
    if (!connectionStatus) {
        return;
    }

    try {
        const response = await fetch("api/realtime-config.php", { cache: "no-store" });
        const config = await response.json();

        if (config.ok) {
            connectionStatus.className = "badge bg-green text-green-fg";
            connectionStatus.textContent = "WebSocket beschikbaar";
        } else {
            connectionStatus.className = "badge bg-yellow text-yellow-fg";
            connectionStatus.textContent = "Polling actief";
        }
    } catch (error) {
        connectionStatus.className = "badge bg-yellow text-yellow-fg";
        connectionStatus.textContent = "Polling actief";
    }
}

checkRealtimeStatus();

document.addEventListener("submit", (event) => {
    if (
        event.target.matches("[data-confirm-end-blackboard]") &&
        !window.confirm("Weet je zeker dat je dit reactiebord wilt beëindigen?")
    ) {
        event.preventDefault();
        return;
    }

    if (
        event.target.matches("[data-confirm-new-blackboard]") &&
        !window.confirm("Dit beëindigt het huidige reactiebord en start een nieuw leeg reactiebord. Doorgaan?")
    ) {
        event.preventDefault();
        return;
    }

    event.target.querySelectorAll("button[type='submit'], button:not([type])")
        .forEach((button) => {
            button.disabled = true;
            button.textContent = "Even wachten...";
        });
});
</script>

<script src="tabler/core/dist/js/tabler.min.js"></script>

</body>
</html>
