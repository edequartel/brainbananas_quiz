<?php

require_once __DIR__ . '/includes/theme.php';

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
    <?php brainbananas_theme_head(); ?>
</head>

<body class="bg-yellow-lt">

<div class="page">

    <div class="container py-4">
        <?php brainbananas_theme_picker(); ?>

        <div class="text-center mb-4">

            <div class="text-secondary">
                Sessiecode
            </div>

            <div class="display-3 fw-bold">
                <?= h($code) ?>
            </div>

            <label class="form-check form-switch d-inline-flex align-items-center gap-2 mt-3">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="expanded-view-toggle"
                >
                <span class="form-check-label">
                    Uitgebreide weergave
                </span>
            </label>

            <div class="mt-2 d-none" data-expanded-field>
                <span class="badge bg-secondary text-secondary-fg" id="connection-status">
                    Verbinding maken...
                </span>
            </div>

            <div class="mt-3 d-none gap-2 justify-content-center flex-wrap" data-expanded-field>

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
let livePollingInterval = null;
const connectionStatus = document.getElementById("connection-status");
const expandedViewToggle = document.getElementById("expanded-view-toggle");
const expandedFields = document.querySelectorAll("[data-expanded-field]");

function isExpandedView() {
    return expandedViewToggle && expandedViewToggle.checked;
}

function syncExpandedFields() {
    expandedFields.forEach((element) => {
        element.classList.toggle("d-none", !isExpandedView());
        element.classList.toggle("d-flex", isExpandedView() && element.classList.contains("gap-2"));
    });
}

if (expandedViewToggle) {
    expandedViewToggle.addEventListener("change", () => {
        syncExpandedFields();
        loadResults();
    });
}

syncExpandedFields();

function setConnectionStatus(mode) {
    if (!connectionStatus) {
        return;
    }

    if (mode === "websocket") {
        connectionStatus.className = "badge bg-green text-green-fg";
        connectionStatus.textContent = "WebSocket actief";
        return;
    }

    if (mode === "connecting") {
        connectionStatus.className = "badge bg-secondary text-secondary-fg";
        connectionStatus.textContent = "WebSocket verbinden...";
        return;
    }

    connectionStatus.className = "badge bg-yellow text-yellow-fg";
    connectionStatus.textContent = "Polling actief";
}

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
        const answerLabel = String.fromCharCode(65 + index);

        return `
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary text-secondary-fg">
                        ${answerLabel}
                    </span>
                    <span>
                        ${escapeHtml(answer)}
                    </span>
                </span>

                ${
                    isExpandedView() && isCorrect
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

    const questionCard = `
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
    `;

    const liveAnswersCard = isExpandedView()
        ? `<div class="col-12">

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

           </div>`
        : ``;

    document.getElementById("live-area").innerHTML = `
        <div class="row g-4">
            ${questionCard}
            ${liveAnswersCard}
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

function startLivePolling(delay = 10000) {
    if (livePollingInterval !== null) {
        clearInterval(livePollingInterval);
    }

    livePollingInterval = setInterval(loadResults, delay);
}

async function connectRealtime() {
    setConnectionStatus("connecting");

    try {
        const response = await fetch("api/realtime-config.php", { cache: "no-store" });
        const config = await response.json();

        if (!config.ok) {
            setConnectionStatus("polling");
            startLivePolling(2000);
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
            const topic = "realtime:brainbananas-live-" + code;

            send(topic, "phx_join", {
                config: {
                    postgres_changes: [
                        {
                            event: "*",
                            schema: "public",
                            table: "brainbananas_sessions",
                            filter: "code=eq." + code
                        },
                        {
                            event: "*",
                            schema: "public",
                            table: "brainbananas_answers",
                            filter: "session_code=eq." + code
                        },
                        {
                            event: "*",
                            schema: "public",
                            table: "brainbananas_players",
                            filter: "session_code=eq." + code
                        }
                    ],
                    broadcast: { self: false },
                    presence: { key: "" }
                },
                access_token: config.anon_key
            });

            setConnectionStatus("websocket");
            startLivePolling(10000);

            setInterval(() => {
                if (socket.readyState === WebSocket.OPEN) {
                    send("phoenix", "heartbeat", {});
                }
            }, 25000);
        });

        socket.addEventListener("message", (event) => {
            const message = JSON.parse(event.data);

            if (message.event === "postgres_changes") {
                loadResults();
            }
        });

        socket.addEventListener("close", () => {
            setConnectionStatus("polling");
            startLivePolling(2000);
        });

        socket.addEventListener("error", () => {
            setConnectionStatus("polling");
            startLivePolling(2000);
        });
    } catch (error) {
        console.error(error);
        setConnectionStatus("polling");
        startLivePolling(2000);
    }
}

connectRealtime();

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
