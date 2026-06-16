<?php

session_start();

require_once __DIR__ . '/includes/theme.php';
require __DIR__ . '/api/supabase.php';
require __DIR__ . '/api/session-cleanup.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

brainbananas_cleanup_old_sessions();

$student = trim((string)($_SESSION['student'] ?? ''));
$sessionCode = strtoupper(trim((string)($_SESSION['code'] ?? '')));
$requestedCode = strtoupper(trim((string)($_GET['code'] ?? '')));
$code = $requestedCode !== '' ? $requestedCode : $sessionCode;
$canPost = $student !== '' && $sessionCode !== '' && $sessionCode === $code;

if ($code === '') {
    header('Location: student.php');
    exit;
}

$sessionResult = supabase_request(
    'GET',
    'brainbananas_sessions?code=eq.' . urlencode($code) . '&select=code,status'
);

if (!$sessionResult['ok'] || empty($sessionResult['data'])) {
    die('Sessie niet gevonden.');
}

?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>BrainBananas Blackboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="tabler/core/dist/css/tabler.min.css"
        rel="stylesheet"
    >
    <?php brainbananas_theme_head(); ?>
    <style>
        body {
            background: #111814;
        }

        .blackboard-page {
            min-height: 100vh;
            background:
                linear-gradient(90deg, rgba(255, 255, 255, .025) 1px, transparent 1px),
                linear-gradient(rgba(255, 255, 255, .025) 1px, transparent 1px),
                #111814;
            background-size: 36px 36px;
            color: #f4f1df;
        }

        .blackboard {
            min-height: 58vh;
            border: 8px solid #8a5f34;
            border-radius: 8px;
            background:
                radial-gradient(circle at 20% 15%, rgba(255, 255, 255, .08), transparent 24%),
                linear-gradient(135deg, #183928, #10291f 55%, #0d2119);
            box-shadow:
                inset 0 0 48px rgba(0, 0, 0, .35),
                0 18px 42px rgba(0, 0, 0, .32);
            padding: 1.25rem;
        }

        .chalk-title {
            color: #f8f4db;
            font-family: Georgia, "Times New Roman", serif;
            letter-spacing: 0;
            text-shadow: 0 1px 0 rgba(255, 255, 255, .2);
        }

        .comment-note {
            height: 100%;
            min-height: 128px;
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 8px;
            background: rgba(255, 255, 255, .055);
            color: #fff9d7;
            padding: 1rem;
            box-shadow: inset 0 0 18px rgba(255, 255, 255, .035);
        }

        .comment-text {
            overflow-wrap: anywhere;
            white-space: pre-wrap;
            font-size: 1.1rem;
            line-height: 1.45;
        }

        .comment-meta {
            color: rgba(248, 244, 219, .72);
            font-size: .875rem;
        }

        .chalk-input {
            background: rgba(255, 255, 255, .94);
            border-radius: 8px;
        }
    </style>
</head>

<body>

<div class="page blackboard-page">
    <div class="container-xl py-4">
        <?php brainbananas_theme_picker(); ?>

        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-end mb-4">
            <div>
                <div class="text-secondary">
                    Sessie <?= h($code) ?>
                </div>
                <h1 class="display-5 chalk-title mb-0">
                    Reactiebord
                </h1>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <?php if ($canPost): ?>
                    <a href="quiz.php" class="btn btn-outline-light">
                        Terug naar quiz
                    </a>
                <?php else: ?>
                    <a href="live.php?code=<?= urlencode($code) ?>" class="btn btn-outline-light">
                        Terug naar live
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canPost): ?>
            <form id="comment-form" class="mb-4">
                <div class="row g-2 align-items-start">
                    <div class="col-12 col-lg">
                        <textarea
                            class="form-control form-control-lg chalk-input"
                            name="comment"
                            id="comment-input"
                            rows="2"
                            maxlength="280"
                            placeholder="Schrijf je reactie op het bord"
                            required
                        ></textarea>
                    </div>

                    <div class="col-12 col-lg-auto">
                        <button class="btn btn-yellow btn-lg w-100">
                            Plaatsen
                        </button>
                    </div>
                </div>
                <div class="text-secondary mt-2">
                    Je plaatst als <?= h($student) ?>.
                </div>
            </form>
        <?php endif; ?>

        <div id="message-area"></div>

        <section class="blackboard">
            <div id="comments-grid" class="row g-3"></div>
        </section>
    </div>
</div>

<script>
const boardCode = <?= json_encode($code) ?>;
const canPost = <?= json_encode($canPost) ?>;
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
    messageArea.innerHTML = `
        <div class="alert alert-${type}">${escapeHtml(text)}</div>
    `;
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

    commentsGrid.innerHTML = comments.map((comment) => `
        <article class="col-12 col-md-6 col-xl-4">
            <div class="comment-note">
                <div class="comment-text mb-3">${escapeHtml(comment.comment_text || "")}</div>
                <div class="comment-meta d-flex justify-content-between gap-2">
                    <strong>${escapeHtml(comment.student_name || "Leerling")}</strong>
                    <span>${escapeHtml(formatTime(comment.created_at))}</span>
                </div>
            </div>
        </article>
    `).join("");
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

if (commentForm && canPost) {
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
}

loadComments();
setInterval(loadComments, 2500);
</script>

<script src="tabler/core/dist/js/tabler.min.js"></script>

</body>
</html>
