<?php

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$file = basename($_GET['file'] ?? '');

if ($file === '') {
    die('Bestand ontbreekt.');
}

$path = __DIR__ . '/session-history/' . $file;

if (!file_exists($path)) {
    die('Sessiebestand niet gevonden.');
}

$data = json_decode(file_get_contents($path), true);

if (!$data) {
    die('Ongeldige sessie-JSON.');
}

$metadata = $data['metadata'];
$students = $data['students'];
$countedQuestionCount = $metadata['counted_question_count'] ?? $metadata['question_count'];
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Opgeslagen sessie</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="tabler/core/dist/css/tabler.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-yellow-lt">

<div class="page">
    <div class="container-xl py-4">

        <div class="row align-items-center mb-4">
            <div class="col">
                <h1>🍌 Opgeslagen BrainBananas-sessie</h1>
                <div class="text-secondary">
                    <?= h($metadata['quiz_title']) ?> · <?= h($metadata['session_code']) ?>
                </div>
            </div>

            <div class="col-auto d-print-none">
                <button onclick="window.print()" class="btn btn-yellow">
                    Print / PDF
                </button>

                <a href="history.php" class="btn btn-outline-secondary">
                    Terug
                </a>
            </div>
        </div>

        <div class="row row-cards mb-4">

            <div class="col-sm-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="h1"><?= h($metadata['student_count']) ?></div>
                        <div class="text-secondary">Leerlingen</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="h1"><?= h($countedQuestionCount) ?></div>
                        <div class="text-secondary">Meetellende vragen</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="h1"><?= h($metadata['quiz_file']) ?></div>
                        <div class="text-secondary">Quizbestand</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="h1">JSON</div>
                        <div class="text-secondary">Lokaal archief</div>
                    </div>
                </div>
            </div>

        </div>

        <div class="card mb-4">

            <div class="card-header">
                <h2 class="card-title">Leerlingenoverzicht</h2>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Leerling</th>
                            <th>Goed</th>
                            <th>Beantwoord</th>
                            <th>Resultaat</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td class="fw-bold"><?= h($student['student_name']) ?></td>
                            <td><?= h($student['correct']) ?> / <?= h($countedQuestionCount) ?></td>
                            <td><?= h($student['answered']) ?> / <?= h($countedQuestionCount) ?></td>
                            <td>
                                <span class="badge bg-yellow text-yellow-fg">
                                    <?= h($student['percentage']) ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <?php foreach ($students as $student): ?>

            <div class="card mb-4">

                <div class="card-header">
                    <h2 class="card-title">
                        <?= h($student['student_name']) ?>
                        · <?= h($student['correct']) ?> / <?= h($countedQuestionCount) ?>
                        · <?= h($student['percentage']) ?>%
                    </h2>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Vraag</th>
                                <th>Gegeven antwoord</th>
                                <th>Juiste antwoord</th>
                                <th>Resultaat</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($student['answers'] as $answer): ?>
                            <tr>
                                <td><?= h($answer['question_index'] + 1) ?></td>
                                <td><?= h($answer['question']) ?></td>
                                <td><?= h($answer['given_answer'] ?? '-') ?></td>
                                <td><?= h($answer['correct_answer']) ?></td>
                                <td>
                                    <?php if (empty($answer['answered'])): ?>
                                        <span class="badge bg-secondary text-secondary-fg">
                                            Niet beantwoord
                                        </span>
                                    <?php elseif (!empty($answer['is_correct'])): ?>
                                        <span class="badge bg-green text-green-fg">
                                            Goed
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-red text-red-fg">
                                            Volgende keer beter
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            </div>

        <?php endforeach; ?>

    </div>
</div>

</body>
</html>
