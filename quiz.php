<?php
session_start();

require __DIR__ . '/api/supabase.php';
require __DIR__ . '/api/session-options.php';
require __DIR__ . '/api/session-cleanup.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$student = $_SESSION['student'] ?? '';
$code = $_SESSION['code'] ?? '';

brainbananas_cleanup_old_sessions();

if ($student === '' || $code === '') {
    header('Location: student.php');
    exit;
}

$sessionResult = supabase_request(
    'GET',
    'brainbananas_sessions?code=eq.' . urlencode($code) . '&select=*'
);

if (!$sessionResult['ok'] || empty($sessionResult['data'])) {
    die('Sessie niet gevonden.');
}

$session = $sessionResult['data'][0];

if (!in_array(($session['status'] ?? ''), ['active', 'finished'], true)) {
    die('Sessie is niet actief.');
}

$currentQuestion = intval($session['current_question']);

$quizFile = basename($session['quiz_file']);
$quizPath = __DIR__ . '/quizzes/' . $quizFile;

if (!file_exists($quizPath)) {
    die('Quizbestand niet gevonden.');
}

$quiz = json_decode(file_get_contents($quizPath), true);
$totalQuestions = count($quiz['questions']);
$sessionOptions = brainbananas_read_session_options($code);
$teacherSkippedQuestions = brainbananas_skipped_questions($sessionOptions);

if ($currentQuestion >= $totalQuestions) {
    $answersResult = supabase_request(
        'GET',
        'brainbananas_answers' .
        '?session_code=eq.' . urlencode($code) .
        '&student_name=eq.' . urlencode($student) .
        '&select=*'
    );

    $answers = $answersResult['data'] ?? [];
    $correctCount = 0;
    $answeredQuestions = [];
    $countedQuestions = max(0, $totalQuestions - count($teacherSkippedQuestions));

    foreach ($answers as $answer) {
        $questionIndex = intval($answer['question_index']);

        if (in_array($questionIndex, $teacherSkippedQuestions, true)) {
            continue;
        }

        $answeredQuestions[$questionIndex] = true;

        if (!empty($answer['is_correct'])) {
            $correctCount++;
        }
    }

    $missedCount = max(0, $countedQuestions - count($answeredQuestions));
    $finalGrade = $countedQuestions > 0
        ? round(1 + (($correctCount / $countedQuestions) * 9), 1)
        : null;
    ?>
    <!doctype html>
    <html lang="nl">
    <head>
        <meta charset="utf-8">
        <title>BrainBananas Quiz</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link
            href="tabler/core/dist/css/tabler.min.css"
            rel="stylesheet"
        >
    </head>

    <body class="bg-yellow-lt">
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="text-center mb-4">
                <h1 class="display-5">🍌 BrainBananas</h1>
                <div class="text-secondary">
                    <?= h($student) ?> · sessie <?= h($code) ?>
                </div>
            </div>

            <div class="card bg-white border-yellow">
                <div class="card-body text-center">
                    <div class="text-secondary mb-1">
                        Eindcijfer
                    </div>

                    <?php if ($finalGrade !== null): ?>
                        <div class="display-3 fw-bold text-yellow">
                            <?= h(number_format($finalGrade, 1, ',', '')) ?>
                        </div>
                    <?php else: ?>
                        <div class="h1 fw-bold text-secondary">
                            Geen cijfer
                        </div>
                    <?php endif; ?>

                    <div class="text-secondary">
                        <?= h($correctCount) ?> van <?= h($countedQuestions) ?> meetellende vragen goed
                    </div>

                    <?php if (count($teacherSkippedQuestions) > 0): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <?= h(count($teacherSkippedQuestions)) ?> vraag<?= count($teacherSkippedQuestions) === 1 ? '' : 'en' ?>
                            door de leraar overgeslagen en niet meegerekend.
                        </div>
                    <?php endif; ?>

                    <?php if ($missedCount > 0): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <?= h($missedCount) ?> niet beantwoorde vraag<?= $missedCount === 1 ? '' : 'en' ?>
                            tel<?= $missedCount === 1 ? 't' : 'len' ?> mee in de berekening.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$question = $quiz['questions'][$currentQuestion];
$showAnswerFeedback = !empty($sessionOptions['show_answer_feedback']);
$isLastQuestion = $currentQuestion >= $totalQuestions - 1;

$answerCheck = supabase_request(
    'GET',
    'brainbananas_answers' .
    '?session_code=eq.' . urlencode($code) .
    '&student_name=eq.' . urlencode($student) .
    '&question_index=eq.' . $currentQuestion .
    '&select=*'
);

$alreadyAnswered = !empty($answerCheck['data']);
$submittedAnswer = $alreadyAnswered ? $answerCheck['data'][0] : null;
$correctIndex = intval($question['correct'] ?? -1);
$answerIndex = $submittedAnswer ? intval($submittedAnswer['answer_index'] ?? -1) : -1;
$givenAnswer = $question['answers'][$answerIndex] ?? null;
$correctAnswer = $question['answers'][$correctIndex] ?? null;
$isCorrect = $submittedAnswer ? !empty($submittedAnswer['is_correct']) : false;
$explanation = trim((string)($question['uitleg'] ?? $question['explanation'] ?? ''));
$finalCorrectCount = 0;
$finalCountedQuestions = max(0, $totalQuestions - count($teacherSkippedQuestions));
$finalMissedCount = 0;
$finalGrade = null;

if ($alreadyAnswered && $isLastQuestion) {
    $allAnswersResult = supabase_request(
        'GET',
        'brainbananas_answers' .
        '?session_code=eq.' . urlencode($code) .
        '&student_name=eq.' . urlencode($student) .
        '&select=*'
    );

    $allAnswers = $allAnswersResult['data'] ?? [];
    $answeredQuestions = [];

    foreach ($allAnswers as $answer) {
        $questionIndex = intval($answer['question_index']);

        if (in_array($questionIndex, $teacherSkippedQuestions, true)) {
            continue;
        }

        $answeredQuestions[$questionIndex] = true;

        if (!empty($answer['is_correct'])) {
            $finalCorrectCount++;
        }
    }

    $finalMissedCount = max(0, $finalCountedQuestions - count($answeredQuestions));
    $finalGrade = $finalCountedQuestions > 0
        ? round(1 + (($finalCorrectCount / $finalCountedQuestions) * 9), 1)
        : null;
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>BrainBananas Quiz</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="tabler/core/dist/css/tabler.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-yellow-lt">

<div class="page page-center">
    <div class="container container-tight py-4">

        <div class="text-center mb-4">
            <h1 class="display-5">🍌 BrainBananas</h1>

            <div class="text-secondary">
                <?= h($student) ?> · sessie <?= h($code) ?>
            </div>
        </div>

        <div class="card">

            <div class="card-header">
                <div class="w-100">
                    <div class="d-flex justify-content-between">
                        <strong>Vraag <?= $currentQuestion + 1 ?></strong>
                        <span class="text-secondary"><?= $totalQuestions ?> totaal</span>
                    </div>

                    <div class="progress progress-sm mt-2">
                        <div
                            class="progress-bar bg-yellow"
                            style="width: <?= (($currentQuestion + 1) / $totalQuestions) * 100 ?>%"
                        ></div>
                    </div>
                </div>
            </div>

            <div class="card-body">

                <h2 class="mb-4">
                    <?= h($question['question']) ?>
                </h2>

                <?php if ($alreadyAnswered): ?>

                    <div class="alert alert-success">
                        Je antwoord is opgeslagen. Wacht op de volgende vraag.
                    </div>

                    <?php if ($finalGrade !== null): ?>
                        <div class="card bg-white border-yellow mt-3">
                            <div class="card-body text-center">
                                <div class="text-secondary mb-1">
                                    Eindcijfer
                                </div>

                                <?php if ($finalGrade !== null): ?>
                                    <div class="display-3 fw-bold text-yellow">
                                        <?= h(number_format($finalGrade, 1, ',', '')) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="h1 fw-bold text-secondary">
                                        Geen cijfer
                                    </div>
                                <?php endif; ?>

                                <div class="text-secondary">
                                    <?= h($finalCorrectCount) ?> van <?= h($finalCountedQuestions) ?> meetellende vragen goed
                                </div>

                                <?php if (count($teacherSkippedQuestions) > 0): ?>
                                    <div class="alert alert-info mt-3 mb-0">
                                        <?= h(count($teacherSkippedQuestions)) ?> vraag<?= count($teacherSkippedQuestions) === 1 ? '' : 'en' ?>
                                        door de leraar overgeslagen en niet meegerekend.
                                    </div>
                                <?php endif; ?>

                                <?php if ($finalMissedCount > 0): ?>
                                    <div class="alert alert-warning mt-3 mb-0">
                                        <?= h($finalMissedCount) ?> niet beantwoorde vraag<?= $finalMissedCount === 1 ? '' : 'en' ?>
                                        tel<?= $finalMissedCount === 1 ? 't' : 'len' ?> mee in de berekening.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($showAnswerFeedback): ?>
                        <div class="alert <?= $isCorrect ? 'alert-success' : 'alert-warning' ?> mt-3">
                            <strong>
                                <?= $isCorrect ? 'Goed beantwoord.' : 'Jammer, volgende keer beter.' ?>
                            </strong>
                            <?php if (!$isCorrect): ?>
                                Kijk hieronder welk antwoord juist was.
                            <?php endif; ?>
                        </div>

                        <div class="card <?= $isCorrect ? 'bg-green-lt border-success' : 'bg-yellow-lt border-warning' ?> mt-3">
                            <div class="card-body">
                                <div class="mb-2">
                                    <strong>Jouw antwoord:</strong>
                                    <?= h($givenAnswer ?? '-') ?>
                                    <span class="badge <?= $isCorrect ? 'bg-success' : 'bg-warning text-dark' ?> ms-2">
                                        <?= $isCorrect ? 'Goed' : 'Volgende keer beter' ?>
                                    </span>
                                </div>

                                <div class="mb-2">
                                    <strong>Juiste antwoord:</strong>
                                    <?= h($correctAnswer ?? '-') ?>
                                </div>

                                <?php if ($explanation !== ''): ?>
                                    <div>
                                        <strong>Uitleg:</strong>
                                        <?= h($explanation) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>

                    <form method="post" action="api/submit.php">

                        <input
                            type="hidden"
                            name="question_index"
                            value="<?= $currentQuestion ?>"
                        >

                        <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column">

                            <?php foreach ($question['answers'] as $index => $answer): ?>
                                <?php $answerLabel = chr(65 + $index); ?>

                                <label class="form-selectgroup-item flex-fill">
                                    <input
                                        type="radio"
                                        name="answer_index"
                                        value="<?= $index ?>"
                                        class="form-selectgroup-input"
                                        required
                                    >

                                    <div class="form-selectgroup-label d-flex align-items-center p-3">
                                        <div class="me-3">
                                            <span class="form-selectgroup-check"></span>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-secondary text-secondary-fg">
                                                <?= h($answerLabel) ?>
                                            </span>
                                            <span>
                                                <?= h($answer) ?>
                                            </span>
                                        </div>
                                    </div>
                                </label>

                            <?php endforeach; ?>

                        </div>

                        <button class="btn btn-yellow btn-lg w-100 mt-4">
                            Antwoord versturen
                        </button>

                    </form>

                    <div class="alert alert-info mt-3">
                        Als de leraar doorgaat, ga je automatisch naar de volgende vraag.
                    </div>

                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<script>
const sessionCode = <?= json_encode($code) ?>;
const visibleQuestion = <?= json_encode($currentQuestion) ?>;
let questionPollingInterval = null;

async function checkQuestionChange() {
    try {
        const response = await fetch(
            "api/current-question.php?code=" + encodeURIComponent(sessionCode),
            { cache: "no-store" }
        );

        const data = await response.json();

        if (!data.ok) {
            return;
        }

        if (data.current_question !== visibleQuestion) {
            window.location.reload();
        }

    } catch (error) {
        console.error(error);
    }
}

function startQuestionPolling(delay = 10000) {
    if (questionPollingInterval !== null) {
        clearInterval(questionPollingInterval);
    }

    questionPollingInterval = setInterval(checkQuestionChange, delay);
}

async function connectQuestionRealtime() {
    try {
        const response = await fetch("api/realtime-config.php", { cache: "no-store" });
        const config = await response.json();

        if (!config.ok) {
            startQuestionPolling(1500);
            return;
        }

        let ref = 1;
        const socket = new WebSocket(
            config.websocket_url +
            "?apikey=" + encodeURIComponent(config.anon_key) +
            "&vsn=1.0.0"
        );

        const send = (topic, event, payload = {}) => {
            socket.send(JSON.stringify({
                topic,
                event,
                payload,
                ref: String(ref++),
                join_ref: "1"
            }));
        };

        socket.addEventListener("open", () => {
            const topic = "realtime:brainbananas-student-" + sessionCode;

            send(topic, "phx_join", {
                config: {
                    postgres_changes: [
                        {
                            event: "UPDATE",
                            schema: "public",
                            table: "brainbananas_sessions",
                            filter: "code=eq." + sessionCode
                        }
                    ],
                    broadcast: { self: false },
                    presence: { key: "" }
                },
                access_token: config.anon_key
            });

            startQuestionPolling(10000);

            setInterval(() => {
                if (socket.readyState === WebSocket.OPEN) {
                    send("phoenix", "heartbeat", {});
                }
            }, 25000);
        });

        socket.addEventListener("message", (event) => {
            const message = JSON.parse(event.data);

            if (message.event === "postgres_changes") {
                checkQuestionChange();
            }
        });

        socket.addEventListener("close", () => {
            startQuestionPolling(1500);
        });

        socket.addEventListener("error", () => {
            startQuestionPolling(1500);
        });
    } catch (error) {
        console.error(error);
        startQuestionPolling(1500);
    }
}

connectQuestionRealtime();

document.addEventListener("submit", (event) => {
    event.target.querySelectorAll("button[type='submit'], button:not([type])")
        .forEach((button) => {
            button.disabled = true;
            button.textContent = "Even wachten...";
        });
});
</script>

<script src="tabler/core/dist/js/tabler.min.js"></script>

</body>
</html>
</body>
</html>
