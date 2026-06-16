<?php

require_once __DIR__ . '/includes/theme.php';
require_once __DIR__ . '/includes/teacher-auth.php';
require __DIR__ . '/api/supabase.php';
require __DIR__ . '/api/session-cleanup.php';

brainbananas_require_teacher_auth();

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function brainbananas_comment_board_session_exists(string $code): bool
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

$code = strtoupper(trim((string)($_GET['code'] ?? '')));

if ($code === '') {
    die('Geen sessiecode.');
}

$quizSessionResult = supabase_request(
    'GET',
    'brainbananas_sessions?code=eq.' . urlencode($code) . '&select=code,status'
);

if (!brainbananas_comment_board_session_exists($code)) {
    die('Sessie niet gevonden.');
}

$hasQuizSession = $quizSessionResult['ok'] && !empty($quizSessionResult['data']);

?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>BrainBananas Reactiebord</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="tabler/core/dist/css/tabler.min.css" rel="stylesheet">
    <?php brainbananas_theme_head(); ?>
    <style>
        .comment-note {
            height: 100%;
            min-height: 128px;
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
            font-size: 1.1rem;
            line-height: 1.45;
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

        <div class="text-center mb-4">
            <h1 class="display-5">🍌 BrainBananas</h1>
            <div class="text-secondary">
                Reactiebord · sessie <?= h($code) ?>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-sm-<?= $hasQuizSession ? '3' : '4' ?> g-2 mb-4">
            <div class="col">
                <a href="comment-student.php?code=<?= urlencode($code) ?>" class="btn btn-yellow w-100" target="_blank">
                    Leerlinglogin openen
                </a>
            </div>
            <div class="col">
                <a href="<?= $hasQuizSession ? 'live.php?code=' . urlencode($code) : 'teacher.php?blackboard_code=' . urlencode($code) ?>" class="btn btn-outline-secondary w-100">
                    <?= $hasQuizSession ? 'Terug naar live' : 'Terug naar leraar' ?>
                </a>
            </div>
            <div class="col">
                <form method="post" action="api/clear-comments.php" id="clear-board-form">
                    <input type="hidden" name="code" value="<?= h($code) ?>">
                    <button class="btn btn-outline-danger w-100">
                        Reactiebord wissen
                    </button>
                </form>
            </div>
            <?php if (!$hasQuizSession): ?>
                <div class="col">
                    <form method="post" action="teacher.php" id="end-board-form">
                        <input type="hidden" name="teacher_action" value="end_blackboard">
                        <input type="hidden" name="blackboard_code" value="<?= h($code) ?>">
                        <button class="btn btn-outline-danger w-100">
                            Reactiebord beëindigen
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="alert alert-info">
            Leerlingen kiezen op de startpagina <strong>Reactiebord</strong> en vullen sessiecode
            <strong><?= h($code) ?></strong> in.
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title">Vraag of opmerking plaatsen</h2>
            </div>
            <div class="card-body">
                <form id="teacher-comment-form">
                    <input type="hidden" name="code" value="<?= h($code) ?>">

                    <div class="mb-3">
                        <textarea
                            class="form-control form-control-lg"
                            name="comment"
                            id="teacher-comment-input"
                            rows="3"
                            maxlength="280"
                            placeholder="Plaats een vraag of opmerking voor de klas"
                            required
                        ></textarea>
                    </div>

                    <button class="btn btn-yellow btn-lg w-100">
                        Plaatsen als leraar
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
    </div>
</div>

<script>
const boardCode = <?= json_encode($code) ?>;
const commentsGrid = document.getElementById("comments-grid");
const messageArea = document.getElementById("message-area");
const teacherCommentForm = document.getElementById("teacher-comment-form");
const teacherCommentInput = document.getElementById("teacher-comment-input");

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

teacherCommentForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(teacherCommentForm);
    const button = teacherCommentForm.querySelector("button");
    button.disabled = true;

    try {
        const response = await fetch("api/comments.php", {
            method: "POST",
            body: formData
        });
        const data = await response.json();

        if (!data.ok) {
            showMessage("danger", data.error || "Kon opmerking niet plaatsen.");
            return;
        }

        teacherCommentInput.value = "";
        await loadComments();
    } catch (error) {
        showMessage("danger", "Kon opmerking niet plaatsen.");
    } finally {
        button.disabled = false;
    }
});

document.getElementById("clear-board-form").addEventListener("submit", (event) => {
    const confirmed = window.confirm(
        "Weet je zeker dat je alle reacties op dit reactiebord wilt wissen?"
    );

    if (!confirmed) {
        event.preventDefault();
    }
});

const endBoardForm = document.getElementById("end-board-form");

if (endBoardForm) {
    endBoardForm.addEventListener("submit", (event) => {
        const confirmed = window.confirm(
            "Weet je zeker dat je dit reactiebord wilt beëindigen?"
        );

        if (!confirmed) {
            event.preventDefault();
        }
    });
}

loadComments();
setInterval(loadComments, 2500);
</script>

<script src="tabler/core/dist/js/tabler.min.js"></script>

</body>
</html>
