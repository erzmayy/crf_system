<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireCrfRole(['pemohon']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../user/pir.php');
    exit;
}

verifyCsrf();

$pdo = getConnection();
$user = getCurrentUser();
$id = (int) ($_POST['id'] ?? 0);
$pirDate = trim($_POST['pir_date'] ?? '');
$pir = trim($_POST['post_implementation_review'] ?? '');

if ($id <= 0 || $pir === '') {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'CRF, Tanggal Post Implementation Review, dan hasil review wajib diisi.'
    ];
    header('Location: ../user/pir.php');
    exit;
}

$parsedPirDate = DateTime::createFromFormat('!Y-m-d', $pirDate);
$pirDateErrors = DateTime::getLastErrors();
$isValidPirDate = $parsedPirDate !== false
    && $parsedPirDate->format('Y-m-d') === $pirDate
    && (
        $pirDateErrors === false
        || (
            $pirDateErrors['warning_count'] === 0
            && $pirDateErrors['error_count'] === 0
        )
    );

if (!$isValidPirDate) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Tanggal Post Implementation Review wajib diisi dengan tanggal yang valid.'
    ];
    header('Location: ../user/pir.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id
        FROM change_requests
        WHERE id = :id
          AND user_id = :user_id
          AND workflow_stage = 'PEMOHON_PIR'
        FOR UPDATE
    ");
    $stmt->execute([
        'id' => $id,
        'user_id' => $user['id'],
    ]);

    if (!$stmt->fetch()) {
        $pdo->rollBack();
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'CRF tidak ditemukan pada antrean Post Implementation Review Anda.'
        ];
        header('Location: ../user/pir.php');
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE change_requests
        SET
            pir_date = :pir_date,
            post_implementation_review = :pir,
            workflow_stage = 'CMO_FINAL'
        WHERE id = :id
          AND user_id = :user_id
          AND workflow_stage = 'PEMOHON_PIR'
    ");
    $stmt->execute([
        'pir_date' => $parsedPirDate->format('Y-m-d'),
        'pir' => $pir,
        'id' => $id,
        'user_id' => $user['id'],
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('CRF sudah tidak tersedia untuk pengisian Post Implementation Review.');
    }

    $actor = !empty($user['nama']) ? $user['nama'] : $user['userid'];
    logCrfActivity(
        $pdo,
        $id,
        'Pemohon - Post Implementation Review Dikirim',
        'Pemohon mengirim Tanggal dan hasil Post Implementation Review. CRF diteruskan ke CMO untuk finalisasi.',
        $actor
    );

    $pdo->commit();
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'Post Implementation Review berhasil dikirim. CRF diteruskan ke CMO untuk finalisasi.'
    ];
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('submit_pir error: ' . $e->getMessage());
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Terjadi kesalahan saat mengirim Post Implementation Review.'
    ];
}

header('Location: ../user/pir.php');
exit;
