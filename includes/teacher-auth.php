<?php

require_once __DIR__ . '/theme.php';

const BRAINBANANAS_TEACHER_PASSWORD = 'bartimeus';

function brainbananas_teacher_is_authenticated(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return !empty($_SESSION['brainbananas_teacher_authenticated']);
}

function brainbananas_teacher_login_error(): string
{
    return 'Wachtwoord onjuist.';
}

function brainbananas_require_teacher_auth(
    ?string $redirectAfterLogin = null,
    string $assetPrefix = ''
): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!empty($_SESSION['brainbananas_teacher_authenticated'])) {
        return;
    }

    $redirectAfterLogin ??= $_SERVER['REQUEST_URI'] ?? 'teacher.php';
    $loginError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_password'])) {
        $password = (string)$_POST['teacher_password'];
        $redirectAfterLogin = (string)($_POST['redirect'] ?? $redirectAfterLogin);

        if (hash_equals(BRAINBANANAS_TEACHER_PASSWORD, $password)) {
            $_SESSION['brainbananas_teacher_authenticated'] = true;
            header('Location: ' . $redirectAfterLogin);
            exit;
        }

        $loginError = brainbananas_teacher_login_error();
    }

    brainbananas_render_teacher_login($redirectAfterLogin, $loginError, $assetPrefix);
    exit;
}

function brainbananas_render_teacher_login(string $redirectAfterLogin, string $loginError, string $assetPrefix): void
{
    $cssPath = $assetPrefix . 'tabler/core/dist/css/tabler.min.css';
    $jsPath = $assetPrefix . 'tabler/core/dist/js/tabler.min.js';
    ?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>BrainBananas Leraar Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="<?= htmlspecialchars($cssPath, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php brainbananas_theme_head(); ?>
</head>
<body class="bg-yellow-lt">
<div class="page page-center">
    <div class="container container-tight py-4">
        <?php brainbananas_theme_picker(); ?>

        <div class="text-center mb-4">
            <h1 class="display-5">🍌 BrainBananas</h1>
            <div class="text-secondary">Leraar login</div>
        </div>

        <form method="post" class="card">
            <div class="card-body">
                <?php if ($loginError !== ''): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <input
                    type="hidden"
                    name="redirect"
                    value="<?= htmlspecialchars($redirectAfterLogin, ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="mb-3">
                    <label class="form-label" for="teacher-password">Wachtwoord</label>
                    <input
                        class="form-control"
                        id="teacher-password"
                        type="password"
                        name="teacher_password"
                        autocomplete="current-password"
                        autofocus
                        required
                    >
                </div>

                <button class="btn btn-yellow w-100" type="submit">
                    Inloggen
                </button>
            </div>
        </form>

        <div class="text-center mt-4">
            <a href="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>student.php" class="text-secondary">
                Naar leerlingpagina
            </a>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
    <?php
}
