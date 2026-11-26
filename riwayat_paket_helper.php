<?php
/**
 * Helper Riwayat Perubahan Paket Pelanggan (Upgrade / Downgrade / Penyesuaian Tarif)
 * Billing Samiran Multiwilayah
 */

require_once __DIR__ . '/helpers_salam.php';

/**
 * Memastikan tabel riwayat_paket_salam siap digunakan.
 * Otomatis membuat tabel bila belum ada agar tidak menimbulkan error di instalasi lain.
 */
function salamRiwayatPaketTableReady(mysqli $koneksi): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $check = $koneksi->query("SHOW TABLES LIKE 'riwayat_paket_salam'");
    if ($check instanceof mysqli_result && $check->num_rows > 0) {
        $ready = true;
        return true;
    }

    // Auto-create table jika belum ada
    $createSql = "CREATE TABLE IF NOT EXISTS `riwayat_paket_salam` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `pelanggan_id` INT UNSIGNED NOT NULL COMMENT 'Mengacu ke pelanggan_salam.id',
        `id_pelanggan` VARCHAR(60) NOT NULL DEFAULT '',
        `nama_pelanggan` VARCHAR(120) NOT NULL DEFAULT '',
        `paket_lama` VARCHAR(180) NOT NULL,
        `paket_baru` VARCHAR(180) NOT NULL,
        `tarif_lama` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
        `tarif_baru` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
        `selisih_tarif` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
        `jenis_perubahan` ENUM('Upgrade','Downgrade','Penyesuaian Tarif') NOT NULL,
        `tanggal_perubahan` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `tanggal_efektif` DATE NOT NULL,
        `alasan` TEXT DEFAULT NULL,
        `admin_id` INT NULL DEFAULT NULL,
        `admin_nama` VARCHAR(100) DEFAULT NULL,
        `sumber` ENUM('manual','deteksi_historis') NOT NULL DEFAULT 'manual',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_pelanggan_id` (`pelanggan_id`),
        INDEX `idx_tanggal_efektif` (`tanggal_efektif`),
        INDEX `idx_jenis_perubahan` (`jenis_perubahan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if ($koneksi->query($createSql)) {
        $ready = true;
        return true;
    }

    error_log('Gagal membuat tabel riwayat_paket_salam: ' . $koneksi->error);
    $ready = false;
    return false;
}

/**
 * Menentukan jenis perubahan paket berdasarkan perbandingan tarif lama dan baru.
 */
function salamTentukanJenisPerubahan(float $tarifLama, float $tarifBaru): string
{
    $selisih = $tarifBaru - $tarifLama;
    if ($selisih > 0.01) {
        return 'Upgrade';
    }
    if ($selisih < -0.01) {
        return 'Downgrade';
    }
    return 'Penyesuaian Tarif';
}

/**
 * Mencatat riwayat perubahan paket ke database secara manual (saat admin melakukan edit).
 */
function salamCatatRiwayatPaket(mysqli $koneksi, array $data): bool
{
    if (!salamRiwayatPaketTableReady($koneksi)) {
        return false;
    }

    $pelangganId = (int) ($data['pelanggan_id'] ?? 0);
    if ($pelangganId <= 0) {
        return false;
    }

    $idPelanggan = trim((string) ($data['id_pelanggan'] ?? ''));
    $namaPelanggan = trim((string) ($data['nama_pelanggan'] ?? ''));
    $paketLama = trim((string) ($data['paket_lama'] ?? ''));
    $paketBaru = trim((string) ($data['paket_baru'] ?? ''));
    $tarifLama = (float) ($data['tarif_lama'] ?? 0);
    $tarifBaru = (float) ($data['tarif_baru'] ?? 0);
    $selisihTarif = $tarifBaru - $tarifLama;
    $jenisPerubahan = $data['jenis_perubahan'] ?? salamTentukanJenisPerubahan($tarifLama, $tarifBaru);

    $tanggalEfektif = trim((string) ($data['tanggal_efektif'] ?? ''));
    if ($tanggalEfektif === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalEfektif)) {
        $tanggalEfektif = date('Y-m-d');
    }

    $alasan = trim((string) ($data['alasan'] ?? ''));
    $adminId = !empty($data['admin_id']) ? (int) $data['admin_id'] : null;
    $adminNama = trim((string) ($data['admin_nama'] ?? ($_SESSION['username'] ?? 'Admin')));
    $sumber = ($data['sumber'] ?? 'manual') === 'deteksi_historis' ? 'deteksi_historis' : 'manual';

    $stmt = $koneksi->prepare(
        "INSERT INTO `riwayat_paket_salam`
        (`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`,
         `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`,
         `tanggal_efektif`, `alasan`, `admin_id`, `admin_nama`, `sumber`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        error_log('Prepare statement salamCatatRiwayatPaket gagal: ' . $koneksi->error);
        return false;
    }

    $stmt->bind_param(
        'issssdddsssiss',
        $pelangganId,
        $idPelanggan,
        $namaPelanggan,
        $paketLama,
        $paketBaru,
        $tarifLama,
        $tarifBaru,
        $selisihTarif,
        $jenisPerubahan,
        $tanggalEfektif,
        $alasan,
        $adminId,
        $adminNama,
        $sumber
    );

    $ok = $stmt->execute();
    if (!$ok) {
        error_log('Eksekusi salamCatatRiwayatPaket gagal: ' . $stmt->error);
    }
    $stmt->close();

    return $ok;
}

/**
 * Mendeteksi perubahan paket historis dari snapshot tabel tagihan_salam dan pelanggan_salam.
 * Menghasilkan riwayat transparan tanpa mengarang data yang tidak ada.
 */
function salamDeteksiRiwayatHistorisPelanggan(mysqli $koneksi, int $pelangganId): array
{
    if ($pelangganId <= 0) {
        return [];
    }

    // Ambil histori dari tagihan_salam urut periode terlama ke terbaru
    $stmt = $koneksi->prepare(
        "SELECT
            t.periode,
            t.paket_snapshot AS paket,
            COALESCE(NULLIF(t.tarif_snapshot, 0), t.nominal_tagihan, 0) AS tarif,
            t.id_pelanggan_snapshot AS id_pelanggan,
            t.nama_snapshot AS nama
         FROM tagihan_salam t
         WHERE t.pelanggan_id = ?
         ORDER BY t.periode ASC"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $pelangganId);
    $stmt->execute();
    $result = $stmt->get_result();
    $snapshots = [];
    while ($row = $result->fetch_assoc()) {
        $snapshots[] = [
            'periode' => $row['periode'],
            'paket' => trim((string) $row['paket']),
            'tarif' => (float) $row['tarif'],
            'id_pelanggan' => trim((string) $row['id_pelanggan']),
            'nama' => trim((string) $row['nama']),
        ];
    }
    $stmt->close();

    // Tambahkan periode berjalan dari pelanggan_salam sebagai titik data terbaru
    $stmtCurrent = $koneksi->prepare(
        "SELECT p.waktu AS periode, p.paket, p.tarif_langganan AS tarif, p.id_pelanggan, p.nama
         FROM pelanggan_salam p
         WHERE p.id = ?
         LIMIT 1"
    );
    if ($stmtCurrent) {
        $stmtCurrent->bind_param('i', $pelangganId);
        $stmtCurrent->execute();
        $curr = $stmtCurrent->get_result()->fetch_assoc();
        $stmtCurrent->close();

        if ($curr && !empty($curr['paket'])) {
            $currPeriode = date('Y-m-01', strtotime((string) ($curr['periode'] ?? date('Y-m-01'))));
            // Cek apakah periode berjalan belum ada di snapshot
            $alreadyHas = false;
            foreach ($snapshots as $s) {
                if (date('Y-m', strtotime($s['periode'])) === date('Y-m', strtotime($currPeriode))) {
                    $alreadyHas = true;
                    break;
                }
            }
            if (!$alreadyHas) {
                $snapshots[] = [
                    'periode' => $currPeriode,
                    'paket' => trim((string) $curr['paket']),
                    'tarif' => (float) ($curr['tarif'] ?? 0),
                    'id_pelanggan' => trim((string) ($curr['id_pelanggan'] ?? '')),
                    'nama' => trim((string) ($curr['nama'] ?? '')),
                ];
            }
        }
    }

    if (count($snapshots) < 2) {
        return [];
    }

    // Urutkan snapshot berdasarkan periode ASC
    usort($snapshots, function ($a, $b) {
        return strcmp($a['periode'], $b['periode']);
    });

    $detectedChanges = [];
    for ($i = 0; $i < count($snapshots) - 1; $i++) {
        $prev = $snapshots[$i];
        $next = $snapshots[$i + 1];

        $paketBeda = ($prev['paket'] !== '' && $next['paket'] !== '' && strcasecmp($prev['paket'], $next['paket']) !== 0);
        $tarifBeda = abs($prev['tarif'] - $next['tarif']) > 0.01;

        if ($paketBeda || $tarifBeda) {
            $selisih = $next['tarif'] - $prev['tarif'];
            $jenis = salamTentukanJenisPerubahan($prev['tarif'], $next['tarif']);

            $detectedChanges[] = [
                'id' => 0, // Ditandai sebagai virtual/historis
                'pelanggan_id' => $pelangganId,
                'id_pelanggan' => $next['id_pelanggan'] ?: $prev['id_pelanggan'],
                'nama_pelanggan' => $next['nama'] ?: $prev['nama'],
                'paket_lama' => $prev['paket'] ?: '-',
                'paket_baru' => $next['paket'] ?: '-',
                'tarif_lama' => $prev['tarif'],
                'tarif_baru' => $next['tarif'],
                'selisih_tarif' => $selisih,
                'jenis_perubahan' => $jenis,
                'tanggal_perubahan' => null, // Tidak mengarang tanggal
                'tanggal_efektif' => $next['periode'],
                'alasan' => 'Penyesuaian terdeteksi dari arsip tagihan bulanan',
                'admin_id' => null,
                'admin_nama' => 'Sistem (Deteksi Historis)',
                'sumber' => 'deteksi_historis',
                'created_at' => $next['periode'] . ' 00:00:00',
            ];
        }
    }

    return $detectedChanges;
}

/**
 * Mengambil seluruh riwayat paket pelanggan (manual + deteksi historis),
 * diurutkan dari yang terbaru (tanggal_efektif DESC).
 */
function salamGetSemuaRiwayatPaket(mysqli $koneksi, int $pelangganId): array
{
    if ($pelangganId <= 0) {
        return [];
    }

    $manualRows = [];
    if (salamRiwayatPaketTableReady($koneksi)) {
        $stmt = $koneksi->prepare(
            "SELECT * FROM `riwayat_paket_salam`
             WHERE `pelanggan_id` = ?
             ORDER BY `tanggal_efektif` DESC, `id` DESC"
        );
        if ($stmt) {
            $stmt->bind_param('i', $pelangganId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $manualRows[] = $r;
            }
            $stmt->close();
        }
    }

    // Ambil deteksi historis
    $historicalRows = salamDeteksiRiwayatHistorisPelanggan($koneksi, $pelangganId);

    // Kumpulkan tanggal efektif yang sudah memiliki catatan manual agar tidak ganda
    $manualPeriods = [];
    foreach ($manualRows as $m) {
        $key = date('Y-m', strtotime((string) $m['tanggal_efektif']));
        $manualPeriods[$key] = true;
    }

    // Gabungkan riwayat historis yang belum dicatat manual
    $combined = $manualRows;
    foreach ($historicalRows as $h) {
        $key = date('Y-m', strtotime((string) $h['tanggal_efektif']));
        if (!isset($manualPeriods[$key])) {
            $combined[] = $h;
        }
    }

    // Urutkan dari yang paling baru
    usort($combined, function ($a, $b) {
        $tA = strtotime((string) ($a['tanggal_efektif'] ?? ''));
        $tB = strtotime((string) ($b['tanggal_efektif'] ?? ''));
        if ($tA === $tB) {
            return (int) ($b['id'] ?? 0) - (int) ($a['id'] ?? 0);
        }
        return $tB - $tA;
    });

    return $combined;
}

/**
 * Mengambil status perubahan paket terbaru (Upgrade / Downgrade / Penyesuaian)
 * untuk sekumpulan pelanggan_id sekaligus (batch lookup untuk tabel dashboard).
 *
 * @param mysqli $koneksi
 * @param array $pelangganIds
 * @return array [pelanggan_id => row_data_perubahan]
 */
function salamGetLatestPaketStatusMap(mysqli $koneksi, array $pelangganIds): array
{
    $pelangganIds = array_values(array_filter(array_unique(array_map('intval', $pelangganIds))));
    if (empty($pelangganIds)) {
        return [];
    }

    $map = [];

    // 1. Ambil dari riwayat_paket_salam jika tabel siap
    if (salamRiwayatPaketTableReady($koneksi)) {
        $idList = implode(',', $pelangganIds);
        $sql = "
            SELECT r.*
            FROM `riwayat_paket_salam` r
            INNER JOIN (
                SELECT `pelanggan_id`, MAX(`id`) AS max_id
                FROM `riwayat_paket_salam`
                WHERE `pelanggan_id` IN ({$idList})
                GROUP BY `pelanggan_id`
            ) latest ON r.`id` = latest.max_id
        ";
        $res = $koneksi->query($sql);
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $pid = (int) $row['pelanggan_id'];
                $map[$pid] = $row;
            }
        }
    }

    // 2. Untuk ID yang belum punya catatan di riwayat_paket_salam, cek deteksi historis
    $missingIds = array_diff($pelangganIds, array_keys($map));
    foreach ($missingIds as $pid) {
        $history = salamDeteksiRiwayatHistorisPelanggan($koneksi, $pid);
        if (!empty($history)) {
            $latest = end($history);
            if ($latest) {
                $map[$pid] = $latest;
            }
        }
    }

    return $map;
}

/**
 * Mengambil satu catatan riwayat paket berdasarkan ID primer.
 */
function salamGetRiwayatPaketById(mysqli $koneksi, int $id): ?array
{
    if ($id <= 0 || !salamRiwayatPaketTableReady($koneksi)) {
        return null;
    }

    $stmt = $koneksi->prepare("SELECT * FROM `riwayat_paket_salam` WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Memperbarui catatan riwayat paket yang sudah ada berdasarkan ID.
 */
function salamUpdateRiwayatPaket(mysqli $koneksi, int $id, array $data): bool
{
    if ($id <= 0 || !salamRiwayatPaketTableReady($koneksi)) {
        return false;
    }

    $existing = salamGetRiwayatPaketById($koneksi, $id);
    if (!$existing) {
        return false;
    }

    $paketLama = trim((string) ($data['paket_lama'] ?? $existing['paket_lama']));
    $paketBaru = trim((string) ($data['paket_baru'] ?? $existing['paket_baru']));
    $tarifLama = isset($data['tarif_lama']) ? (float) $data['tarif_lama'] : (float) $existing['tarif_lama'];
    $tarifBaru = isset($data['tarif_baru']) ? (float) $data['tarif_baru'] : (float) $existing['tarif_baru'];
    $selisihTarif = $tarifBaru - $tarifLama;

    $jenisPerubahan = trim((string) ($data['jenis_perubahan'] ?? ''));
    if (!in_array($jenisPerubahan, ['Upgrade', 'Downgrade', 'Penyesuaian Tarif'], true)) {
        $jenisPerubahan = salamTentukanJenisPerubahan($tarifLama, $tarifBaru);
    }

    $tanggalEfektif = trim((string) ($data['tanggal_efektif'] ?? $existing['tanggal_efektif']));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalEfektif)) {
        $tanggalEfektif = (string) $existing['tanggal_efektif'];
    }

    $alasan = isset($data['alasan']) ? trim((string) $data['alasan']) : (string) $existing['alasan'];
    $adminNama = trim((string) ($data['admin_nama'] ?? ($_SESSION['username'] ?? ($existing['admin_nama'] ?: 'Admin'))));

    $stmt = $koneksi->prepare(
        "UPDATE `riwayat_paket_salam`
         SET `paket_lama` = ?, `paket_baru` = ?, `tarif_lama` = ?, `tarif_baru` = ?,
             `selisih_tarif` = ?, `jenis_perubahan` = ?, `tanggal_efektif` = ?,
             `alasan` = ?, `admin_nama` = ?
         WHERE `id` = ?"
    );

    if (!$stmt) {
        error_log('Prepare salamUpdateRiwayatPaket gagal: ' . $koneksi->error);
        return false;
    }

    $stmt->bind_param(
        'ssdddssssi',
        $paketLama,
        $paketBaru,
        $tarifLama,
        $tarifBaru,
        $selisihTarif,
        $jenisPerubahan,
        $tanggalEfektif,
        $alasan,
        $adminNama,
        $id
    );

    $ok = $stmt->execute();
    if (!$ok) {
        error_log('Execute salamUpdateRiwayatPaket gagal: ' . $stmt->error);
    }
    $stmt->close();

    return $ok;
}

/**
 * Menghapus satu catatan riwayat paket berdasarkan ID.
 */
function salamHapusRiwayatPaket(mysqli $koneksi, int $id): bool
{
    if ($id <= 0 || !salamRiwayatPaketTableReady($koneksi)) {
        return false;
    }

    $stmt = $koneksi->prepare("DELETE FROM `riwayat_paket_salam` WHERE `id` = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

