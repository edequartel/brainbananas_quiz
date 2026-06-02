<?php

require __DIR__ . '/supabase.php';
require __DIR__ . '/session-options.php';
require __DIR__ . '/archive-helper.php';

$code = strtoupper(trim($_POST['code'] ?? $_GET['code'] ?? ''));

$archiveResult = brainbananas_archive_session($code);

if (!$archiveResult['ok']) {
    die($archiveResult['error'] ?? 'Kon sessie niet opslaan.');
}

header('Location: ../history.php');
exit;
