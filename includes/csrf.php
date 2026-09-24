<?php
/**
 * includes/csrf.php
 * Perlindungan CSRF sederhana berbasis token session.
 */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function verifyCsrf(): void
{
    $sent  = $_POST['csrf_token'] ?? '';
    $token = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($sent)
        || !is_string($token)
        || $token === ''
        || !hash_equals($token, $sent)
    ) {
        http_response_code(403);
        exit(
            'Permintaan tidak valid (token keamanan tidak cocok atau sudah kedaluwarsa). '
            . 'Silakan kembali, muat ulang halaman, lalu coba lagi.'
        );
    }
}