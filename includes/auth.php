<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/crf.php';

/**
 * Memastikan user sudah login.
 * Semua user yang sudah login boleh membuka Form CRF dan Pengajuan Saya.
 */
function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

/**
 * Apakah user yang sedang login termasuk admin CRF?
 * Ditentukan dari atribut user (dept + divisi) sesuai aturan di
 * config/crf.php. Atribut dibaca dari database di setiap request,
 * jadi perubahan jabatan langsung berlaku.
 *
 * Saat integrasi ke SIAP: ganti nama tabel `users` menjadi `tbl_user`.
 */
function isAdmin(): bool
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $stmt = getConnection()->prepare(
        'SELECT userid, dept, divisi FROM users WHERE id = :id LIMIT 1'
    );

    $stmt->execute([
        'id' => $_SESSION['user_id']
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        return $cache = false;
    }

    $norm = static fn($value) => mb_strtolower(trim((string) $value));

    // Akun yang dikecualikan
    $excluded = array_map($norm, CRF_ADMIN_EXCLUDE_USERIDS);

    if (in_array($norm($user['userid']), $excluded, true)) {
        return $cache = false;
    }

    // Cocokkan dengan aturan dept + divisi
    foreach (CRF_ADMIN_RULES as $rule) {
        $divisiList = array_map($norm, $rule['divisi']);

        if (
            $norm($user['dept']) === $norm($rule['dept'])
            && in_array($norm($user['divisi']), $divisiList, true)
        ) {
            return $cache = true;
        }
    }

    return $cache = false;
}


/**
 * Mengambil role workflow CRF dari tabel crf_user_roles.
 * Jika belum ada mapping, fallback ke admin legacy atau pemohon.
 */
function getCrfRole(): string
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    requireLogin();

    try {
        $stmt = getConnection()->prepare(
            'SELECT role
             FROM crf_user_roles
             WHERE user_id = :user_id
               AND is_active = 1
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        $role = $stmt->fetchColumn();

        if (is_string($role) && $role !== '') {
            return $cache = $role;
        }
    } catch (Throwable $e) {
        // Migration role belum dijalankan; gunakan fallback legacy.
        error_log('getCrfRole fallback: ' . $e->getMessage());
    }

    return $cache = (isAdmin() ? 'admin' : 'pemohon');
}

function isCrfRole(string $role): bool
{
    return getCrfRole() === $role;
}

function requireCrfRole($roles): void
{
    requireLogin();

    $roles = is_array($roles) ? $roles : [$roles];

    if (!in_array(getCrfRole(), $roles, true)) {
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Anda tidak memiliki akses ke halaman tersebut.'
        ];

        switch (getCrfRole()) {
            case 'cmo':
                header('Location: ../cmo/dashboard.php');
                break;
            case 'otomasi':
                header('Location: ../otomasi/dashboard.php');
                break;
            case 'pak_joko':
                header('Location: ../pak_joko/dashboard.php');
                break;
            case 'admin':
                header('Location: ../admin/dashboard.php');
                break;
            default:
                header('Location: ../user/pengajuan_saya.php');
                break;
        }
        exit;
    }
}

function crfRoleLabel(string $role): string
{
    switch ($role) {
        case 'cmo':
            return 'CMO';
        case 'otomasi':
            return 'Otomasi';
        case 'pak_joko':
            return 'Pak Joko';
        case 'admin':
            return 'Admin';
        default:
            return 'Pemohon';
    }
}

/**
 * Memastikan user sudah login dan termasuk admin CRF.
 */
function requireAdmin(): void
{
    requireLogin();

    if (getCrfRole() !== 'admin') {
        $_SESSION['flash'] = [
            'type'    => 'danger',
            'message' => 'Anda tidak memiliki akses ke halaman tersebut.'
        ];

        header('Location: ../user/pengajuan_saya.php');
        exit;
    }
}

/**
 * Apakah user yang login boleh mengakses CRF (dan lampirannya)?
 * - Pemilik CRF: boleh (termasuk saat masih Draft).
 * - Admin CRF: boleh, kecuali CRF berstatus Draft.
 */
function canAccessCrf(PDO $pdo, int $crfId): bool
{
    if ($crfId <= 0 || !isset($_SESSION['user_id'])) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT user_id, status
         FROM change_requests
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute(['id' => $crfId]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    if ((int) $row['user_id'] === (int) $_SESSION['user_id']) {
        return true;
    }

    if ($row['status'] === 'Draft') {
        return false;
    }

    return in_array(
        getCrfRole(),
        ['admin', 'cmo', 'otomasi', 'pak_joko'],
        true
    );
}