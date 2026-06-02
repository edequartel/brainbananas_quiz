<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$historyDir = __DIR__ . '/session-history';
$indexPath = $historyDir . '/index.json';

if (!is_dir($historyDir)) {
    mkdir($historyDir, 0755, true);
}

if (!file_exists($indexPath)) {
    file_put_contents($indexPath, '[]');
}

$json = file_get_contents($indexPath);
$sessions = json_decode($json, true);

if (!is_array($sessions)) {
    $sessions = [];
}

$sessions = array_reverse($sessions);
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>BrainBananas Opgeslagen sessies</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="tabler/core/dist/css/tabler.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<div class="page">
    <div class="container-xl py-4">

        <div class="row align-items-center mb-4">
            <div class="col">
                <h1>🍌 Opgeslagen sessies</h1>
                <div class="text-secondary">
                    Lokale JSON-bestanden uit session-history
                </div>
            </div>

            <div class="col-auto">
                <a href="teacher.php" class="btn btn-yellow">
                    Nieuwe sessie
                </a>
            </div>
        </div>

        <div class="alert alert-info">
            Pad: <code><?= h($indexPath) ?></code><br>
            Aantal sessies gevonden: <strong><?= count($sessions) ?></strong>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Sessie</th>
                            <th>Quiz</th>
                            <th>Leerlingen</th>
                            <th>Vragen</th>
                            <th>Bestand</th>
                            <th>Bekijken</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (!$sessions): ?>
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-4">
                                Er zijn nog geen opgeslagen sessies.
                                Gebruik eerst <strong>Sessiegeschiedenis opslaan</strong> in live.php.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td><?= h($session['date'] ?? '-') ?></td>
                            <td class="fw-bold"><?= h($session['session_code'] ?? '-') ?></td>
                            <td>
                                <?= h($session['quiz_title'] ?? '-') ?>
                                <div class="text-secondary">
                                    <?= h($session['quiz_file'] ?? '-') ?>
                                </div>
                            </td>
                            <td><?= h($session['student_count'] ?? '0') ?></td>
                            <td><?= h($session['counted_question_count'] ?? $session['question_count'] ?? '0') ?></td>
                            <td>
                                <code><?= h($session['file'] ?? '-') ?></code>
                            </td>
                            <td>
                                <?php if (!empty($session['file'])): ?>
                                    <a
                                        href="view-session.php?file=<?= urlencode($session['file']) ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Bekijk
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>

                </table>
            </div>
        </div>

    </div>
</div>

</body>
</html>
