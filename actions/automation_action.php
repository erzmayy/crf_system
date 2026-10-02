<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireCrfRole(['otomasi']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../otomasi/index.php');
    exit;
}

verifyCsrf();

$pdo = getConnection();
$user = getCurrentUser();
$id = (int) ($_POST['id'] ?? 0);
$action = $_POST['action'] ?? 'save';
$submittedLevel = $_POST['level'] ?? '';
$slaValue = trim($_POST['sla_value'] ?? '');
$slaUnit = $_POST['sla_unit'] ?? '';
$implementation = trim($_POST['implementation'] ?? '');
$implementationDate = trim($_POST['implementation_date'] ?? '');

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'CRF tidak valid.'];
    header('Location: ../otomasi/index.php');
    exit;
}

if (!in_array($action, ['save', 'complete'], true)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Aksi Otomasi tidak dikenal.'];
    header('Location: ../otomasi/index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            id,
            workflow_stage,
            automation_started_at,
            kadep_operasional_approved_at,
            level,
            impact_category,
            final_urgency_level,
            sla_value,
            sla_unit
        FROM change_requests
        WHERE id = :id
        FOR UPDATE
    ");
    $stmt->execute(['id' => $id]);
    $crf = $stmt->fetch();

    if (!$crf || $crf['workflow_stage'] !== 'OTOMASI') {
        $pdo->rollBack();
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'CRF tidak ditemukan pada tahap Otomasi.'
        ];
        header('Location: ../otomasi/index.php');
        exit;
    }

    $level = $crf['level']
        ?: crfUrgencyForImpact($crf['impact_category'] ?? null)
        ?: $submittedLevel;
    $isExecutionStage = !empty($crf['kadep_operasional_approved_at']);
    if ($isExecutionStage || !empty($crf['final_urgency_level'])) {
        $slaValue = (string) ($crf['sla_value'] ?? '');
        $slaUnit = $crf['sla_unit'] ?? '';
    }

    if (
        !in_array($level, ['Tinggi', 'Normal', 'Rendah'], true)
        || $slaValue === ''
        || !is_numeric($slaValue)
        || (float) $slaValue <= 0
        || !in_array($slaUnit, ['Menit', 'Jam', 'Hari'], true)
    ) {
        $pdo->rollBack();
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Level Urgensi dan SLA wajib diisi dengan benar.'
        ];
        header('Location: ../otomasi/detail.php?id=' . $id);
        exit;
    }

    $parsedImplementationDate = DateTime::createFromFormat('!Y-m-d', $implementationDate);
    $implementationDateErrors = DateTime::getLastErrors();
    $isValidImplementationDate = $parsedImplementationDate !== false
        && $parsedImplementationDate->format('Y-m-d') === $implementationDate
        && (
            $implementationDateErrors === false
            || (
                $implementationDateErrors['warning_count'] === 0
                && $implementationDateErrors['error_count'] === 0
            )
        );
    $hasMissingExecutionDetails = !$isValidImplementationDate
        || $implementation === '';

    if (
        $action === 'complete'
        && $isExecutionStage
        && $hasMissingExecutionDetails
    ) {
        $pdo->rollBack();
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Tanggal implementasi dan Implementasi / Hasil Perubahan wajib diisi dengan benar sebelum eksekusi diselesaikan.'
        ];
        header('Location: ../otomasi/detail.php?id=' . $id);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $actor = !empty($user['nama']) ? $user['nama'] : $user['userid'];

    if ($action === 'save') {
        $stmt = $pdo->prepare("
            UPDATE change_requests
            SET
                level = :level,
                sla_value = :sla_value,
                sla_unit = :sla_unit,
                sla_started_at = NULL,
                sla_due_at = NULL
            WHERE id = :id
              AND workflow_stage = 'OTOMASI'
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
        $redirect = '../otomasi/detail.php?id=' . $id;
    } elseif (!$isExecutionStage) {
        $stmt = $pdo->prepare("
            UPDATE change_requests
            SET
                level = :level,
                sla_value = :sla_value,
                sla_unit = :sla_unit,
                sla_started_at = NULL,
                sla_due_at = NULL,
                automation_started_at = NULL,
                workflow_stage = 'kadep_operasional',
                status = 'Dalam Proses'
            WHERE id = :id
              AND workflow_stage = 'OTOMASI'
              AND kadep_operasional_approved_at IS NULL
        ");
        $stmt->execute([
            'level' => $level,
            'sla_value' => (float) $slaValue,
            'sla_unit' => $slaUnit,
            'id' => $id,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('CRF sudah tidak tersedia untuk approval.');
        }

        logCrfActivity(
            $pdo,
            $id,
            'Otomasi - SLA Ditentukan',
            'Otomasi menentukan Level Urgensi dan SLA. CRF diteruskan ke Kepala Departemen Operasional untuk approval.',
            $actor
        );
        $message = 'Level Urgensi dan SLA berhasil ditentukan. CRF menunggu approval Kepala Departemen Operasional.';
        $redirect = '../pak_joko/index.php';
    } else {
        $stmt = $pdo->prepare("
            UPDATE change_requests
            SET
                automation_completed_at = :automation_completed_at,
                implementation_date = :implementation_date,
                implementation = :implementation,
                workflow_stage = 'PEMOHON_PIR',
                status = 'Dalam Proses'
            WHERE id = :id
              AND workflow_stage = 'OTOMASI'
              AND kadep_operasional_approved_at IS NOT NULL
        ");
        $stmt->execute([
            'automation_completed_at' => $now,
            'implementation_date' => $parsedImplementationDate->format('Y-m-d'),
            'implementation' => $implementation,
            'id' => $id,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('CRF sudah tidak tersedia untuk diselesaikan.');
        }

        logCrfActivity(
            $pdo,
            $id,
            'Otomasi Selesai',
            'Otomasi menyelesaikan eksekusi dan mencatat Tanggal Implementasi serta Implementasi / Hasil Perubahan. CRF diteruskan kepada Pemohon untuk Post Implementation Review.',
            $actor
        );
        $message = 'Eksekusi dan hasil implementasi berhasil disimpan. CRF menunggu Post Implementation Review dari Pemohon.';
        $redirect = '../otomasi/index.php';
    }

    $pdo->commit();
    $_SESSION['flash'] = ['type' => 'success', 'message' => $message];
    header('Location: ' . $redirect);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('automation_action error: ' . $e->getMessage());
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Terjadi kesalahan saat memproses Otomasi.'
    ];
    header('Location: ../otomasi/detail.php?id=' . $id);
    exit;
}
