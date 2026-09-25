<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pak_joko/index.php');
    exit;
}
verifyCsrf();

$pdo = getConnection();
$user = getCurrentUser();
$id = (int) ($_POST['id'] ?? 0);
$note = trim($_POST['approval_note'] ?? '');

if ($id <= 0) {
    $_SESSION['flash'] = ['type'=>'danger','message'=>'CRF tidak valid.'];
    header('Location: ../pak_joko/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, workflow_stage FROM change_requests WHERE id=:id LIMIT 1");
$stmt->execute(['id'=>$id]);
$crf = $stmt->fetch();
if (!$crf || $crf['workflow_stage'] !== 'PAK_JOKO') {
    $_SESSION['flash'] = ['type'=>'danger','message'=>'CRF tidak tersedia untuk approval.'];
    header('Location: ../pak_joko/index.php');
    exit;
}

try {
    $pdo->beginTransaction();
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE change_requests SET pak_joko_approved_by=:approved_by, pak_joko_approved_at=:approved_at, pak_joko_approval_note=:approval_note, workflow_stage='CMO_FINAL', status='Dalam Proses' WHERE id=:id AND workflow_stage='PAK_JOKO'");
    $stmt->execute([
        'approved_by'=>$user['id'],
        'approved_at'=>$now,
        'approval_note'=>$note !== '' ? $note : null,
        'id'=>$id,
    ]);

    $actor = !empty($user['nama']) ? $user['nama'] : $user['userid'];
    logCrfActivity($pdo, $id, 'Approval Kepala Departemen Operasional', $note !== '' ? 'Kepala Departemen Operasional menyetujui CRF. Catatan: ' . $note : 'Kepala Departemen Operasional menyetujui hasil penanganan CRF.', $actor);
    $pdo->commit();
    $_SESSION['flash'] = ['type'=>'success','message'=>'CRF berhasil di-approve dan diteruskan ke CMO untuk finalisasi.'];
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('pak_joko_approve error: ' . $e->getMessage());
    $_SESSION['flash'] = ['type'=>'danger','message'=>'Terjadi kesalahan saat approval.'];
}

header('Location: ../pak_joko/index.php');
exit;
