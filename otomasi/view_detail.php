<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getConnection();

$id = (int) ($_GET['id'] ?? 0);


/*
 * =========================================================
 * AMBIL CRF
 * =========================================================
 */
$stmt = $pdo->prepare("
    SELECT cr.*
    FROM change_requests cr
    WHERE cr.id = :id
      AND cr.workflow_stage = 'OTOMASI'
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
    ?>

    <div class="crf-page">
        <div class="container">
            <div class="alert alert-danger">
                CRF tidak ditemukan pada antrean Otomasi.
            </div>

            <a href="index.php" class="btn btn-crf-outline">
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>
        </div>
    </div>

    <?php
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


$pageTitle = 'Otomasi - Detail CRF';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* =====================================================
       KHUSUS HALAMAN DETAIL OTOMASI
       dibuat compact agar tidak terlalu melebar
       ===================================================== */

    .otomasi-detail-compact {
        max-width: 980px;
    }

    .otomasi-info-grid,
    .otomasi-sla-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        column-gap: 42px;
        row-gap: 24px;
        align-items: start;
    }

    .otomasi-info-item,
    .otomasi-sla-item {
        min-width: 0;
    }

    .otomasi-info-item .crf-detail-label,
    .otomasi-sla-item .crf-detail-label {
        margin-bottom: 8px;
    }

    .otomasi-info-item .crf-detail-value,
    .otomasi-sla-item .crf-detail-value {
        line-height: 1.45;
        min-height: 24px;
    }

    .otomasi-main-value {
        display: block;
        line-height: 1.45;
    }

    .otomasi-sub-value {
        display: block;
        margin-top: 4px;
        color: #64748b;
        font-size: 0.88rem;
        line-height: 1.35;
    }

    .otomasi-date-value,
    .otomasi-sla-value {
        white-space: nowrap;
    }

    .otomasi-sla-value {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        line-height: 1.45;
    }

    .otomasi-sla-unit {
        color: #475569;
        font-weight: 500;
    }

    .otomasi-muted {
        color: #94a3b8;
    }

    @media (max-width: 992px) {
        .otomasi-detail-compact {
            max-width: 100%;
        }

        .otomasi-info-grid,
        .otomasi-sla-grid {
            column-gap: 28px;
        }
    }

    @media (max-width: 768px) {
        .otomasi-info-grid,
        .otomasi-sla-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 480px) {
        .otomasi-info-grid,
        .otomasi-sla-grid {
            grid-template-columns: 1fr;
        }
    }
</style>


<div class="crf-page">

    <div class="container">


        <!-- HEADER -->
        <div class="crf-page-header d-flex justify-content-between align-items-start gap-2 flex-wrap">

            <div>
                <h1>Detail CRF</h1>

                <p class="mb-0">
                    Nomor Register:
                    <strong>
                        <?= h($crf['request_number'] ?? '-') ?>
                    </strong>
                    ·
                    Pengaju:
                    <?= h($crf['full_name'] ?? '-') ?>
                </p>
            </div>


            <div class="d-flex gap-2">

                <a
                    href="index.php"
                    class="btn btn-crf-outline"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>

                <a
                    href="detail.php?id=<?= (int) $crf['id'] ?>"
                    class="btn btn-crf-primary"
                >
                    <i class="bi bi-gear"></i>
                    Proses CRF
                </a>

            </div>

        </div>


        <!-- STATUS -->
        <div class="d-flex gap-2 mb-4 flex-wrap">

            <span class="crf-badge <?= statusBadgeClass($crf['status']) ?>">
                Status:
                <?= h(statusLabel($crf['status'] ?? '')) ?>
            </span>


            <span class="crf-badge <?= workflowStageBadgeClass($crf['workflow_stage']) ?>">
                Tahap:
                <?= h(workflowStageLabel($crf['workflow_stage'])) ?>
            </span>


            <span class="crf-badge <?= levelBadgeClass($crf['level']) ?>">
                Level:
                <?= h($crf['level'] ?? 'Belum ditentukan') ?>
            </span>


            <?php if (
                !empty($crf['sla_value'])
                && !empty($crf['sla_unit'])
            ): ?>

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


        <!-- =====================================================
             1. INFORMASI PENGAJUAN
             ===================================================== -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">
                <span class="crf-section-number">1</span>
                <h2>Informasi Pengajuan</h2>
            </div>


            <div class="crf-section-body">

                <div class="otomasi-detail-compact">

                    <!-- BARIS 1 -->
                    <div class="otomasi-info-grid mb-4">

                        <div class="otomasi-info-item">
                            <div class="crf-detail-label">PENGAJU</div>
                            <div class="crf-detail-value">
                                <span class="otomasi-main-value">
                                    <?= h($crf['full_name'] ?? '-') ?>
                                </span>
                            </div>
                        </div>


                        <div class="otomasi-info-item">
                            <div class="crf-detail-label">EMAIL</div>
                            <div class="crf-detail-value">
                                <span class="otomasi-main-value">
                                    <?= h($crf['email'] ?? '-') ?>
                                </span>
                            </div>
                        </div>


                        <div class="otomasi-info-item">
                            <div class="crf-detail-label">NO. HP / WA</div>
                            <div class="crf-detail-value">
                                <span class="otomasi-main-value">
                                    <?= h($crf['phone'] ?? '-') ?>
                                </span>
                            </div>
                        </div>


                        <div class="otomasi-info-item">
                            <div class="crf-detail-label">NOMOR REGISTER</div>
                            <div class="crf-detail-value">
                                <span class="otomasi-main-value">
                                    <?= h($crf['request_number'] ?? '-') ?>
                                </span>
                            </div>
                        </div>

                    </div>


                    <hr>


                    <!-- BARIS 2 -->
                    <div class="otomasi-info-grid mt-4">

                        <!-- HARI / TANGGAL -->
                        <div class="otomasi-info-item">

                            <div class="crf-detail-label">
                                HARI / TANGGAL
                            </div>

                            <div class="crf-detail-value">

                                <?php if (!empty($crf['submission_date'])): ?>

                                    <span class="otomasi-date-value">
                                        <?= h(
                                            formatTanggalIndonesia(
                                                new DateTime(
                                                    $crf['submission_date']
                                                )
                                            )
                                        ) ?>
                                    </span>

                                <?php else: ?>

                                    <span class="otomasi-muted">
                                        Belum diajukan
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- KEPADA -->
                        <div class="otomasi-info-item">

                            <div class="crf-detail-label">
                                KEPADA
                            </div>

                            <div class="crf-detail-value">

                                <span class="otomasi-main-value">
                                    <?= h(
                                        $crf['to_department']
                                        ?? '-'
                                    ) ?>
                                </span>

                                <?php if (!empty($crf['to_division'])): ?>

                                    <span class="otomasi-sub-value">
                                        <?= h($crf['to_division']) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- DARI -->
                        <div class="otomasi-info-item">

                            <div class="crf-detail-label">
                                DARI
                            </div>

                            <div class="crf-detail-value">

                                <span class="otomasi-main-value">
                                    <?= h(
                                        $crf['from_department']
                                        ?? '-'
                                    ) ?>
                                </span>

                                <?php if (!empty($crf['from_division'])): ?>

                                    <span class="otomasi-sub-value">
                                        <?= h($crf['from_division']) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- STATUS -->
                        <div class="otomasi-info-item">

                            <div class="crf-detail-label">
                                STATUS
                            </div>

                            <div class="crf-detail-value">

                                <span class="crf-badge <?= statusBadgeClass($crf['status'] ?? '') ?>">
                                    <?= h(
                                        statusLabel(
                                            $crf['status'] ?? ''
                                        )
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>
        </div>


        <!-- =====================================================
             2. DETAIL PERMINTAAN
             ===================================================== -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">
                <span class="crf-section-number">2</span>
                <h2>Detail Permintaan</h2>
            </div>


            <div class="crf-section-body">

                <div class="crf-detail-label">
                    Rincian Permohonan Perubahan
                </div>

                <div class="crf-detail-value mb-3">
                    <?= nl2br(
                        h(
                            $crf['change_description']
                            ?? '-'
                        )
                    ) ?>
                </div>


                <div class="crf-detail-label">
                    Benefit dari Perubahan yang Diharapkan
                </div>

                <div class="crf-detail-value mb-3">
                    <?= nl2br(
                        h(
                            $crf['benefit']
                            ?? '-'
                        )
                    ) ?>
                </div>


                <div class="crf-detail-label">
                    Dampak Jika Tidak Dilakukan Perubahan
                </div>

                <div class="crf-detail-value mb-3">
                    <?= nl2br(
                        h(
                            $crf['impact']
                            ?? '-'
                        )
                    ) ?>
                </div>


                <div class="crf-detail-label">
                    Alasan Permohonan Perubahan
                </div>

                <div class="crf-detail-value">
                    <?= nl2br(
                        h(
                            $crf['reason']
                            ?? '-'
                        )
                    ) ?>
                </div>

            </div>

        </div>


        <!-- =====================================================
             3. BUKTI DAN INFORMASI PENDUKUNG
             ===================================================== -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">
                <span class="crf-section-number">3</span>
                <h2>Bukti dan Informasi Pendukung</h2>
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
                                                $file['original_name']
                                                ?? '-'
                                            ) ?>
                                        </div>

                                        <small class="text-muted">
                                            <?= round(
                                                (
                                                    (int) (
                                                        $file['file_size']
                                                        ?? 0
                                                    )
                                                ) / 1024
                                            ) ?>
                                            KB
                                        </small>

                                    </div>

                                </div>


                                <div class="d-flex gap-2">

                                    <?php if (!empty($file['file_path'])): ?>

                                        <a
                                            href="<?= h(
                                                $file['file_path']
                                            ) ?>"
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


        <!-- =====================================================
             4. BIAYA / ANGGARAN & KATEGORI
             ===================================================== -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">
                <span class="crf-section-number">4</span>
                <h2>Biaya / Anggaran & Kategori Perubahan</h2>
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
                                    $crf['budget_type']
                                    ?? null
                                )
                            ) ?>

                            <?php if (
                                $crf['budget_amount']
                                !== null
                                && $crf['budget_amount']
                                !== ''
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
                                $crf['change_category']
                                ?? '-'
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


        <!-- =====================================================
             5. CHANGE REQUEST ACTION
             ===================================================== -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">
                <span class="crf-section-number">5</span>
                <h2>Change Request Action</h2>
            </div>


            <div class="crf-section-body">

                <div class="crf-detail-label">
                    SARAN ALTERNATIF
                </div>

                <div class="crf-detail-value">

                    <?php if (
                        !empty(
                            $crf['alternative_suggestion']
                        )
                    ): ?>

                        <?= nl2br(
                            h(
                                $crf[
                                    'alternative_suggestion'
                                ]
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


        <!-- =====================================================
             6. SLA
             ===================================================== -->
        <div class="crf-section mb-4">

            <div class="crf-section-header">
                <span class="crf-section-number">6</span>
                <h2>Informasi SLA</h2>
            </div>


            <div class="crf-section-body">

                <div class="otomasi-detail-compact">

                    <div class="otomasi-sla-grid">

                        <!-- LEVEL URGENSI -->
                        <div class="otomasi-sla-item">

                            <div class="crf-detail-label">
                                LEVEL URGENSI
                            </div>

                            <div class="crf-detail-value">

                                <?php if (!empty($crf['level'])): ?>

                                    <span class="otomasi-sla-value">
                                        <?= h($crf['level']) ?>
                                    </span>

                                <?php else: ?>

                                    <span class="otomasi-muted">
                                        Belum ditentukan
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- SLA -->
                        <div class="otomasi-sla-item">

                            <div class="crf-detail-label">
                                SLA
                            </div>

                            <div class="crf-detail-value">

                                <?php if (
                                    !empty($crf['sla_value'])
                                    && !empty($crf['sla_unit'])
                                ): ?>

                                    <span class="otomasi-sla-value">

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

                                        <span class="otomasi-sla-unit">
                                            <?= h($crf['sla_unit']) ?>
                                        </span>

                                    </span>

                                <?php else: ?>

                                    <span class="otomasi-muted">
                                        Belum ditentukan
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- MULAI SLA -->
                        <div class="otomasi-sla-item">

                            <div class="crf-detail-label">
                                MULAI SLA
                            </div>

                            <div class="crf-detail-value">

                                <?php if (
                                    !empty($crf['sla_started_at'])
                                ): ?>

                                    <span class="otomasi-date-value">

                                        <?= h(
                                            date(
                                                'd-m-Y H:i',
                                                strtotime(
                                                    $crf['sla_started_at']
                                                )
                                            )
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span class="otomasi-muted">
                                        -
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- BATAS SLA -->
                        <div class="otomasi-sla-item">

                            <div class="crf-detail-label">
                                BATAS SLA
                            </div>

                            <div class="crf-detail-value">

                                <?php if (
                                    !empty($crf['sla_due_at'])
                                ): ?>

                                    <span class="otomasi-date-value">

                                        <?= h(
                                            date(
                                                'd-m-Y H:i',
                                                strtotime(
                                                    $crf['sla_due_at']
                                                )
                                            )
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span class="otomasi-muted">
                                        -
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             7. TIMELINE
             ===================================================== -->
        <div class="crf-section mt-4">

            <div class="crf-section-header">
                <span class="crf-section-number">7</span>
                <h2>Timeline Proses</h2>
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


                                    <?php if (!empty($item['description'])): ?>

                                        <div class="crf-timeline-description">
                                            <?= nl2br(
                                                h(
                                                    $item['description']
                                                )
                                            ) ?>
                                        </div>

                                    <?php endif; ?>


                                    <?php if (!empty($item['actor'])): ?>

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
