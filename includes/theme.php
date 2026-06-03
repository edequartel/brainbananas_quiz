<?php

if (!function_exists('brainbananas_theme_head')) {
    function brainbananas_theme_head(): void
    {
        ?>
        <script>
        (function () {
            const theme = localStorage.getItem("brainbananasTheme") || "normal";
            document.documentElement.dataset.bbTheme = theme;
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
                --bb-font-scale: 1.18;
            }

            html[data-bb-theme="contrast"] {
                --bb-page-bg: #000000;
                --bb-card-bg: #111111;
                --bb-text: #ffffff;
                --bb-muted: #ffff66;
                --bb-border: #ffff00;
                --bb-accent: #ffff00;
                --bb-accent-text: #000000;
                --bb-font-scale: 1.24;
            }

            html[data-bb-theme="dark"] {
                --bb-page-bg: #101827;
                --bb-card-bg: #172235;
                --bb-text: #f8fafc;
                --bb-muted: #c7d2fe;
                --bb-border: #5eead4;
                --bb-accent: #5eead4;
                --bb-accent-text: #0f172a;
                --bb-font-scale: 1.12;
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
            const buttons = document.querySelectorAll("[data-bb-theme-button]");

            function applyTheme(theme) {
                document.documentElement.dataset.bbTheme = theme;
                localStorage.setItem("brainbananasTheme", theme);

                buttons.forEach((button) => {
                    button.setAttribute(
                        "aria-pressed",
                        button.dataset.theme === theme ? "true" : "false"
                    );
                });
            }

            buttons.forEach((button) => {
                button.addEventListener("click", () => applyTheme(button.dataset.theme));
            });

            applyTheme(localStorage.getItem("brainbananasTheme") || "normal");
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
        </div>
        <?php
    }
}
