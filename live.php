<?php

$code = strtoupper(trim($_GET['code'] ?? ''));

if ($code === '') {
    die('Geen sessiecode.');
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>BrainBananas Live</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="tabler/core/dist/css/tabler.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-yellow-lt">

<div class="page">

    <div class="container py-4">

        <div class="text-center mb-4">

            <h1 class="display-5">
                🍌 BrainBananas Live
            </h1>

            <div class="text-secondary">
                Sessiecode
            </div>

            <div class="display-3 fw-bold">
                <?= h($code) ?>
            </div>

            <div class="mt-3 d-flex gap-2 justify-content-center flex-wrap">

                <a
                    href="student.php"
                    class="btn btn-outline-secondary"
                    target="_blank"
                >
                    Leerlingpagina openen
                </a>

                <a
                    href="report.php?code=<?= urlencode($code) ?>"
                    class="btn btn-yellow"
                >
                    Live rapport openen
                </a>

                <a
                    href="history.php"
                    class="btn btn-outline-primary"
                >
                    Bekijk opgeslagen sessies
                </a>

                <form
                    method="post"
                    action="api/archive-session.php"
                    class="d-inline"
                >
                    <input
                        type="hidden"
                        name="code"
                        value="<?= h($code) ?>"
                    >

                    <button class="btn btn-outline-secondary">
                        Sessiegeschiedenis opslaan
                    </button>
                </form>

            </div>

        </div>

        <div id="live-area"></div>

    </div>

</div>

<script>
const code = <?= json_encode($code) ?>;

async function loadResults() {

    const response = await fetch(
        "api/results.php?code=" + encodeURIComponent(code)
    );

    const data = await response.json();

    if (!data.ok) {
        document.getElementById("live-area").innerHTML =
            `<div class="alert alert-danger">${escapeHtml(data.error)}</div>`;
        return;
    }

    if (data.quiz_finished) {
        document.getElementById("live-area").innerHTML = `
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-secondary">
                        ${escapeHtml(data.quiz_title)}
                    </div>

                    <h2 class="mt-2">
                        Quiz afgerond
                    </h2>

                    <div class="alert alert-success mt-3">
                        Leerlingen kunnen nu hun eindcijfer zien.
                    </div>
                </div>
            </div>
        `;
        return;
    }

    const q = data.question;
    const answerChoices = (q.answers || []).map((answer, index) => {
        const isCorrect = Number(q.correct) === index;

        return `
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                    ${escapeHtml(answer)}
                </span>

                ${
                    isCorrect
                    ? `<span class="badge bg-green text-green-fg">Juiste antwoord</span>`
                    : ``
                }
            </li>
        `;
    }).join("");

    let rows = "";

    data.answer_rows.forEach(row => {

        let statusBadge = "";

        if (row.status === "answered") {

            statusBadge = row.is_correct
                ? `<span class="badge bg-green text-green-fg">Goed</span>`
                : `<span class="badge bg-yellow text-yellow-fg">Volgende keer beter</span>`;

        } else {

            statusBadge =
                `<span class="badge bg-yellow text-yellow-fg">Wacht op antwoord</span>`;
        }

        rows += `
            <tr>
                <td class="fw-bold">
                    ${escapeHtml(row.student_name)}
                </td>

                <td>
                    ${statusBadge}
                </td>

                <td>
                    ${escapeHtml(row.given_answer || "-")}
                </td>

                <td>
                    ${escapeHtml(row.correct_answer || "-")}
                </td>

                <td class="text-secondary">
                    ${escapeHtml(row.answered_at || "-")}
                </td>
            </tr>
        `;
    });

    if (rows === "") {
        rows = `
            <tr>
                <td colspan="5" class="text-center text-secondary py-4">
                    Er zijn nog geen leerlingen aangesloten.
                </td>
            </tr>
        `;
    }

    document.getElementById("live-area").innerHTML = `
        <div class="row g-4">

            <div class="col-12">

                <div class="card">

                    <div class="card-body text-center">

                        <div class="text-secondary">
                            ${escapeHtml(data.quiz_title)}
                        </div>

                        <h2 class="mt-2">
                            Vraag ${data.current_question + 1}
                            /
                            ${data.total_questions}
                        </h2>

                        <h1 class="my-4">
                            ${escapeHtml(q.question)}
                        </h1>

                        <div class="text-start mb-4">
                            <div class="fw-bold mb-2">
                                Antwoordkeuzes
                            </div>

                            <ul class="list-group">
                                ${answerChoices}
                            </ul>
                        </div>

                        <div class="alert alert-info">
                            ${data.answered_count}
                            of
                            ${data.player_count}
                            leerlingen hebben geantwoord
                        </div>

                        <form method="post" action="api/next-question.php">

                            <input
                                type="hidden"
                                name="code"
                                value="${escapeHtml(code)}"
                            >

                            <div class="d-flex gap-2 justify-content-center flex-wrap">
                                <button
                                    class="btn btn-yellow btn-lg"
                                    name="action"
                                    value="next"
                                >
                                    ${data.is_last_question ? "Quiz afronden" : "Volgende vraag"}
                                </button>

                                <button
                                    class="btn btn-outline-secondary btn-lg"
                                    name="action"
                                    value="skip"
                                >
                                    Sla vraag over
                                </button>
                            </div>

                        </form>

                        ${
                            data.is_last_question
                            ? `<div class="alert alert-warning mt-3">
                                   Dit is de laatste vraag.
                               </div>`
                            : ``
                        }

                    </div>

                </div>

            </div>

            <div class="col-12">

                <div class="card">

                    <div class="card-header">
                        <h3 class="card-title">
                            Live antwoorden van leerlingen
                        </h3>
                    </div>

                    <div class="table-responsive">

                        <table class="table table-vcenter card-table">

                            <thead>
                                <tr>
                                    <th>Leerling</th>
                                    <th>Status</th>
                                    <th>Gegeven antwoord</th>
                                    <th>Juiste antwoord</th>
                                    <th>Tijd</th>
                                </tr>
                            </thead>

                            <tbody>
                                ${rows}
                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>
    `;
}

function escapeHtml(text) {
    return String(text)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

loadResults();

setInterval(loadResults, 2000);

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
