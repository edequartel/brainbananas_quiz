<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">

    <title>BrainBananas Leerling</title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <link
        href="tabler/core/dist/css/tabler.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-yellow-lt">

<div class="page page-center">
<div class="container container-tight py-4">

    <div class="text-center mb-4">

        <div class="display-1 mb-3">
            🍌
        </div>

        <h1 class="display-5">
            BrainBananas
        </h1>

        <div class="text-secondary fs-3 mt-2">
            Doe mee met de live quiz
        </div>

    </div>

    <div class="card">

        <div class="card-body">

            <form action="api/join.php" method="post">

                <div class="mb-4">

                    <label class="form-label fs-4 fw-bold">
                        Je naam
                    </label>

                    <input
                        type="text"
                        name="student"
                        class="form-control form-control-lg"
                        placeholder="Vul je naam in"
                        autocomplete="name"
                        required
                    >

                </div>

                <div class="mb-4">

                    <label class="form-label fs-4 fw-bold">
                        Sessiecode
                    </label>

                    <input
                        type="text"
                        name="code"
                        class="form-control form-control-lg text-uppercase text-center fw-bold"
                        placeholder="ABC123"
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        required
                    >

                </div>

                <button class="btn btn-yellow btn-lg w-100">
                    Meedoen
                </button>

            </form>

        </div>

    </div>

</div>
</div>

<script src="tabler/core/dist/js/tabler.min.js"></script>

</body>
</html>
