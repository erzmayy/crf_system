<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../otomasi/index.php');
    exit;
}

verifyCsrf();

$pdo = getConnection();
$user = getCurrentUser();

$id = (int) ($_POST['id'] ?? 0);
$action = $_POST['action'] ?? 'save';

$level = $_POST['level'] ?? '';
$slaValue = trim($_POST['sla_value'] ?? '');
$slaUnit = $_POST['sla_unit'] ?? '';

if (!in_array($level, ['Tinggi', 'Normal', 'Rendah'], true)) {
    $level = '';
}

if (!in_array($slaUnit, ['Menit', 'Jam', 'Hari'], true)) {
    $slaUnit = '';
}

if ($id <= 0) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'CRF tidak valid.'
    ];

    header('Location: ../otomasi/index.php');
    exit;
}


/* =========================================================
 * AMBIL CRF
 * ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        workflow_stage,
        automation_started_at,
        pak_joko_approved_at,
        level,
        sla_value,
        sla_unit
    FROM change_requests
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $id
]);

$crf = $stmt->fetch();

if (!$crf || $crf['workflow_stage'] !== 'OTOMASI') {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'CRF tidak ditemukan pada tahap Otomasi.'
    ];

    header('Location: ../otomasi/index.php');
    exit;
}


/*
 * OTOMASI dipakai dua kali:
 *
 * 1. pak_joko_approved_at kosong
 *    = menentukan Level Urgensi + SLA
 *
 * 2. pak_joko_approved_at terisi
 *    = eksekusi perubahan
 */
$isExecutionStage = !empty($crf['pak_joko_approved_at']);


/* =========================================================
 * VALIDASI MODE PENENTUAN SLA
 * ========================================================= */

if (!$isExecutionStage) {

    if (
        $level === ''
        || $slaValue === ''
        || !is_numeric($slaValue)
        || (float) $slaValue <= 0
        || $slaUnit === ''
    ) {
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Level Urgensi dan SLA wajib diisi dengan benar.'
        ];

        header('Location: ../otomasi/detail.php?id=' . $id);
        exit;
    }

}


try {

    $pdo->beginTransaction();

    $now = date('Y-m-d H:i:s');

    $actor = !empty($user['nama'])
        ? $user['nama']
        : $user['userid'];


    /* =====================================================
     * MODE 1 — TENTUKAN SLA
     * ===================================================== */

    if (!$isExecutionStage) {

        /*
        * HANYA ketika SLA benar-benar dikirim ke
        * Kepala Departemen Operasional, waktu mulai dicatat.
        */
        if ($action === 'complete') {

            $startedAt = $now;

            $dueAt = slaDueAt(
                $startedAt,
                $slaValue,
                $slaUnit
            );

            $stmt = $pdo->prepare("
                UPDATE change_requests
                SET
                    level = :level,
                    sla_value = :sla_value,
                    sla_unit = :sla_unit,
                    sla_started_at = :sla_started_at,
                    sla_due_at = :sla_due_at,
                    automation_started_at = :automation_started_at,
                    workflow_stage = 'PAK_JOKO',
                    status = 'Dalam Proses'
                WHERE id = :id
                AND workflow_stage = 'OTOMASI'
                AND pak_joko_approved_at IS NULL
            ");

            $stmt->execute([
                'level' => $level,
                'sla_value' => (float) $slaValue,
                'sla_unit' => $slaUnit,
                'sla_started_at' => $startedAt,
                'sla_due_at' => $dueAt,
                'automation_started_at' => $startedAt,
                'id' => $id,
            ]);

            logCrfActivity(
                $pdo,
                $id,
                'Otomasi - SLA Ditentukan',
                'Otomasi menentukan Level Urgensi dan SLA. CRF diteruskan ke Kepala Departemen Operasional untuk approval.',
                $actor
            );

            $message = 'Level Urgensi dan SLA berhasil ditentukan. CRF menunggu approval Kepala Departemen Operasional.';

        } else {

            /*
            * Kalau hanya klik Simpan:
            * Level dan SLA disimpan,
            * tetapi waktu Mulai BELUM dicatat.
            */
            $stmt = $pdo->prepare("
                UPDATE change_requests
                SET
                    level = :level,
                    sla_value = :sla_value,
                    sla_unit = :sla_unit
                WHERE id = :id
                AND workflow_stage = 'OTOMASI'
                AND pak_joko_approved_at IS NULL
            ");

            $stmt->execute([
                'level' => $level,
                'sla_value' => (float) $slaValue,
                'sla_unit' => $slaUnit,
                'id' => $id,
            ]);

            logCrfActivity(
                $pdo,
                $id,
                'Proses Otomasi Diperbarui',
                'Level Urgensi dan SLA diperbarui oleh Otomasi.',
                $actor
            );

            $message = 'Data Level Urgensi dan SLA berhasil disimpan.';
        }


        $pdo->commit();

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => $message
        ];


        if ($action === 'complete') {
            header('Location: ../pak_joko/index.php');
        } else {
            header('Location: ../otomasi/detail.php?id=' . $id);
        }

        exit;
    }


    /* =====================================================
     * MODE 2 — EKSEKUSI SETELAH APPROVAL
     * ===================================================== */

    if ($action === 'complete') {

        $stmt = $pdo->prepare("
            UPDATE change_requests
            SET
                automation_completed_at = :automation_completed_at,
                workflow_stage = 'PEMOHON_PIR',
                status = 'Dalam Proses'
            WHERE id = :id
              AND workflow_stage = 'OTOMASI'
              AND pak_joko_approved_at IS NOT NULL
        " );

        $stmt->execute([
            'automation_completed_at' => $now,
            'id' => $id,
        ]);


        logCrfActivity(
            $pdo,
            $id,
            'Eksekusi Otomasi Selesai',
            'Otomasi menyelesaikan eksekusi perubahan. CRF diteruskan ke Pemohon untuk mengisi Implementasi dan Post Implementation Review.',
            $actor
        );


        $pdo->commit();

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Eksekusi Otomasi selesai. CRF diteruskan ke Pemohon untuk Implementasi dan Post Implementation Review.'
        ];

        header('Location: ../otomasi/index.php');
        exit;
    }


    throw new RuntimeException(
        'Aksi Otomasi tidak dikenal.'
    );


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'automation_action error: '
        . $e->getMessage()
    );

    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Terjadi kesalahan saat memproses Otomasi.'
    ];

    header('Location: ../otomasi/detail.php?id=' . $id);
    exit;
}
