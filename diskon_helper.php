<?php
/**
 * Helper Keterangan & Riwayat Diskon Pelanggan
 * Billing Samiran Multiwilayah
 *
 * Memisahkan secara tegas antara kasus Diskon Tagihan (potongan harga pada periode tertentu)
 * dengan kasus Perubahan Paket (Upgrade / Downgrade yang bersifat permanen pada paket langganan).
 */

require_once __DIR__ . '/helpers_salam.php';

/**
 * Memastikan tabel diskon_pelanggan_salam siap digunakan.
 */
function salamDiskonTableReady(mysqli $koneksi): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $check = $koneksi->query("SHOW TABLES LIKE 'diskon_pelanggan_salam'");
    if ($check instanceof mysqli_result && $check->num_rows > 0) {
        $ready = true;
        return true;
    }

    $createSql = "CREATE TABLE IF NOT EXISTS `diskon_pelanggan_salam` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `pelanggan_id` INT UNSIGNED NOT NULL COMMENT 'Mengacu ke pelanggan_salam.id',
        `id_pelanggan` VARCHAR(60) NOT NULL DEFAULT '',
        `nama_pelanggan` VARCHAR(120) NOT NULL DEFAULT '',
        `periode` VARCHAR(7) NOT NULL COMMENT 'Format YYYY-MM (misal: 2026-05)',
        `nominal_diskon` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
        `keterangan` VARCHAR(255) NOT NULL DEFAULT '',
        `tarif_normal` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
        `tarif_setelah_diskon` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
        `admin_nama` VARCHAR(100) DEFAULT 'Sistem (Data Billing)',
        `sumber` ENUM('manual','spreadsheet') NOT NULL DEFAULT 'spreadsheet',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_pelanggan_id` (`pelanggan_id`),
        INDEX `idx_periode` (`periode`),
        INDEX `idx_pelanggan_periode` (`pelanggan_id`, `periode`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if ($koneksi->query($createSql)) {
        $ready = true;
        return true;
    }

    error_log('Gagal membuat tabel diskon_pelanggan_salam: ' . $koneksi->error);
    $ready = false;
    return false;
}

/**
 * Mencatat atau memperbarui data diskon pelanggan untuk periode tertentu.
 */
function salamCatatDiskonPelanggan(mysqli $koneksi, array $data): bool
{
    if (!salamDiskonTableReady($koneksi)) {
        return false;
    }

    $pelangganId = (int) ($data['pelanggan_id'] ?? 0);
    if ($pelangganId <= 0) {
        return false;
    }

    $idPelanggan = trim((string) ($data['id_pelanggan'] ?? ''));
    $namaPelanggan = trim((string) ($data['nama_pelanggan'] ?? ''));
    $periode = trim((string) ($data['periode'] ?? '')); // YYYY-MM
    if (!preg_match('/^\d{4}-\d{2}$/', $periode)) {
        $periode = date('Y-m');
    }

    $nominalDiskon = (float) ($data['nominal_diskon'] ?? 0);
    $keterangan = trim((string) ($data['keterangan'] ?? ''));
    $tarifNormal = (float) ($data['tarif_normal'] ?? 0);
    $tarifSetelahDiskon = (float) ($data['tarif_setelah_diskon'] ?? ($tarifNormal - $nominalDiskon));
    $adminNama = trim((string) ($data['admin_nama'] ?? 'Sistem (Data Billing)'));
    $sumber = ($data['sumber'] ?? 'spreadsheet') === 'manual' ? 'manual' : 'spreadsheet';

    // Cek apakah sudah ada catatan untuk pelanggan & periode ini
    $checkStmt = $koneksi->prepare("SELECT id FROM diskon_pelanggan_salam WHERE pelanggan_id = ? AND periode = ? LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param('is', $pelangganId, $periode);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            $updateStmt = $koneksi->prepare(
                "UPDATE diskon_pelanggan_salam 
                 SET nominal_diskon = ?, keterangan = ?, tarif_normal = ?, tarif_setelah_diskon = ?, admin_nama = ?, sumber = ?
                 WHERE id = ?"
            );
            $existId = (int) $existing['id'];
            $updateStmt->bind_param('dsddssi', $nominalDiskon, $keterangan, $tarifNormal, $tarifSetelahDiskon, $adminNama, $sumber, $existId);
            $ok = $updateStmt->execute();
            $updateStmt->close();
            return $ok;
        }
    }

    $stmt = $koneksi->prepare(
        "INSERT INTO `diskon_pelanggan_salam`
        (`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `periode`, `nominal_diskon`, `keterangan`, `tarif_normal`, `tarif_setelah_diskon`, `admin_nama`, `sumber`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'isssdsddss',
        $pelangganId,
        $idPelanggan,
        $namaPelanggan,
        $periode,
        $nominalDiskon,
        $keterangan,
        $tarifNormal,
        $tarifSetelahDiskon,
        $adminNama,
        $sumber
    );

    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Format label ringkas untuk badge diskon (misal: "20K" atau "10K").
 */
function salamFormatDiskonSingkat(float $nominal, string $keteranganRaw = ''): string
{
    if ($nominal >= 1000) {
        $k = $nominal / 1000;
        return (fmod($k, 1) === 0.0 ? number_format($k, 0) : number_format($k, 1)) . 'K';
    }
    if ($nominal > 0) {
        return 'Rp ' . number_format($nominal, 0, ',', '.');
    }
    // Jika nominal 0 tapi ada keterangan teks
    if (preg_match('/disk\s*(\d+)\s*(k)?/i', $keteranganRaw, $m)) {
        return strtoupper($m[1] . ($m[2] ?? 'K'));
    }
    return '';
}

/**
 * Mengambil map diskon pelanggan untuk sekumpulan pelanggan_id pada periode tertentu.
 * Digunakan untuk rendering baris tabel dashboard.
 *
 * @param mysqli $koneksi
 * @param array $pelangganIds
 * @param string $periodeYm Format YYYY-MM
 * @return array [pelanggan_id => row_diskon_lengkap]
 */
function salamGetDiskonPeriodeMap(mysqli $koneksi, array $pelangganIds, string $periodeYm): array
{
    $pelangganIds = array_values(array_filter(array_unique(array_map('intval', $pelangganIds))));
    if (empty($pelangganIds) || !salamDiskonTableReady($koneksi)) {
        return [];
    }

    $idList = implode(',', $pelangganIds);
    $escapedPeriode = $koneksi->real_escape_string($periodeYm);

    $sql = "
        SELECT * 
        FROM `diskon_pelanggan_salam`
        WHERE `pelanggan_id` IN ({$idList})
          AND `periode` = '{$escapedPeriode}'
    ";

    $map = [];
    $res = $koneksi->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $pid = (int) $row['pelanggan_id'];
            $nom = (float) $row['nominal_diskon'];
            $row['label_singkat'] = salamFormatDiskonSingkat($nom, $row['keterangan']);
            $map[$pid] = $row;
        }
    }

    return $map;
}

/**
 * Mengambil seluruh riwayat diskon untuk 1 pelanggan (untuk halaman detail / edit).
 */
function salamGetSemuaDiskonPelanggan(mysqli $koneksi, int $pelangganId): array
{
    if ($pelangganId <= 0 || !salamDiskonTableReady($koneksi)) {
        return [];
    }

    $stmt = $koneksi->prepare(
        "SELECT * FROM `diskon_pelanggan_salam`
         WHERE `pelanggan_id` = ?
         ORDER BY `periode` DESC, `id` DESC"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $pelangganId);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $nom = (float) $row['nominal_diskon'];
        $row['label_singkat'] = salamFormatDiskonSingkat($nom, $row['keterangan']);
        $list[] = $row;
    }
    $stmt->close();

    return $list;
}

/**
 * Mengambil ringkasan diskon pelanggan: apakah pernah mendapat diskon dan berapa totalnya.
 */
function salamGetRingkasanDiskonPelanggan(mysqli $koneksi, int $pelangganId): array
{
    if ($pelangganId <= 0 || !salamDiskonTableReady($koneksi)) {
        return ['punya_diskon' => false, 'total_kali' => 0, 'total_nominal' => 0];
    }

    $stmt = $koneksi->prepare(
        "SELECT COUNT(*) AS total_kali, SUM(nominal_diskon) AS total_nominal
         FROM `diskon_pelanggan_salam`
         WHERE `pelanggan_id` = ?"
    );

    if (!$stmt) {
        return ['punya_diskon' => false, 'total_kali' => 0, 'total_nominal' => 0];
    }

    $stmt->bind_param('i', $pelangganId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $kali = (int) ($row['total_kali'] ?? 0);
    $nom = (float) ($row['total_nominal'] ?? 0);

    return [
        'punya_diskon' => $kali > 0,
        'total_kali' => $kali,
        'total_nominal' => $nom
    ];
}

/**
 * Menghapus catatan diskon pelanggan berdasarkan ID primer.
 */
function salamHapusDiskonPelangganById(mysqli $koneksi, int $id): bool
{
    if ($id <= 0 || !salamDiskonTableReady($koneksi)) {
        return false;
    }

    $stmt = $koneksi->prepare("DELETE FROM `diskon_pelanggan_salam` WHERE `id` = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}
