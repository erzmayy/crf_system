<?php
/**
 * includes/session.php
 * ---------------------------------------------------------------
 * Menangani session user yang sudah login.
 * Data user diambil dari tabel users berdasarkan user_id
 * yang tersimpan di session.
 * ---------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// ID user yang disimulasikan sedang login (lihat brief butir 5 & 9).
// const SIMULATED_USER_ID = 1;

/**
 * Mengambil data user aktif dari database.
 * Menyimpan hasilnya ke $_SESSION supaya tidak query berulang-ulang.
 */
function getCurrentUser(): array
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }

    if (!isset($_SESSION['active_user'])) {
        $pdo = getConnection();

        $stmt = $pdo->prepare(
            'SELECT * FROM users WHERE id = :id LIMIT 1'
        );

        $stmt->execute([
            'id' => $_SESSION['user_id']
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            $_SESSION = [];
            session_destroy();

            header('Location: ../login.php');
            exit;
        }

        $_SESSION['active_user'] = $user;
    }

    return $_SESSION['active_user'];
}

/**
 * Helper kecil untuk escape output (mencegah XSS).
 */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/csrf.php';