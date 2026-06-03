<?php
require __DIR__ . '/../includes/teacher-auth.php';

brainbananas_require_teacher_auth('../teacher.php', '../');

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$projectDir = dirname(__DIR__);
$output = [];
$exitCode = 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Alleen POST is toegestaan.');
}

$command = 'cd ' . escapeshellarg($projectDir) . ' && git pull 2>&1';
exec($command, $output, $exitCode);
$outputText = implode("\n", $output);
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Git pull</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="../tabler/core/dist/css/tabler.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-yellow-lt">

<div class="page page-center">
    <div class="container container-tight py-5">

        <div class="text-center mb-4">
            <h1 class="display-5">🍌 BrainBananas</h1>
            <div class="text-secondary">Git pull</div>
        </div>

        <div class="alert alert-<?= $exitCode === 0 ? 'success' : 'danger' ?>">
            <?= $exitCode === 0 ? 'Git pull is klaar.' : 'Git pull is niet gelukt.' ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Uitvoer</h2>
            </div>

            <div class="card-body">
                <pre class="m-0 p-3 bg-dark text-white rounded"><?= h($outputText !== '' ? $outputText : 'Geen uitvoer.') ?></pre>
            </div>
        </div>

        <a href="../teacher.php" class="btn btn-yellow w-100 mt-3">
            Terug naar lerarenoverzicht
        </a>

    </div>
</div>

<script src="../tabler/core/dist/js/tabler.min.js"></script>

</body>
</html>
