<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireCrfRole(['pemohon']);

$pdo = getConnection();
$user = getCurrentUser();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        id,
        request_number,
        full_name,
        change_category,
        submission_date,
        implementation_date,
        implementation
    FROM change_requests
    WHERE id = :id
      AND user_id = :user_id
      AND workflow_stage = 'PEMOHON_PIR'
    LIMIT 1
");
$stmt->execute([
    'id' => $id,
    'user_id' => $user['id'],
]);
$crf = $stmt->fetch();

if (!$crf) {
    http_response_code(404);
    $pageTitle = 'CRF Tidak Ditemukan';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="crf-page">
        <div class="container">
            <div class="alert alert-warning" role="alert">
                CRF tidak ditemukan pada daftar Post Implementation Review yang menunggu Anda.
            </div>
            <a href="pir.php" class="btn btn-crf-outline">
                <i class="bi bi-arrow-left"></i> Kembali ke Daftar Post Implementation Review
            </a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$pageTitle = 'Isi Post Implementation Review - ' . ($crf['request_number'] ?: 'CRF #' . $crf['id']);

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
            <h1>Isi Post Implementation Review</h1>
            <p class="text-muted mb-0">
                Tinjau detail implementasi berikut sebelum mengirim Post Implementation Review.
            </p>
        </div>

        <section class="crf-section crf-detail-card mb-4">
            <div class="crf-section-header">
                <span class="crf-section-number"><i class="bi bi-clipboard-check"></i></span>
                <h2><?= h($crf['request_number'] ?: 'CRF #' . $crf['id']) ?></h2>
            </div>
            <div class="crf-section-body">
                <div class="crf-info-rows mb-4">
                    <div class="crf-info-row">
                        <span class="crf-info-label">Pemohon</span>
                        <div class="crf-info-value"><?= h($crf['full_name'] ?? '-') ?></div>
                    </div>
                    <div class="crf-info-row">
                        <span class="crf-info-label">Kategori</span>
                        <div class="crf-info-value"><?= h($crf['change_category'] ?? '-') ?></div>
                    </div>
                    <div class="crf-info-row">
                        <span class="crf-info-label">Tanggal Implementasi</span>
                        <div class="crf-info-value">
                            <?= !empty($crf['implementation_date'])
                                ? h(date('d-m-Y', strtotime($crf['implementation_date'])))
                                : '<span class="text-muted">Belum diisi.</span>' ?>
                        </div>
                    </div>
                    <div class="crf-info-row crf-request-row-long">
                        <span class="crf-info-label">Implementasi / Hasil Perubahan</span>
                        <div class="crf-info-value">
                            <?= !empty($crf['implementation'])
                                ? nl2br(h($crf['implementation']))
                                : '<span class="text-muted">Belum diisi.</span>' ?>
                        </div>
                    </div>
                </div>

                <form method="POST" action="../actions/submit_pir.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $crf['id'] ?>">

                    <div class="mb-3">
                        <label for="pir_date" class="form-label fw-semibold">
                            Tanggal Post Implementation Review <span class="text-danger">*</span>
                        </label>
                        <input
                            type="date"
                            id="pir_date"
                            name="pir_date"
                            class="form-control"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="post_implementation_review" class="form-label fw-semibold">
                            Post Implementation Review <span class="text-danger">*</span>
                        </label>
                        <textarea
                            id="post_implementation_review"
                            name="post_implementation_review"
                            class="form-control"
                            rows="6"
                            required
                            placeholder="Tuliskan hasil evaluasi setelah perubahan diterapkan..."
                        ></textarea>
                    </div>

                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <a href="pir.php" class="btn btn-crf-outline">
                            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Post Implementation Review
                        </a>
                        <button type="submit" class="btn btn-crf-primary">
                            <i class="bi bi-send"></i> Kirim Post Implementation Review
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
