<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireCrfRole(['pemohon']);

$pdo = getConnection();
$user = getCurrentUser();
$stmt = $pdo->prepare("
    SELECT
        id,
        request_number,
        full_name,
        change_category,
        submission_date,
        implementation_date
    FROM change_requests
    WHERE user_id = :user_id
      AND workflow_stage = 'PEMOHON_PIR'
    ORDER BY id DESC
");
$stmt->execute(['user_id' => $user['id']]);
$crfs = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$pageTitle = 'Post Implementation Review';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="crf-page crf-helpdesk-page pt-4">
    <div class="container">
        <?php if ($flash): ?>
            <div class="alert alert-<?= h($flash['type']) ?> crf-alert" role="alert">
                <?= h($flash['message']) ?>
            </div>
        <?php endif; ?>

        <div class="crf-page-header mb-4">
            <h1>Post Implementation Review</h1>
            <p class="text-muted mb-0">
                Pilih CRF yang ingin ditinjau. Form Post Implementation Review akan dibuka setelah Anda memilih satu CRF.
            </p>
        </div>

        <?php if (!$crfs): ?>
            <div class="crf-empty-state">
                <div class="crf-empty-icon"><i class="bi bi-clipboard-check"></i></div>
                <h2>Tidak ada CRF yang menunggu Post Implementation Review</h2>
                <p>CRF akan muncul di sini setelah Otomasi menyelesaikan implementasi.</p>
                <a href="pengajuan_saya.php" class="btn btn-crf-outline">Lihat Pengajuan Saya</a>
            </div>
        <?php else: ?>
            <div class="alert alert-warning crf-alert" role="status">
                <i class="bi bi-bell-fill"></i>
                Ada <strong><?= count($crfs) ?></strong> CRF yang menunggu Post Implementation Review Anda.
            </div>

            <div class="card pengajuan-list-card">
                <div class="card-body">
                    <div class="pengajuan-list-heading">
                        <div>
                            <h2>CRF Menunggu Post Implementation Review</h2>
                            <p>Pilih satu CRF untuk melihat hasil implementasi dan mengisi Post Implementation Review.</p>
                        </div>
                        <span class="pengajuan-result-pill">
                            <i class="bi bi-clipboard-check"></i>
                            <?= count($crfs) ?> menunggu
                        </span>
                    </div>

                    <div class="pir-list" role="list">
                        <div class="pir-list-header" aria-hidden="true">
                            <span>Nomor Register</span>
                            <span>Kategori</span>
                            <span>Tanggal Pengajuan</span>
                            <span>Tanggal Implementasi</span>
                            <span>Aksi</span>
                        </div>
                        <?php foreach ($crfs as $crf): ?>
                            <article class="pir-list-row" role="listitem">
                                <div class="pir-list-cell" data-label="Nomor Register">
                                    <strong><?= h($crf['request_number'] ?: 'CRF #' . $crf['id']) ?></strong>
                                </div>
                                <div class="pir-list-cell" data-label="Kategori">
                                    <?= h($crf['change_category'] ?? '-') ?>
                                </div>
                                <div class="pir-list-cell" data-label="Tanggal Pengajuan">
                                    <?= !empty($crf['submission_date'])
                                        ? h(date('d-m-Y', strtotime($crf['submission_date'])))
                                        : '-' ?>
                                </div>
                                <div class="pir-list-cell" data-label="Tanggal Implementasi">
                                    <?= !empty($crf['implementation_date'])
                                        ? h(date('d-m-Y', strtotime($crf['implementation_date'])))
                                        : '-' ?>
                                </div>
                                <div class="pir-list-cell pir-list-action" data-label="Aksi">
                                    <a
                                        href="pir_detail.php?id=<?= (int) $crf['id'] ?>"
                                        class="btn btn-crf-primary"
                                    >
                                        <i class="bi bi-pencil-square"></i> Isi Post Implementation Review
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
