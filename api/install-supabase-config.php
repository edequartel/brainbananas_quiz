<?php

require __DIR__ . '/../includes/teacher-auth.php';

brainbananas_require_teacher_auth('../teacher.php', '../');

$message = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectDir = dirname(__DIR__);
    $targetDir = dirname($_SERVER['DOCUMENT_ROOT'] ?? $projectDir) . '/private';
    $targetPath = $targetDir . '/supabase_brainbananas.php';
    $command = 'cd ' . escapeshellarg($projectDir)
        . ' && git show '
        . escapeshellarg('e9b70b1^:private/supabase_brainbananas.php')
        . ' 2>/dev/null';
    $contents = shell_exec($command);

    if (!is_string($contents) || $contents === '') {
        $message = 'Configuratie kon niet uit Git-geschiedenis worden hersteld.';
    } elseif (!is_dir($targetDir) && !mkdir($targetDir, 0700, true)) {
        $message = 'Privémap kon niet worden gemaakt.';
    } elseif (file_put_contents($targetPath, $contents, LOCK_EX) === false) {
        $message = 'Configuratiebestand kon niet worden geschreven.';
    } else {
        chmod($targetPath, 0600);
        $config = require $targetPath;
        $ok = is_array($config)
            && !empty($config['SUPABASE_URL'])
            && !empty($config['SUPABASE_SERVICE_ROLE_KEY'])
            && !empty($config['SUPABASE_ANON_KEY']);
        $message = $ok
            ? 'Supabase-configuratie is hersteld.'
            : 'Het herstelde configuratiebestand is ongeldig.';
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Supabase-configuratie herstellen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../tabler/core/dist/css/tabler.min.css" rel="stylesheet">
</head>
<body class="bg-yellow-lt">
<div class="page page-center">
    <div class="container container-tight py-5">
        <div class="card">
            <div class="card-body">
                <h1 class="card-title">Supabase-configuratie herstellen</h1>
                <?php if ($message !== ''): ?>
                    <div class="alert alert-<?= $ok ? 'success' : 'danger' ?>">
                        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <?php if (!$ok): ?>
                    <form method="post">
                        <button class="btn btn-yellow w-100" type="submit">
                            Configuratie herstellen
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
