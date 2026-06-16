<?php

session_start();

require_once __DIR__ . '/includes/theme.php';
require __DIR__ . '/api/supabase.php';
require __DIR__ . '/api/session-cleanup.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function brainbananas_comment_session_exists(string $code): bool
{
    $commentSessionResult = supabase_request(
        'GET',
        'brainbananas_comment_sessions?code=eq.' . urlencode($code) . '&status=eq.active&select=code'
    );

    if ($commentSessionResult['ok'] && !empty($commentSessionResult['data'])) {
        return true;
    }

    $sessionResult = supabase_request(
        'GET',
        'brainbananas_sessions?code=eq.' . urlencode($code) . '&select=code,status'
    );

    return $sessionResult['ok'] && !empty($sessionResult['data']);
}

brainbananas_cleanup_old_sessions();

$loginError = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['comment_student'], $_SESSION['comment_code']);

    header('Location: comment-student.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedStudent = trim((string)($_POST['student'] ?? ''));
    $postedCode = strtoupper(trim((string)($_POST['code'] ?? '')));

    if ($postedStudent === '' || $postedCode === '') {
        $loginError = 'Naam en sessiecode zijn verplicht.';
    } elseif (!brainbananas_comment_session_exists($postedCode)) {
        $loginError = 'Sessie niet gevonden.';
    } else {
        $_SESSION['comment_student'] = $postedStudent;
        $_SESSION['comment_code'] = $postedCode;

        header('Location: comment-student.php?code=' . urlencode($postedCode));
        exit;
    }
}

$student = trim((string)($_SESSION['comment_student'] ?? ''));
$sessionCode = strtoupper(trim((string)($_SESSION['comment_code'] ?? '')));
$requestedCode = strtoupper(trim((string)($_GET['code'] ?? '')));
$code = $requestedCode !== '' ? $requestedCode : $sessionCode;
$canPost = $student !== '' && $sessionCode !== '' && $sessionCode === $code;
$showLogin = $code === '' || !$canPost;

if ($code !== '' && !$showLogin && !brainbananas_comment_session_exists($code)) {
    die('Sessie niet gevonden.');
}

?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>BrainBananas Reactiebord Leerling</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="tabler/core/dist/css/tabler.min.css" rel="stylesheet">
    <?php brainbananas_theme_head(); ?>
    <style>
        .comment-note {
            height: 100%;
            min-height: 118px;
            border: 1px solid var(--tblr-border-color);
            border-radius: 8px;
            background: #fff;
            padding: 1rem;
        }

        .comment-note-teacher {
            border-color: var(--tblr-yellow);
            background: var(--tblr-yellow-lt);
        }

        .comment-text {
            overflow-wrap: anywhere;
            white-space: pre-wrap;
            font-size: 1.05rem;
            line-height: 1.4;
        }

        .comment-meta {
            color: var(--tblr-secondary);
            font-size: .875rem;
        }
    </style>
</head>

<body class="bg-yellow-lt">

<div class="page">
    <div class="container container-tight py-4">
        <?php brainbananas_theme_picker(); ?>

        <?php if ($showLogin): ?>
            <div class="text-center mb-4">
                <h1 class="display-5">🍌 BrainBananas</h1>
                <div class="text-secondary">
                    Reactiebord leerling
                </div>
            </div>

            <?php if ($loginError !== ''): ?>
                <div class="alert alert-danger">
                    <?= h($loginError) ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label fs-4 fw-bold">Je naam</label>
                            <input
                                type="text"
                                name="student"
                                class="form-control form-control-lg"
                                placeholder="Vul je naam in"
                                autocomplete="name"
                                required
                            >
                        </div>

                        <div class="mb-4">
                            <label class="form-label fs-4 fw-bold">Reactiebord-code</label>
                            <input
                                type="text"
                                name="code"
                                class="form-control form-control-lg text-uppercase text-center fw-bold"
                                value="<?= h($requestedCode) ?>"
                                placeholder="ABC123"
                                autocomplete="off"
                                autocapitalize="characters"
                                spellcheck="false"
                                required
                            >
                        </div>

                        <button class="btn btn-yellow btn-lg w-100">
                            Naar reactiebord
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center mb-4">
                <h1 class="display-5">🍌 BrainBananas</h1>
                <div class="text-secondary">
                    Reactiebord
                </div>
                <div class="mt-2">
                    <div class="text-secondary">
                        <?= h($student) ?> · reactiebord <?= h($code) ?>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <a href="comment-student.php?logout=1" class="btn btn-outline-secondary w-100">
                    Wissel leerling
                </a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form id="comment-form">
                        <div class="mb-3">
                            <textarea
                                class="form-control form-control-lg"
                                name="comment"
                                id="comment-input"
                                rows="3"
                                maxlength="280"
                                placeholder="Schrijf je reactie op het bord"
                                required
                            ></textarea>
                        </div>

                        <button class="btn btn-yellow btn-lg w-100">
                            Plaatsen
                        </button>
                    </form>
                </div>
            </div>

            <div id="message-area"></div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Reacties</h2>
                </div>
                <div class="card-body">
                    <div id="comments-grid" class="row g-3"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$showLogin): ?>
<script>
const boardCode = <?= json_encode($code) ?>;
const commentsGrid = document.getElementById("comments-grid");
const messageArea = document.getElementById("message-area");
const commentForm = document.getElementById("comment-form");
const commentInput = document.getElementById("comment-input");

function escapeHtml(text) {
    return String(text)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function formatTime(value) {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return "";
    }

    return date.toLocaleTimeString("nl-NL", {
        hour: "2-digit",
        minute: "2-digit"
    });
}

function showMessage(type, text) {
    messageArea.innerHTML = `<div class="alert alert-${type}">${escapeHtml(text)}</div>`;
}

function renderComments(comments) {
    if (!comments.length) {
        commentsGrid.innerHTML = `
            <div class="col-12">
                <div class="text-center py-5 text-secondary">
                    Nog geen reacties op het bord.
                </div>
            </div>
        `;
        return;
    }

    commentsGrid.innerHTML = comments.map((comment) => {
        const isTeacher = comment.author_role === "teacher";

        return `
        <article class="col-12 col-md-6 col-xl-4">
            <div class="comment-note ${isTeacher ? "comment-note-teacher" : ""}">
                <div class="comment-text mb-3">${escapeHtml(comment.comment_text || "")}</div>
                <div class="comment-meta d-flex justify-content-between gap-2">
                    <strong>
                        ${escapeHtml(comment.student_name || "Leerling")}
                        ${isTeacher ? `<span class="badge bg-yellow text-yellow-fg ms-2">Leraar</span>` : ``}
                    </strong>
                    <span>${escapeHtml(formatTime(comment.created_at))}</span>
                </div>
            </div>
        </article>
    `;
    }).join("");
}

async function loadComments() {
    try {
        const response = await fetch(
            "api/comments.php?code=" + encodeURIComponent(boardCode),
            { cache: "no-store" }
        );
        const data = await response.json();

        if (!data.ok) {
            showMessage("danger", data.error || "Kon reacties niet laden.");
            return;
        }

        messageArea.innerHTML = "";
        renderComments(data.comments || []);
    } catch (error) {
        showMessage("danger", "Kon reacties niet laden.");
    }
}

commentForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(commentForm);
    const button = commentForm.querySelector("button");
    button.disabled = true;

    try {
        const response = await fetch("api/comments.php", {
            method: "POST",
            body: formData
        });
        const data = await response.json();

        if (!data.ok) {
            showMessage("danger", data.error || "Kon reactie niet plaatsen.");
            return;
        }

        commentInput.value = "";
        await loadComments();
    } catch (error) {
        showMessage("danger", "Kon reactie niet plaatsen.");
    } finally {
        button.disabled = false;
    }
});

loadComments();
setInterval(loadComments, 2500);
</script>
<?php endif; ?>

<script src="tabler/core/dist/js/tabler.min.js"></script>

</body>
</html>
