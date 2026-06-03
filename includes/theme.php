<?php

if (!function_exists('brainbananas_theme_head')) {
    function brainbananas_theme_head(): void
    {
        ?>
        <script>
        (function () {
            const theme = localStorage.getItem("brainbananasTheme") || "normal";
            const fontSize = localStorage.getItem("brainbananasFontSize") || "small";
            const fontWeight = localStorage.getItem("brainbananasFontWeight") || "normal";
            document.documentElement.dataset.bbTheme = theme;
            document.documentElement.dataset.bbFontSize = fontSize;
            document.documentElement.dataset.bbFontWeight = fontWeight;
        })();
        </script>
        <style>
            :root {
                --bb-page-bg: #fff7d6;
                --bb-card-bg: #ffffff;
                --bb-text: #182433;
                --bb-muted: #667382;
                --bb-border: #d9dee3;
                --bb-accent: #f5b700;
                --bb-accent-text: #182433;
                --bb-font-scale: 1;
                --bb-font-weight: 400;
                --bb-content-max-width: 960px;
            }

            html[data-bb-theme="large"] {
                --bb-page-bg: #eaf4ff;
                --bb-card-bg: #ffffff;
                --bb-text: #102a43;
                --bb-muted: #3d5872;
                --bb-border: #8ec5ff;
                --bb-accent: #0b74de;
                --bb-accent-text: #ffffff;
            }

            html[data-bb-theme="contrast"] {
                --bb-page-bg: #000000;
                --bb-card-bg: #111111;
                --bb-text: #ffffff;
                --bb-muted: #ffff66;
                --bb-border: #ffff00;
                --bb-accent: #ffff00;
                --bb-accent-text: #000000;
            }

            html[data-bb-theme="dark"] {
                --bb-page-bg: #101827;
                --bb-card-bg: #172235;
                --bb-text: #f8fafc;
                --bb-muted: #c7d2fe;
                --bb-border: #5eead4;
                --bb-accent: #5eead4;
                --bb-accent-text: #0f172a;
            }

            html[data-bb-font-size="small"] {
                --bb-font-scale: 1;
            }

            html[data-bb-font-size="large"] {
                --bb-font-scale: 1.18;
            }

            html[data-bb-font-size="largest"] {
                --bb-font-scale: 1.34;
            }

            html[data-bb-font-weight="bold"] {
                --bb-font-weight: 700;
            }

            html {
                font-size: calc(16px * var(--bb-font-scale));
            }

            .container-tight {
                max-width: var(--bb-content-max-width);
            }

            body,
            body.bg-yellow-lt,
            body.bg-light {
                background: var(--bb-page-bg) !important;
                color: var(--bb-text);
                font-weight: var(--bb-font-weight);
            }

            input,
            select,
            textarea,
            .form-control,
            .form-select,
            .form-check-label,
            .list-group-item,
            .table {
                font-weight: var(--bb-font-weight);
            }

            .card,
            .modal-content,
            .dropdown-menu,
            .list-group-item,
            .form-selectgroup-label,
            .form-control,
            .form-select,
            textarea,
            .table {
                background-color: var(--bb-card-bg);
                border-color: var(--bb-border);
                color: var(--bb-text);
            }

            .form-control::placeholder {
                color: var(--bb-muted);
                opacity: 1;
            }

            .table,
            .table thead th,
            .table tbody td {
                color: var(--bb-text);
                border-color: var(--bb-border);
            }

            .text-secondary,
            .form-label,
            .form-hint,
            .text-muted {
                color: var(--bb-muted) !important;
            }

            .btn-outline-secondary,
            .btn-outline-primary {
                border-color: var(--bb-border);
                color: var(--bb-text);
            }

            .alert {
                border-color: var(--bb-border);
            }

            html[data-bb-theme="contrast"] .alert,
            html[data-bb-theme="dark"] .alert {
                background-color: var(--bb-card-bg);
                color: var(--bb-text);
            }

            .btn-yellow,
            .bg-yellow,
            .progress-bar.bg-yellow {
                background-color: var(--bb-accent) !important;
                border-color: var(--bb-accent) !important;
                color: var(--bb-accent-text) !important;
            }

            .text-yellow {
                color: var(--bb-accent) !important;
            }

            .border-yellow {
                border-color: var(--bb-accent) !important;
            }

            .bb-theme-picker {
                display: flex;
                flex-wrap: wrap;
                gap: .5rem;
                justify-content: flex-end;
                align-items: center;
                margin-bottom: 1rem;
            }

            .bb-theme-picker__label {
                color: var(--bb-muted);
                font-weight: 700;
            }

            .bb-theme-button {
                border: 2px solid var(--bb-border);
                border-radius: .375rem;
                background: var(--bb-card-bg);
                color: var(--bb-text);
                font-weight: 700;
                padding: .45rem .7rem;
                min-height: 2.5rem;
            }

            .bb-theme-button[data-theme="normal"] {
                border-color: #f5b700;
                background: #fff7d6;
                color: #182433;
            }

            .bb-theme-button[data-theme="large"] {
                border-color: #0b74de;
                background: #eaf4ff;
                color: #102a43;
                font-size: 1.1em;
            }

            .bb-theme-button[data-theme="contrast"] {
                border-color: #ffff00;
                background: #000000;
                color: #ffff00;
                font-size: 1.1em;
            }

            .bb-theme-button[data-theme="dark"] {
                border-color: #5eead4;
                background: #101827;
                color: #ffffff;
            }

            .bb-theme-button[data-font-size="small"] {
                font-size: 1em;
            }

            .bb-theme-button[data-font-size="large"] {
                font-size: 1.15em;
            }

            .bb-theme-button[data-font-size="largest"] {
                font-size: 1.3em;
            }

            .bb-theme-button[aria-pressed="true"] {
                outline: 3px solid var(--bb-accent);
                outline-offset: 2px;
                box-shadow: 0 0 0 .15rem rgba(0, 0, 0, .15);
            }

            @media (max-width: 575.98px) {
                .bb-theme-picker {
                    justify-content: center;
                }

                .bb-theme-picker__label {
                    flex-basis: 100%;
                    text-align: center;
                }
            }
        </style>
        <script>
        document.addEventListener("DOMContentLoaded", () => {
            const themeButtons = document.querySelectorAll("[data-bb-theme-button]");
            const fontSizeButtons = document.querySelectorAll("[data-bb-font-size-button]");
            const fontWeightButton = document.querySelector("[data-bb-font-weight-button]");

            function applyTheme(theme) {
                document.documentElement.dataset.bbTheme = theme;
                localStorage.setItem("brainbananasTheme", theme);

                themeButtons.forEach((button) => {
                    button.setAttribute(
                        "aria-pressed",
                        button.dataset.theme === theme ? "true" : "false"
                    );
                });
            }

            function applyFontSize(fontSize) {
                document.documentElement.dataset.bbFontSize = fontSize;
                localStorage.setItem("brainbananasFontSize", fontSize);

                fontSizeButtons.forEach((button) => {
                    button.setAttribute(
                        "aria-pressed",
                        button.dataset.fontSize === fontSize ? "true" : "false"
                    );
                });
            }

            function applyFontWeight(fontWeight) {
                document.documentElement.dataset.bbFontWeight = fontWeight;
                localStorage.setItem("brainbananasFontWeight", fontWeight);

                if (fontWeightButton) {
                    fontWeightButton.setAttribute(
                        "aria-pressed",
                        fontWeight === "bold" ? "true" : "false"
                    );
                    fontWeightButton.textContent = fontWeight === "bold" ? "Vet" : "Normaal";
                }
            }

            themeButtons.forEach((button) => {
                button.addEventListener("click", () => applyTheme(button.dataset.theme));
            });

            fontSizeButtons.forEach((button) => {
                button.addEventListener("click", () => applyFontSize(button.dataset.fontSize));
            });

            if (fontWeightButton) {
                fontWeightButton.addEventListener("click", () => {
                    const current = document.documentElement.dataset.bbFontWeight || "normal";
                    applyFontWeight(current === "bold" ? "normal" : "bold");
                });
            }

            applyTheme(localStorage.getItem("brainbananasTheme") || "normal");
            applyFontSize(localStorage.getItem("brainbananasFontSize") || "small");
            applyFontWeight(localStorage.getItem("brainbananasFontWeight") || "normal");
        });
        </script>
        <?php
    }
}

if (!function_exists('brainbananas_theme_picker')) {
    function brainbananas_theme_picker(): void
    {
        ?>
        <div class="bb-theme-picker d-print-none" aria-label="Thema kiezen">
            <button class="bb-theme-button" type="button" data-theme="normal" data-bb-theme-button aria-pressed="false">
                Normaal
            </button>
            <button class="bb-theme-button" type="button" data-theme="large" data-bb-theme-button aria-pressed="false">
                Helder
            </button>
            <button class="bb-theme-button" type="button" data-theme="contrast" data-bb-theme-button aria-pressed="false">
                Hoog contrast
            </button>
            <button class="bb-theme-button" type="button" data-theme="dark" data-bb-theme-button aria-pressed="false">
                Donker
            </button>
            <button class="bb-theme-button" type="button" data-font-size="small" data-bb-font-size-button aria-pressed="false">
                Klein
            </button>
            <button class="bb-theme-button" type="button" data-font-size="large" data-bb-font-size-button aria-pressed="false">
                Groot
            </button>
            <button class="bb-theme-button" type="button" data-font-size="largest" data-bb-font-size-button aria-pressed="false">
                Grootst
            </button>
            <button class="bb-theme-button" type="button" data-bb-font-weight-button aria-pressed="false">
                Normaal
            </button>
        </div>
        <?php
    }
}
