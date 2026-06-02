<?php

session_start();

require __DIR__ . '/supabase.php';
require __DIR__ . '/session-cleanup.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$student = trim($_POST["student"] ?? "");
$code = strtoupper(trim($_POST["code"] ?? ""));

brainbananas_cleanup_old_sessions();

if ($student === "" || $code === "") {
    die("Naam en sessiecode zijn verplicht.");
}

$endpoint =
    "brainbananas_sessions" .
    "?code=eq." . urlencode($code) .
    "&status=eq.active" .
    "&select=*";

$sessionResult = supabase_request("GET", $endpoint);

if (!$sessionResult["ok"]) {
    die("Kon sessie niet controleren: " . h($sessionResult["raw"] ?? "Onbekende fout"));
}

if (empty($sessionResult["data"])) {
    die("Ongeldige of inactieve sessiecode.");
}

$session = $sessionResult["data"][0];

$playerResult = supabase_request("POST", "brainbananas_players", [
    "session_code" => $code,
    "student_name" => $student
]);

if (!$playerResult["ok"]) {
    die("Kon niet deelnemen aan sessie: " . h($playerResult["raw"] ?? "Onbekende fout"));
}

$_SESSION["student"] = $student;
$_SESSION["code"] = $code;
$_SESSION["quiz"] = $session["quiz_file"];
$_SESSION["question_index"] = 0;
$_SESSION["score"] = 0;

header("Location: ../quiz.php");
exit;
