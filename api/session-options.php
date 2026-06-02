<?php

function brainbananas_session_options_dir(): string
{
    return __DIR__ . '/../session-options';
}

function brainbananas_default_session_options(): array
{
    return [
        'show_answer_feedback' => false,
        'skipped_questions' => []
    ];
}

function brainbananas_read_session_options(string $code): array
{
    $code = strtoupper(trim($code));
    $options = brainbananas_default_session_options();

    if ($code === '') {
        return $options;
    }

    $path = brainbananas_session_options_dir() . '/' . $code . '.json';

    if (!file_exists($path)) {
        return $options;
    }

    $storedOptions = json_decode(file_get_contents($path), true);

    if (!is_array($storedOptions)) {
        return $options;
    }

    return array_merge($options, $storedOptions);
}

function brainbananas_skipped_questions(array $options): array
{
    $skippedQuestions = $options['skipped_questions'] ?? [];

    if (!is_array($skippedQuestions)) {
        return [];
    }

    $skippedQuestions = array_map('intval', $skippedQuestions);
    $skippedQuestions = array_values(array_unique($skippedQuestions));
    sort($skippedQuestions);

    return $skippedQuestions;
}

function brainbananas_skip_session_question(string $code, int $questionIndex): void
{
    $options = brainbananas_read_session_options($code);
    $skippedQuestions = brainbananas_skipped_questions($options);
    $skippedQuestions[] = $questionIndex;
    $skippedQuestions = array_values(array_unique($skippedQuestions));
    sort($skippedQuestions);

    $options['skipped_questions'] = $skippedQuestions;

    brainbananas_write_session_options($code, $options);
}

function brainbananas_write_session_options(string $code, array $options): void
{
    $code = strtoupper(trim($code));

    if ($code === '') {
        return;
    }

    $dir = brainbananas_session_options_dir();

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $options = array_merge(brainbananas_default_session_options(), $options);
    $path = $dir . '/' . $code . '.json';

    file_put_contents(
        $path,
        json_encode($options, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}
