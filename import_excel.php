<?php
include 'koneksi.php';

// Otomatis perbarui struktur tabel agar memiliki kolom nik, alamat_lengkap, ppp_secret, dll.
mysqli_query($conn, "DROP TABLE IF EXISTS tagihan_samiran");
mysqli_query($conn, "DROP TABLE IF EXISTS pelanggan_samiran");

mysqli_query($conn, "CREATE TABLE pelanggan_samiran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan VARCHAR(30) NOT NULL UNIQUE,
    nama_lengkap VARCHAR(100) NOT NULL,
    nik VARCHAR(30) NULL,
    id_wilayah INT NULL,
    alamat_lengkap TEXT NULL,
    no_wa VARCHAR(25) NULL,
    tgl_pasang VARCHAR(30) NULL,
    ppp_secret VARCHAR(100) NULL,
    ip_perangkat VARCHAR(20) NULL,
    mac_perangkat VARCHAR(25) NULL,
    id_ap INT NULL,
    kategori_link ENUM('End-User', 'PTP-Backhaul') DEFAULT 'End-User',
    paket VARCHAR(50) NULL,
    tarif_bulanan INT DEFAULT 0,
    biaya_instalasi INT DEFAULT 0,
    sisa_tagihan_2025 INT DEFAULT 0,
    status_pelanggan ENUM('Aktif', 'OFF Bulanan', 'Tidak Aktif') DEFAULT 'Aktif',
    riwayat_paket TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE tagihan_samiran (
    id_tagihan INT AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan VARCHAR(30) NOT NULL,
    nomor_invoice VARCHAR(50) NOT NULL,
    periode_bulan VARCHAR(7) NOT NULL,
    tagihan_bulanan INT DEFAULT 0,
    tunggakan_2025 INT DEFAULT 0,
    biaya_instalasi INT DEFAULT 0,
    sewa_router INT DEFAULT 0,
    diskon INT DEFAULT 0,
    total_bayar INT DEFAULT 0,
    status_bayar ENUM('Belum Lunas', 'Lunas') DEFAULT 'Belum Lunas',
    tgl_bayar DATE NULL,
    keterangan_rapel VARCHAR(150) NULL,
    FOREIGN KEY (id_pelanggan) REFERENCES pelanggan_samiran(id_pelanggan) ON DELETE CASCADE
)");

$file_excel = __DIR__ . '/data billing samiran.xlsx';
if (!file_exists($file_excel)) {
    die("Error: File 'data billing samiran.xlsx' tidak ditemukan di folder C:\\laragon\\www\\billing_samiran\\\n");
}

// Fungsi konversi huruf kolom Excel (A, B, ..., AG) ke index 0-based
function colToIndex($col) {
    $col = strtoupper($col);
    $len = strlen($col);
    $idx = 0;
    for ($i = 0; $i < $len; $i++) {
        $idx = $idx * 26 + (ord($col[$i]) - 64);
    }
    return $idx - 1;
}

// Baca isi XLSX via ZipArchive
$zip = new ZipArchive;
if ($zip->open($file_excel) !== TRUE) {
    die("Gagal membuka file Excel.\n");
}

$sharedStrings = [];
if (($xmlStrings = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
    $sxml = simplexml_load_string($xmlStrings);
    foreach ($sxml->si as $si) {
        if (isset($si->t)) {
            $sharedStrings[] = (string)$si->t;
        } else {
            $text = '';
            foreach ($si->r as $r) {
                $text .= (string)$r->t;
            }
            $sharedStrings[] = $text;
        }
    }
}

$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
$zip->close();

$xml = simplexml_load_string($sheetXml);
$rows = [];
foreach ($xml->sheetData->row as $row) {
    $rIdx = (int)$row['r'] - 1;
    $rows[$rIdx] = [];
    foreach ($row->c as $c) {
        preg_match('/([A-Z]+)[0-9]+/', (string)$c['r'], $m);
        $cIdx = colToIndex($m[1]);
        $type = (string)$c['t'];
        $val  = isset($c->v) ? (string)$c->v : '';
        if ($type === 's' && $val !== '') {
            $val = $sharedStrings[(int)$val] ?? '';
        } elseif ($type === 'inlineStr' && isset($c->is->t)) {
            $val = (string)$c->is->t;
        }
        $rows[$rIdx][$cIdx] = trim($val);
    }
}

// Ambil daftar wilayah untuk pencocokan dusun otomatis
$wilayah_map = [];
$qw = mysqli_query($conn, "SELECT id_wilayah, nama_dusun FROM master_wilayah_samiran");
while ($w = mysqli_fetch_assoc($qw)) {
    $wilayah_map[strtolower($w['nama_dusun'])] = $w['id_wilayah'];
}

function cariIdWilayah($alamat, $ppp, $nama, $map) {
    $gabung = strtolower($alamat . ' ' . $ppp . ' ' . $nama);
    foreach ($map as $dusun => $id) {
        if (strpos($gabung, $dusun) !== false) {
            return $id;
        }
    }
    return 1; // Default: Samiran
}

function formatTanggalExcel($val) {
    if (is_numeric($val) && $val > 20000) {
        $unix = ($val - 25569) * 86400;
        return gmdate("Y-m-d", $unix);
    }
    return $val;
}

function formatNomorHP($val) {
    if ($val === '' || $val === null) return '';
    if (is_numeric($val)) {
        $str = number_format((float)$val, 0, '', '');
        return (substr($str, 0, 1) === '8') ? '0' . $str : $str;
    }
    return preg_replace('/[^0-9]/', '', $val);
}

$bulan_cols = [
    '2026-01' => [13, 14], // JANUARI, KET
    '2026-02' => [15, 16], // FEBRUARI, KET
    '2026-03' => [17, 18], // MARET, KET
    '2026-04' => [19, 20], // APRIL, KET
    '2026-05' => [21, 22], // MEI, KET
    '2026-06' => [23, 24], // JUNI, KET
    '2026-07' => [25, 26], // JULI, KET
    '2026-08' => [27, 28], // AGUSTUS, KET
    '2026-09' => [29, 30], // SEPTEMBER, KET
    '2026-10' => [31, 32], // OKTOBER, KET
];

$total_pelanggan = 0;
$total_tagihan_row = 0;
$auto_id_counter = 1;

// Loop baris pelanggan (baris Excel ke-2 sampai ke-682 -> index 1 sampai 681)
for ($r = 1; $r <= 682; $r++) {
    if (!isset($rows[$r])) continue;
    $d = $rows[$r];

    $nama = $d[2] ?? '';
    if ($nama === '' || strtoupper($nama) === 'TOTAL') continue;

    $bst         = formatTanggalExcel($d[1] ?? '');
    $status_raw  = strtoupper($d[3] ?? 'AKTIF');
    $no_pel      = $d[4] ?? '';
    if ($no_pel === '') {
        $no_pel = sprintf("SMR-NEW-%03d", $auto_id_counter++);
    }
    $pic_wa      = formatNomorHP($d[5] ?? '');
    $alamat      = $d[6] ?? '';
    $nik_raw     = $d[7] ?? '';
    $nik         = is_numeric($nik_raw) ? number_format((float)$nik_raw, 0, '', '') : $nik_raw;
    $ppp         = $d[8] ?? '';
    $biaya_inst  = (int)($d[9] ?? 0);
    $sisa_2025   = (int)($d[10] ?? 0);
    $tarif_bln   = (int)($d[11] ?? 0);
    $id_wilayah  = cariIdWilayah($alamat, $ppp, $nama, $wilayah_map);
    $status_pel  = ($status_raw === 'AKTIF') ? 'Aktif' : 'Tidak Aktif';

    // Escape string
    $e_id     = mysqli_real_escape_string($conn, $no_pel);
    $e_nama   = mysqli_real_escape_string($conn, $nama);
    $e_nik    = mysqli_real_escape_string($conn, $nik);
    $e_alamat = mysqli_real_escape_string($conn, $alamat);
    $e_wa     = mysqli_real_escape_string($conn, $pic_wa);
    $e_bst    = mysqli_real_escape_string($conn, $bst);
    $e_ppp    = mysqli_real_escape_string($conn, $ppp);

    $sql_p = "INSERT INTO pelanggan_samiran 
        (id_pelanggan, nama_lengkap, nik, id_wilayah, alamat_lengkap, no_wa, tgl_pasang, ppp_secret, kategori_link, paket, tarif_bulanan, biaya_instalasi, sisa_tagihan_2025, status_pelanggan)
        VALUES ('$e_id', '$e_nama', '$e_nik', $id_wilayah, '$e_alamat', '$e_wa', '$e_bst', '$e_ppp', 'End-User', 'Paket " . ($tarif_bln/1000) . "K', $tarif_bln, $biaya_inst, $sisa_2025, '$status_pel')
        ON DUPLICATE KEY UPDATE nama_lengkap='$e_nama', sisa_tagihan_2025=$sisa_2025, tarif_bulanan=$tarif_bln";
    
    if (mysqli_query($conn, $sql_p)) {
        $total_pelanggan++;
    }

    // Import riwayat pembayaran per bulan (Januari - Oktober 2026)
    foreach ($bulan_cols as $periode => $cols) {
        $bayar_val = (int)($d[$cols[0]] ?? 0);
        $ket_val   = $d[$cols[1]] ?? '';
        $e_ket     = mysqli_real_escape_string($conn, $ket_val);

        $is_off = (stripos($ket_val, 'OFF') !== false || stripos($ket_val, 'OOF') !== false || stripos($ket_val, '00f') !== false);
        $tagihan_bln_ini = $is_off ? 0 : $tarif_bln;
        $status_byr = ($bayar_val > 0 || $is_off) ? 'Lunas' : 'Belum Lunas';
        $no_inv = "INV-SMR-" . str_replace('-', '', $periode) . "-" . str_pad($total_pelanggan, 4, '0', STR_PAD_LEFT);

        // Simpan tagihan jika ada pembayaran, ada keterangan, atau untuk periode terbaru (2026-09 & 2026-10)
        if ($bayar_val > 0 || $ket_val !== '' || in_array($periode, ['2026-09', '2026-10'])) {
            $nominal_record = ($bayar_val > 0) ? $bayar_val : $tagihan_bln_ini;
            $tunggakan_rec  = ($periode === '2026-10') ? $sisa_2025 : 0;
            $total_rec      = $nominal_record + $tunggakan_rec;

            mysqli_query($conn, "INSERT INTO tagihan_samiran 
                (id_pelanggan, nomor_invoice, periode_bulan, tagihan_bulanan, tunggakan_2025, total_bayar, status_bayar, keterangan_rapel)
                VALUES ('$e_id', '$no_inv', '$periode', $tagihan_bln_ini, $tunggakan_rec, $total_rec, '$status_byr', '$e_ket')");
            $total_tagihan_row++;
        }
    }
}

echo "SELESAI! Berhasil mengimpor $total_pelanggan pelanggan dan $total_tagihan_row catatan tagihan bulanan ke Laragon MySQL.\n";
?>