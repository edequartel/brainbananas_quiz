<?php

require_once __DIR__ . '/includes/theme.php';
require_once __DIR__ . '/includes/teacher-auth.php';

brainbananas_require_teacher_auth();

ini_set('display_errors', 1);
error_reporting(E_ALL);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_history_date($value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return '-';
    }

    try {
        $date = new DateTime($value);
        return $date->format('d-m-y H:i');
    } catch (Exception $exception) {
        return $value;
    }
}

$historyDir = __DIR__ . '/session-history';
$indexPath = $historyDir . '/index.json';
$message = '';
$messageType = 'success';

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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    $file = basename((string)($_POST['file'] ?? ''));

    if ($action !== 'delete') {
        $message = 'Onbekende actie.';
        $messageType = 'danger';
    } elseif ($file === '') {
        $message = 'Geen sessiebestand gekozen.';
        $messageType = 'danger';
    } else {
        $path = $historyDir . '/' . $file;

        if (file_exists($path) && !unlink($path)) {
            $message = 'Kon sessiebestand niet verwijderen.';
            $messageType = 'danger';
        } else {
            $sessions = array_values(array_filter($sessions, function ($session) use ($file) {
                return ($session['file'] ?? '') !== $file;
            }));

            file_put_contents(
                $indexPath,
                json_encode($sessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $message = 'Opgeslagen sessie verwijderd.';
            $messageType = 'success';
        }
    }
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
    <?php brainbananas_theme_head(); ?>
</head>

<body class="bg-light">

<div class="page">
    <div class="container container-tight py-4">
        <?php brainbananas_theme_picker(); ?>

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

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= h($messageType) ?>">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

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
                            <th>Acties</th>
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
                            <td><?= h(format_history_date($session['date_iso'] ?? $session['date'] ?? '')) ?></td>
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
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a
                                            href="view-session.php?file=<?= urlencode($session['file']) ?>"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Bekijk
                                        </a>

                                        <form method="post">
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >
                                            <input
                                                type="hidden"
                                                name="file"
                                                value="<?= h($session['file']) ?>"
                                            >
                                            <button class="btn btn-sm btn-outline-danger">
                                                Verwijderen
                                            </button>
                                        </form>
                                    </div>
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
