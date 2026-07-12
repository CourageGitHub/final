<?php
/**
 * Small general-purpose helpers used across every page.
 */

declare(strict_types=1);

/** Escape a value for safe HTML output (XSS prevention). Use this EVERY time
 *  you print anything that came from the database or user input. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Set a one-time flash message: flash('error', 'Something went wrong');
 *  Read (and clear) it later:                flash('error'); */
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

/** Repopulate a form field after a failed submission, e.g. value="<?= old('email') ?>" */
function old(string $key): string
{
    return e($_SESSION['old'][$key] ?? '');
}

function set_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
