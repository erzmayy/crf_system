<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getConnection();

$id = (int) ($_GET['id'] ?? 0);


/* =========================================================
 * AMBIL DATA CRF
 * ========================================================= */

$stmt = $pdo->prepare("
    SELECT cr.*
    FROM change_requests cr
    WHERE cr.id = :id
      AND cr.workflow_stage = 'PAK_JOKO'
      AND cr.status <> 'Draft'
    LIMIT 1
");

$stmt->execute([
    'id' => $id
]);

$crf = $stmt->fetch();


/* =========================================================
 * JIKA CRF TIDAK DITEMUKAN
 * ========================================================= */

if (!$crf) {

    http_response_code(404);

    $pageTitle = 'CRF Tidak Ditemukan';

    require_once __DIR__ . '/../includes/header.php';
    ?>

    <div class="crf-page">

        <div class="container">

            <div class="alert alert-danger">
                CRF tidak ditemukan pada antrean approval Kepala Departemen Operasional.
            </div>

            <a
                href="index.php"
                class="btn btn-crf-outline"
            >
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>

        </div>

    </div>

    <?php

    require_once __DIR__ . '/../includes/footer.php';

    exit;
}


/* =========================================================
 * ATTACHMENTS
 * ========================================================= */

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


/* =========================================================
 * TIMELINE
 * ========================================================= */

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


/* =========================================================
 * FLASH
 * ========================================================= */

$flash = $_SESSION['flash'] ?? null;

unset($_SESSION['flash']);


$pageTitle = 'Kepala Departemen Operasional - Review CRF';

require_once __DIR__ . '/../includes/header.php';

?>

<div class="crf-page">

    <div class="container">


        <!-- =====================================================
             HEADER
             ===================================================== -->

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 crf-page-header">

            <div>

                <h1>
                    Review CRF
                </h1>

                <p>
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


        <!-- =====================================================
             FLASH MESSAGE
             ===================================================== -->

        <?php if ($flash): ?>

            <div
                class="alert alert-<?= h($flash['type']) ?> crf-alert"
                role="alert"
            >
                <?= h($flash['message']) ?>
            </div>

        <?php endif; ?>


        <!-- =====================================================
             STATUS
             ===================================================== -->

        <div class="d-flex gap-2 mb-4 flex-wrap">

            <span
                class="crf-badge <?= statusBadgeClass($crf['status']) ?>"
            >
                Status:
                <?= h(statusLabel($crf['status'] ?? '')) ?>
            </span>


            <span
                class="crf-badge <?= workflowStageBadgeClass($crf['workflow_stage'] ?? 'PAK_JOKO') ?>"
            >
                Tahap:
                <?= h(workflowStageLabel($crf['workflow_stage'] ?? 'PAK_JOKO')) ?>
            </span>


            <span
                class="crf-badge <?= levelBadgeClass($crf['level']) ?>"
            >
                Level Urgensi:
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

                <span class="crf-section-number">
                    1
                </span>

                <h2>
                    Informasi Pengajuan
                </h2>

            </div>


            <div class="crf-section-body">

                <div class="row g-4 mb-4">

                    <div class="col-md-4">

                        <div class="crf-detail-label">
                            PENGAJU
                        </div>

                        <div class="crf-detail-value">
                            <?= h($crf['full_name'] ?? '-') ?>
                        </div>

                    </div>


                    <div class="col-md-4">

                        <div class="crf-detail-label">
                            EMAIL
                        </div>

                        <div class="crf-detail-value">
                            <?= h($crf['email'] ?? '-') ?>
                        </div>

                    </div>


                    <div class="col-md-4">

                        <div class="crf-detail-label">
                            NO. HP / WA
                        </div>

                        <div class="crf-detail-value">
                            <?= h($crf['phone'] ?? '-') ?>
                        </div>

                    </div>

                </div>


                <hr>


                <div class="row g-4 mt-1">

                    <div class="col-md-3">

                        <div class="crf-detail-label">
                            HARI / TANGGAL
                        </div>

                        <div class="crf-detail-value">

                            <?php if (!empty($crf['submission_date'])): ?>

                                <?= h(
                                    formatTanggalIndonesia(
                                        new DateTime(
                                            $crf['submission_date']
                                        )
                                    )
                                ) ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="crf-detail-label">
                            NOMOR REGISTER
                        </div>

                        <div class="crf-detail-value">
                            <?= h($crf['request_number'] ?? '-') ?>
                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="crf-detail-label">
                            KEPADA
                        </div>

                        <div class="crf-detail-value">

                            <?= h(
                                $crf['to_department'] ?? '-'
                            ) ?>

                            <?php if (!empty($crf['to_division'])): ?>

                                (
                                <?= h($crf['to_division']) ?>
                                )

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="crf-detail-label">
                            DARI
                        </div>

                        <div class="crf-detail-value">

                            <?= h(
                                $crf['from_department'] ?? '-'
                            ) ?>

                            <?php if (!empty($crf['from_division'])): ?>

                                (
                                <?= h($crf['from_division']) ?>
                                )

                            <?php endif; ?>

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

                <span class="crf-section-number">
                    2
                </span>

                <h2>
                    Detail Permintaan
                </h2>

            </div>


            <div class="crf-section-body">


                <div class="crf-detail-label">
                    RINCIAN PERMOHONAN PERUBAHAN
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
                    BENEFIT DARI PERUBAHAN YANG DIHARAPKAN
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
                    DAMPAK JIKA TIDAK DILAKUKAN PERUBAHAN
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
                    ALASAN PERMOHONAN PERUBAHAN
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

                <span class="crf-section-number">
                    3
                </span>

                <h2>
                    Bukti dan Informasi Pendukung
                </h2>

            </div>


            <div class="crf-section-body">


                <div class="crf-detail-label">
                    LAMPIRAN
                </div>


                <div class="crf-detail-value mb-4">

                    <?php if (!$attachments): ?>

                        <span class="text-muted">
                            Tidak ada file yang dilampirkan.
                        </span>

                    <?php else: ?>

                        <div class="list-group">

                            <?php foreach ($attachments as $file): ?>

                                <div
                                    class="list-group-item d-flex justify-content-between align-items-center gap-3 py-2 px-3"
                                >

                                    <div
                                        class="d-flex align-items-center gap-2 flex-grow-1 min-width-0"
                                    >

                                        <i
                                            class="bi bi-paperclip text-primary"
                                        ></i>


                                        <div class="text-truncate">

                                            <div
                                                class="fw-medium text-truncate"
                                            >
                                                <?= h(
                                                    $file['original_name']
                                                ) ?>
                                            </div>


                                            <small class="text-muted">

                                                <?= round(
                                                    $file['file_size'] / 1024
                                                ) ?>

                                                KB

                                            </small>

                                        </div>

                                    </div>


                                    <div
                                        class="d-flex gap-1 flex-shrink-0"
                                    >

                                        <a
                                            href="<?= h(
                                                $file['file_path']
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener"
                                            class="btn btn-sm btn-crf-outline py-1 px-2"
                                        >

                                            <i class="bi bi-eye"></i>

                                            Lihat

                                        </a>


                                        <a
                                            href="../actions/download_attachment.php?id=<?= (int) $file['id'] ?>"
                                            class="btn btn-sm btn-crf-primary py-1 px-2"
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


                <div class="crf-detail-label">
                    BIAYA / ANGGARAN
                </div>

                <div class="crf-detail-value mb-3">

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


                <div class="crf-detail-label">
                    KATEGORI PERUBAHAN
                </div>

                <div class="crf-detail-value mb-3">

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


                <div class="crf-detail-label">
                    SARAN ALTERNATIF
                </div>

                <div class="crf-detail-value">

                    <?= nl2br(
                        h(
                            $crf['alternative_suggestion']
                            ?? '-'
                        )
                    ) ?>

                </div>

            </div>

        </div>


        <!-- =====================================================
             5. INFORMASI SLA
             ===================================================== -->

        <div class="crf-section mb-4">

            <div class="crf-section-header">

                <span class="crf-section-number">
                    5
                </span>

                <h2>
                    Informasi SLA
                </h2>

            </div>


            <div class="crf-section-body">

                <div class="row g-4">


                    <div class="col-md-3">

                        <div class="crf-detail-label">
                            LEVEL URGENSI
                        </div>

                        <div class="crf-detail-value">

                            <?= h(
                                $crf['level']
                                ?? 'Belum ditentukan'
                            ) ?>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="crf-detail-label">
                            SLA
                        </div>

                        <div class="crf-detail-value">

                            <?php if (
                                !empty($crf['sla_value'])
                                && !empty($crf['sla_unit'])
                            ): ?>

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

                                <?= h(
                                    $crf['sla_unit']
                                ) ?>

                            <?php else: ?>

                                <span class="text-muted">
                                    Belum ditentukan
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="crf-detail-label">
                            MULAI SLA
                        </div>

                        <div class="crf-detail-value">

                            <?php if (
                                !empty(
                                    $crf['sla_started_at']
                                )
                            ): ?>

                                <?= h(
                                    date(
                                        'd-m-Y H:i',
                                        strtotime(
                                            $crf[
                                                'sla_started_at'
                                            ]
                                        )
                                    )
                                ) ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="crf-detail-label">
                            BATAS SLA
                        </div>

                        <div class="crf-detail-value">

                            <?php if (
                                !empty(
                                    $crf['sla_due_at']
                                )
                            ): ?>

                                <?= h(
                                    date(
                                        'd-m-Y H:i',
                                        strtotime(
                                            $crf[
                                                'sla_due_at'
                                            ]
                                        )
                                    )
                                ) ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </div>

                    </div>


                </div>

            </div>

        </div>


        <!-- =====================================================
             6. APPROVAL Kepala Departemen Operasional
             ===================================================== -->

        <div class="crf-section mb-4">

            <div class="crf-section-header">

                <span class="crf-section-number">
                    6
                </span>

                <h2>
                    Approval Kepala Departemen Operasional
                </h2>

            </div>


            <div class="crf-section-body">

                <p class="text-muted">

                    Silakan periksa seluruh detail CRF terlebih dahulu
                    sebelum memberikan approval.

                </p>


                <form
                    action="../actions/pak_joko_approve.php"
                    method="POST"
                >

                    <?= csrfField() ?>


                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $crf['id'] ?>"
                    >


                    <div class="mb-3">

                        <label
                            for="approval_note"
                            class="form-label fw-semibold"
                        >
                            Catatan Approval
                            <span class="text-muted">
                                (opsional)
                            </span>
                        </label>


                        <textarea
                            name="approval_note"
                            id="approval_note"
                            class="form-control"
                            rows="4"
                            placeholder="Tuliskan catatan approval bila diperlukan..."
                        ><?= h(
                            $crf['pak_joko_approval_note']
                            ?? ''
                        ) ?></textarea>

                    </div>


                    <button
                        type="submit"
                        class="btn btn-success"
                    >

                        <i class="bi bi-check-circle"></i>

                        Approve CRF

                    </button>

                </form>

            </div>

        </div>


        <!-- =====================================================
             7. TIMELINE
             ===================================================== -->

        <div class="crf-section mt-4">

            <div class="crf-section-header">

                <span class="crf-section-number">
                    <i class="bi bi-clock-history"></i>
                </span>

                <h2>
                    Timeline Proses
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
                                                $item['activity'] === 'Solve'
                                                    ? 'Selesai'
                                                    : (
                                                        $item['activity'] === 'Cancel'
                                                            ? 'Dibatalkan'
                                                            : $item['activity']
                                                    )
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