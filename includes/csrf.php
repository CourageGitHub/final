<?php
/**
 * CSRF protection.
 * Every <form method="post"> must include <?= csrf_field() ?>.
 * Every POST handler must call csrf_verify() before touching the database.
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submitted) || $expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(419);
        die('Your session expired or the form was resubmitted. Go back and try again.');
    }
}
