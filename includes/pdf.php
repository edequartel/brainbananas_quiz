<?php

use Dompdf\Dompdf;
use Dompdf\Options;

function brainbananas_stream_pdf(string $html, string $filename): void
{
    require_once __DIR__ . '/../vendor/autoload.php';

    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}

function brainbananas_pdf_document(string $title, string $body): string
{
    return '<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
    <style>
        @page { margin: 22px; }
        body {
            color: #182433;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            line-height: 1.35;
        }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 18px 0 8px; }
        .muted { color: #667382; }
        .summary {
            border-collapse: collapse;
            margin: 16px 0;
            width: 100%;
        }
        .summary td {
            border: 1px solid #d9dee3;
            padding: 8px;
            text-align: center;
            width: 25%;
        }
        .summary .value {
            display: block;
            font-size: 20px;
            font-weight: bold;
        }
        table {
            border-collapse: collapse;
            margin-bottom: 14px;
            width: 100%;
        }
        th,
        td {
            border: 1px solid #d9dee3;
            padding: 5px;
            text-align: left;
            vertical-align: top;
        }
        th { background: #fff7d6; }
        .badge {
            border-radius: 4px;
            display: inline-block;
            font-weight: bold;
            padding: 2px 5px;
        }
        .good { background: #d1fae5; color: #047857; }
        .bad { background: #fee2e2; color: #b91c1c; }
        .neutral { background: #e5e7eb; color: #374151; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>' . $body . '</body>
</html>';
}
