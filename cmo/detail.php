<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getConnection();

$id = (int) ($_GET['id'] ?? 0);


/*
 * =========================================================
 * AMBIL DATA CRF
 * =========================================================
 */
$stmt = $pdo->prepare("
    SELECT cr.*
    FROM change_requests cr
    WHERE cr.id = :id
      AND cr.workflow_stage IN ('CMO_FILTER', 'CMO_FINAL')
    LIMIT 1
");

$stmt->execute([
    'id' => $id
]);

$crf = $stmt->fetch();


if (!$crf) {

    http_response_code(404);

    $pageTitle = 'CRF Tidak Ditemukan';

    require_once __DIR__ . '/../includes/header.php';

    echo '
        <div class="crf-page">
            <div class="container">
                <div class="alert alert-danger">
                    CRF tidak ditemukan pada antrean CMO.
                </div>

                <a href="index.php" class="btn btn-crf-outline">
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>
            </div>
        </div>
    ';

    require_once __DIR__ . '/../includes/footer.php';

    exit;
}


/*
 * =========================================================
 * ATTACHMENTS
 * =========================================================
 */
$attStmt = $pdo->prepare("
    SELECT *
    FROM attachments
    WHERE change_request_id = :id
    ORDER BY uploaded_at ASC
");

$attStmt->execute([
    'id' => $id
]);

$attachments = $attStmt->fetchAll();


/*
 * =========================================================
 * TIMELINE
 * =========================================================
 */
$logStmt = $pdo->prepare("
    SELECT
        activity,
        description,
        actor,
        created_at
    FROM crf_activity_logs
    WHERE change_request_id = :id
    ORDER BY created_at ASC, id ASC
");

$logStmt->execute([
    'id' => $id
]);

$timeline = $logStmt->fetchAll();


/*
 * =========================================================
 * FLASH MESSAGE
 * =========================================================
 */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);


$pageTitle = 'CMO - Detail CRF';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* =========================================================
   CMO DETAIL - COMPACT INFORMATION LAYOUT
   ========================================================= */
#cmo-detail-page .crf-section-body {
    padding: 1.25rem 1.4rem;
}

#cmo-detail-page .cmo-compact-grid {
    width: 100%;
    max-width: 960px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    column-gap: 34px;
    row-gap: 24px;
    align-items: start;
}

#cmo-detail-page .cmo-detail-item {
    min-width: 0;
}

#cmo-detail-page .crf-detail-label {
    margin-bottom: 6px;
}

#cmo-detail-page .crf-detail-value {
    margin-bottom: 0 !important;
    min-height: 0 !important;
    line-height: 1.45;
}

#cmo-detail-page .cmo-department {
    display: block;
    line-height: 1.45;
}

#cmo-detail-page .cmo-division {
    display: block;
    margin-top: 3px;
    font-size: 0.86rem;
    color: #64748b;
    line-height: 1.35;
}

#cmo-detail-page .cmo-status {
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
}

#cmo-detail-page .cmo-divider {
    margin: 1.15rem 0 1.2rem;
}

#cmo-detail-page .cmo-sla-grid {
    width: 100%;
    max-width: 960px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    column-gap: 34px;
    row-gap: 20px;
    align-items: start;
}

#cmo-detail-page .cmo-sla-item {
    min-width: 0;
}

#cmo-detail-page .cmo-sla-value {
    display: inline-flex;
    align-items: baseline;
    gap: 6px;
    white-space: nowrap;
    line-height: 1.45;
}

#cmo-detail-page .cmo-sla-unit {
    font-size: 0.95rem;
    color: #475569;
}

#cmo-detail-page .cmo-muted {
    color: #94a3b8;
}

@media (max-width: 900px) {
    #cmo-detail-page .cmo-compact-grid,
    #cmo-detail-page .cmo-sla-grid {
        max-width: 100%;
        column-gap: 22px;
    }
}

@media (max-width: 768px) {
    #cmo-detail-page .cmo-compact-grid,
    #cmo-detail-page .cmo-sla-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 24px;
    }
}

@media (max-width: 520px) {
    #cmo-detail-page .cmo-compact-grid,
    #cmo-detail-page .cmo-sla-grid {
        grid-template-columns: 1fr;
    }
}
</style>


<div class="crf-page" id="cmo-detail-page">

    <div class="container">


        <!-- =========================================================
             HEADER
             ========================================================= -->
        <div class="crf-page-header d-flex justify-content-between align-items-start gap-2 flex-wrap">

            <div>

                <h1>
                    Detail CRF - CMO
                </h1>

                <p class="mb-0">

                    Nomor Register:

                    <strong>
                        <?= h($crf['request_number'] ?? '-') ?>
                    </strong>

                </p>

            </div>


            <a
                href="index.php"
                class="btn btn-crf-outline"
            >
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>

        </div>


        <!-- =========================================================
             FLASH
             ========================================================= -->
        <?php if ($flash): ?>

            <div
                class="alert alert-<?= h($flash['type']) ?> crf-alert"
                role="alert"
            >
                <?= h($flash['message']) ?>
            </div>

        <?php endif; ?>


        <!-- =========================================================
             STATUS
             ========================================================= -->
        <div class="d-flex gap-2 mb-4 flex-wrap">

            <span class="crf-badge <?= statusBadgeClass($crf['status']) ?>">

                Status:

                <?= h(statusLabel($crf['status'])) ?>

            </span>


            <span class="crf-badge <?= workflowStageBadgeClass($crf['workflow_stage']) ?>">

                Tahap:

                <?= h(workflowStageLabel($crf['workflow_stage'])) ?>

            </span>


            <span class="crf-badge <?= levelBadgeClass($crf['level']) ?>">

                Level:

                <?= h($crf['level'] ?? 'Belum ditentukan') ?>

            </span>


            <?php if (!empty($crf['sla_value']) && !empty($crf['sla_unit'])): ?>

                <span class="crf-badge badge-stage-pir">

                    SLA:

                    <?= h(
                        rtrim(
                            rtrim(
                                number_format(
                                    (float) $crf['sla_value'],
                                    2,
                                    '.',
                                    ''
                                ),
                                '0'
                            ),
                            '.'
                        )
                    ) ?>

                    <?= h($crf['sla_unit']) ?>

                </span>

            <?php endif; ?>

        </div>



        <!-- =========================================================
             1. INFORMASI PENGAJUAN
             ========================================================= -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">

                <span class="crf-section-number">
                    1
                </span>

                <h2>
                    Informasi Pengajuan
                </h2>

            </div>


            <div class="crf-section-body">

                <!-- DATA PENGAJU -->
                <div class="cmo-compact-grid">

                    <div class="cmo-detail-item">
                        <div class="crf-detail-label">PENGAJU</div>
                        <div class="crf-detail-value">
                            <?= h($crf['full_name'] ?? '-') ?>
                        </div>
                    </div>

                    <div class="cmo-detail-item">
                        <div class="crf-detail-label">EMAIL</div>
                        <div class="crf-detail-value">
                            <?= h($crf['email'] ?? '-') ?>
                        </div>
                    </div>

                    <div class="cmo-detail-item">
                        <div class="crf-detail-label">NO. HP / WA</div>
                        <div class="crf-detail-value">
                            <?= h($crf['phone'] ?? '-') ?>
                        </div>
                    </div>

                    <div class="cmo-detail-item">
                        <div class="crf-detail-label">NOMOR REGISTER</div>
                        <div class="crf-detail-value">
                            <?= h($crf['request_number'] ?? '-') ?>
                        </div>
                    </div>

                </div>

                <hr class="cmo-divider">

                <!-- INFORMASI CRF -->
                <div class="cmo-compact-grid">

                    <div class="cmo-detail-item">
                        <div class="crf-detail-label">HARI / TANGGAL</div>
                        <div class="crf-detail-value">
                            <?php if (!empty($crf['submission_date'])): ?>
                                <?= h(
                                    formatTanggalIndonesia(
                                        new DateTime($crf['submission_date'])
                                    )
                                ) ?>
                            <?php else: ?>
                                <span class="cmo-muted">Belum diajukan</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cmo-detail-item">
                        <div class="crf-detail-label">KEPADA</div>
                        <div class="crf-detail-value">
                            <span class="cmo-department">
                                <?= h($crf['to_department'] ?? '-') ?>
                            </span>
                            <?php if (!empty($crf['to_division'])): ?>
                                <span class="cmo-division">
                                    <?= h($crf['to_division']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cmo-detail-item">
                        <div class="crf-detail-label">DARI</div>
                        <div class="crf-detail-value">
                            <span class="cmo-department">
                                <?= h($crf['from_department'] ?? '-') ?>
                            </span>
                            <?php if (!empty($crf['from_division'])): ?>
                                <span class="cmo-division">
                                    <?= h($crf['from_division']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cmo-detail-item">
                        <div class="crf-detail-label">STATUS</div>
                        <div class="crf-detail-value">
                            <span class="cmo-status crf-badge <?= statusBadgeClass($crf['status'] ?? '') ?>">
                                <?= h(statusLabel($crf['status'] ?? '')) ?>
                            </span>
                        </div>
                    </div>

                </div>

            </div>
        </div>



        <!-- =========================================================
             2. DETAIL PERMINTAAN
             ========================================================= -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">

                <span class="crf-section-number">
                    2
                </span>

                <h2>
                    Detail Permintaan
                </h2>

            </div>


            <div class="crf-section-body">


                <div class="crf-detail-label">
                    Rincian Permohonan Perubahan
                </div>

                <div class="crf-detail-value mb-3">

                    <?= nl2br(
                        h(
                            $crf['change_description'] ?? '-'
                        )
                    ) ?>

                </div>


                <div class="crf-detail-label">
                    Benefit dari Perubahan yang Diharapkan
                </div>

                <div class="crf-detail-value mb-3">

                    <?= nl2br(
                        h(
                            $crf['benefit'] ?? '-'
                        )
                    ) ?>

                </div>


                <div class="crf-detail-label">
                    Dampak Jika Tidak Dilakukan Perubahan
                </div>

                <div class="crf-detail-value mb-3">

                    <?= nl2br(
                        h(
                            $crf['impact'] ?? '-'
                        )
                    ) ?>

                </div>


                <div class="crf-detail-label">
                    Alasan Permohonan Perubahan
                </div>

                <div class="crf-detail-value">

                    <?= nl2br(
                        h(
                            $crf['reason'] ?? '-'
                        )
                    ) ?>

                </div>


            </div>

        </div>



        <!-- =========================================================
             3. BUKTI DAN INFORMASI PENDUKUNG
             ========================================================= -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">

                <span class="crf-section-number">
                    3
                </span>

                <h2>
                    Bukti dan Informasi Pendukung
                </h2>

            </div>


            <div class="crf-section-body">


                <?php if (!$attachments): ?>

                    <span class="text-muted">
                        Tidak ada file yang dilampirkan.
                    </span>


                <?php else: ?>


                    <div class="list-group">

                        <?php foreach ($attachments as $file): ?>

                            <div class="list-group-item d-flex justify-content-between align-items-center gap-3">


                                <div class="d-flex align-items-center gap-2">

                                    <i class="bi bi-paperclip text-primary"></i>


                                    <div>

                                        <div class="fw-medium">

                                            <?= h(
                                                $file['original_name'] ?? '-'
                                            ) ?>

                                        </div>


                                        <small class="text-muted">

                                            <?= round(
                                                ((int) (
                                                    $file['file_size'] ?? 0
                                                )) / 1024
                                            ) ?>

                                            KB

                                        </small>

                                    </div>

                                </div>


                                <div class="d-flex gap-2">


                                    <?php if (!empty($file['file_path'])): ?>

                                        <a
                                            href="<?= h($file['file_path']) ?>"
                                            target="_blank"
                                            rel="noopener"
                                            class="btn btn-sm btn-crf-outline"
                                        >

                                            <i class="bi bi-eye"></i>

                                            Lihat

                                        </a>

                                    <?php endif; ?>


                                    <a
                                        href="../actions/download_attachment.php?id=<?= (int) $file['id'] ?>"
                                        class="btn btn-sm btn-crf-primary"
                                    >

                                        <i class="bi bi-download"></i>

                                        Download

                                    </a>


                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>


                <?php endif; ?>


            </div>

        </div>



        <!-- =========================================================
             4. BIAYA / ANGGARAN & KATEGORI
             ========================================================= -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">

                <span class="crf-section-number">
                    4
                </span>

                <h2>
                    Biaya / Anggaran & Kategori Perubahan
                </h2>

            </div>


            <div class="crf-section-body">


                <div class="row g-4">


                    <div class="col-md-6">

                        <div class="crf-detail-label">
                            BIAYA / ANGGARAN
                        </div>

                        <div class="crf-detail-value">

                            <?= h(
                                budgetTypeLabel(
                                    $crf['budget_type'] ?? null
                                )
                            ) ?>


                            <?php if (
                                $crf['budget_amount'] !== null
                                && $crf['budget_amount'] !== ''
                            ): ?>

                                &mdash;

                                <?= h(
                                    formatRupiah(
                                        $crf['budget_amount']
                                    )
                                ) ?>

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="crf-detail-label">
                            KATEGORI PERUBAHAN
                        </div>

                        <div class="crf-detail-value">

                            <?= h(
                                $crf['change_category'] ?? '-'
                            ) ?>


                            <?php if (
                                !empty(
                                    $crf['change_category_detail']
                                )
                            ): ?>

                                &mdash;

                                <?= h(
                                    $crf['change_category_detail']
                                ) ?>

                            <?php endif; ?>

                        </div>

                    </div>


                </div>


            </div>

        </div>



        <!-- =========================================================
             5. CHANGE REQUEST ACTION
             ========================================================= -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">

                <span class="crf-section-number">
                    5
                </span>

                <h2>
                    Change Request Action
                </h2>

            </div>


            <div class="crf-section-body">


                <div class="crf-detail-label">
                    SARAN ALTERNATIF
                </div>


                <div class="crf-detail-value">

                    <?php if (!empty($crf['alternative_suggestion'])): ?>

                        <?= nl2br(
                            h(
                                $crf['alternative_suggestion']
                            )
                        ) ?>

                    <?php else: ?>

                        <span class="text-muted">
                            Belum ada saran alternatif.
                        </span>

                    <?php endif; ?>

                </div>


            </div>

        </div>



        <!-- =========================================================
             6. IMPLEMENTASI & PIR
             ========================================================= -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">

                <span class="crf-section-number">
                    6
                </span>

                <h2>
                    Implementasi & Post Implementation Review
                </h2>

            </div>


            <div class="crf-section-body">


                <div class="crf-detail-label">
                    IMPLEMENTASI / HASIL PERUBAHAN
                </div>


                <div class="crf-detail-value mb-4">

                    <?php if (!empty($crf['implementation'])): ?>

                        <?= nl2br(
                            h(
                                $crf['implementation']
                            )
                        ) ?>

                    <?php else: ?>

                        <span class="text-muted">
                            Belum diisi oleh Pemohon.
                        </span>

                    <?php endif; ?>

                </div>


                <div class="crf-detail-label">
                    POST IMPLEMENTATION REVIEW
                </div>


                <div class="crf-detail-value mb-4">

                    <?php if (
                        !empty(
                            $crf['post_implementation_review']
                        )
                    ): ?>

                        <?= nl2br(
                            h(
                                $crf['post_implementation_review']
                            )
                        ) ?>

                    <?php else: ?>

                        <span class="text-muted">
                            Belum diisi oleh Pemohon.
                        </span>

                    <?php endif; ?>

                </div>


                <?php if (!empty($crf['pak_joko_approved_at'])): ?>

                    <div class="crf-readonly-note">

                        <i class="bi bi-check2-circle"></i>

                        Disetujui Pak Joko pada

                        <?= h(
                            date(
                                'd-m-Y H:i',
                                strtotime(
                                    $crf['pak_joko_approved_at']
                                )
                            )
                        ) ?>

                    </div>

                <?php endif; ?>


            </div>

        </div>



        <!-- =========================================================
             7. INFORMASI SLA
             ========================================================= -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">

                <span class="crf-section-number">
                    7
                </span>

                <h2>
                    Informasi SLA
                </h2>

            </div>


            <div class="crf-section-body">

                <div class="cmo-sla-grid">

                    <div class="cmo-sla-item">
                        <div class="crf-detail-label">LEVEL URGENSI</div>
                        <div class="crf-detail-value">
                            <?php if (!empty($crf['level'])): ?>
                                <?= h($crf['level']) ?>
                            <?php else: ?>
                                <span class="cmo-muted">Belum ditentukan</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cmo-sla-item">
                        <div class="crf-detail-label">SLA</div>
                        <div class="crf-detail-value">
                            <?php if (!empty($crf['sla_value']) && !empty($crf['sla_unit'])): ?>
                                <span class="cmo-sla-value">
                                    <span>
                                        <?= h(
                                            rtrim(
                                                rtrim(
                                                    number_format(
                                                        (float) $crf['sla_value'],
                                                        2,
                                                        '.',
                                                        ''
                                                    ),
                                                    '0'
                                                ),
                                                '.'
                                            )
                                        ) ?>
                                    </span>
                                    <span class="cmo-sla-unit">
                                        <?= h($crf['sla_unit']) ?>
                                    </span>
                                </span>
                            <?php else: ?>
                                <span class="cmo-muted">Belum ditentukan</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cmo-sla-item">
                        <div class="crf-detail-label">MULAI SLA</div>
                        <div class="crf-detail-value">
                            <?php if (!empty($crf['sla_started_at'])): ?>
                                <?= h(
                                    date(
                                        'd-m-Y H:i',
                                        strtotime($crf['sla_started_at'])
                                    )
                                ) ?>
                            <?php else: ?>
                                <span class="cmo-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cmo-sla-item">
                        <div class="crf-detail-label">BATAS SLA</div>
                        <div class="crf-detail-value">
                            <?php if (!empty($crf['sla_due_at'])): ?>
                                <?= h(
                                    date(
                                        'd-m-Y H:i',
                                        strtotime($crf['sla_due_at'])
                                    )
                                ) ?>
                            <?php else: ?>
                                <span class="cmo-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>
        </div>



        <!-- =========================================================
             8. REVIEW CMO / FINALISASI
             ========================================================= -->

        <?php if ($crf['workflow_stage'] === 'CMO_FILTER'): ?>


            <div class="crf-section mb-4">

                <div class="crf-section-header">

                    <span class="crf-section-number">
                        8
                    </span>

                    <h2>
                        Review CMO
                    </h2>

                </div>


                <div class="crf-section-body">


                    <form
                        action="../actions/cmo_action.php"
                        method="POST"
                    >

                        <?= csrfField() ?>


                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $crf['id'] ?>"
                        >


                        <div class="mb-3">

                            <label class="form-label fw-semibold">

                                Tanggapan / Tindak Lanjut

                            </label>


                            <textarea
                                name="tanggapan"
                                class="form-control"
                                rows="4"
                                placeholder="Wajib diisi jika Perlu Revisi atau Dibatalkan."
                            ><?= h(
                                $crf['tanggapan_tindak_lanjut'] ?? ''
                            ) ?></textarea>

                        </div>


                        <div class="d-flex gap-2 flex-wrap">


                            <button
                                name="action"
                                value="to_automation"
                                class="btn btn-crf-primary"
                            >

                                <i class="bi bi-arrow-right-circle"></i>

                                Lanjut ke Otomasi

                            </button>


                            <button
                                name="action"
                                value="revision"
                                class="btn btn-warning"
                            >

                                <i class="bi bi-pencil-square"></i>

                                Perlu Revisi

                            </button>


                            <button
                                name="action"
                                value="cancel"
                                class="btn btn-outline-danger"
                            >

                                <i class="bi bi-x-circle"></i>

                                Batalkan

                            </button>


                        </div>


                    </form>


                </div>

            </div>


        <?php elseif (
            $crf['workflow_stage'] === 'CMO_FINAL'
        ): ?>


            <div class="crf-section mb-4">

                <div class="crf-section-header">

                    <span class="crf-section-number">
                        8
                    </span>

                    <h2>
                        Finalisasi
                    </h2>

                </div>


                <div class="crf-section-body">


                    <p class="text-muted">

                        Pak Joko sudah menyetujui hasil penanganan.
                        CMO dapat menutup CRF setelah memastikan
                        proses sudah lengkap.

                    </p>


                    <form
                        action="../actions/cmo_action.php"
                        method="POST"
                    >

                        <?= csrfField() ?>


                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $crf['id'] ?>"
                        >


                        <div class="d-flex gap-2 flex-wrap">


                            <button
                                name="action"
                                value="complete"
                                class="btn btn-success"
                            >

                                <i class="bi bi-check-circle"></i>

                                Tandai Selesai

                            </button>


                            <button
                                name="action"
                                value="cancel"
                                class="btn btn-outline-danger"
                            >

                                <i class="bi bi-x-circle"></i>

                                Batalkan

                            </button>


                        </div>


                    </form>


                </div>

            </div>


        <?php endif; ?>



        <!-- =========================================================
             9. TIMELINE
             ========================================================= -->
        <div class="crf-section mt-4">

            <div class="crf-section-header">

                <span class="crf-section-number">
                    9
                </span>

                <h2>
                    Timeline
                </h2>

            </div>


            <div class="crf-section-body">


                <?php if (!$timeline): ?>

                    <div class="text-muted">

                        Belum ada riwayat proses.

                    </div>


                <?php else: ?>


                    <div class="crf-timeline">


                        <?php foreach ($timeline as $item): ?>


                            <div class="crf-timeline-item">


                                <div class="crf-timeline-dot"></div>


                                <div class="crf-timeline-content">


                                    <div class="crf-timeline-top">

                                        <strong>
                                            <?= h(
                                                $item['activity']
                                            ) ?>
                                        </strong>


                                        <span class="crf-timeline-date">

                                            <?= h(
                                                date(
                                                    'd-m-Y H:i',
                                                    strtotime(
                                                        $item['created_at']
                                                    )
                                                )
                                            ) ?>

                                        </span>

                                    </div>


                                    <?php if (
                                        !empty(
                                            $item['description']
                                        )
                                    ): ?>

                                        <div class="crf-timeline-description">

                                            <?= nl2br(
                                                h(
                                                    $item['description']
                                                )
                                            ) ?>

                                        </div>

                                    <?php endif; ?>


                                    <?php if (
                                        !empty(
                                            $item['actor']
                                        )
                                    ): ?>

                                        <div class="crf-timeline-actor">

                                            Oleh:
                                            <?= h(
                                                $item['actor']
                                            ) ?>

                                        </div>

                                    <?php endif; ?>


                                </div>

                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php endif; ?>


            </div>

        </div>


    </div>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>