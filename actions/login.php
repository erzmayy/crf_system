<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$userid = trim($_POST['userid'] ?? '');
$password = $_POST['password'] ?? '';

if ($userid === '' || $password === '') {
    $_SESSION['login_error'] = 'User ID dan password wajib diisi.';
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

$stmt = $pdo->prepare(
    'SELECT
        id,
        userid,
        nama,
        no_wa,
        email,
        password,
        password_new,
        dept,
        divisi
     FROM users
     WHERE userid = :userid
     LIMIT 1'
);

$stmt->execute([
    'userid' => $userid
]);

$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['login_error'] = 'User ID atau password salah.';
    header('Location: ../login.php');
    exit;
}

// Buat session login baru.
session_regenerate_id(true);

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['active_user'] = $user;

$role = getCrfRole();

if ($role === 'admin') {
    header('Location: ../admin/dashboard.php');
} else {
    header('Location: ../user/dashboard.php');
}

exit;