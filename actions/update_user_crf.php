<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../user/pengajuan_saya.php');
    exit;
}

verifyCsrf();

$id = (int) ($_POST['id'] ?? 0);
$_SESSION['flash'] = [
    'type' => 'warning',
    'message' => 'Post Implementation Review hanya dapat dikirim melalui halaman Post Implementation Review saat CRF menunggu pengisian.'
];

header(
    $id > 0
        ? 'Location: ../user/detail.php?id=' . $id
        : 'Location: ../user/pengajuan_saya.php'
);
exit;
