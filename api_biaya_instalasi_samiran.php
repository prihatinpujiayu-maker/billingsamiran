
<?php
session_start();

require_once __DIR__ . '/db_salam.php';
require_once __DIR__ . '/helpers_salam.php';

salamRequireLogin();

header('Content-Type: application/json; charset=utf-8');

function kirimJson($success, $message = '', $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function tanggalValid($tanggal)
{
    if ($tanggal === '' || $tanggal === null) {
        return true;
    }

    $date = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $date && $date->format('Y-m-d') === $tanggal;
}

$method = $_SERVER['REQUEST_METHOD'];

// AMBIL DATA BIAYA INSTALASI
if ($method === 'GET') {

    // AMBIL RIWAYAT TERHAPUS
    if (($_GET['riwayat'] ?? '') === '1') {
        $sqlRiwayat = "
            SELECT
                b.id,
                b.pelanggan_id,
                p.id_pelanggan,
                p.nama,
                b.nominal,
                b.tanggal_instalasi,
                b.deleted_at,
                DATEDIFF(
                    DATE_ADD(b.deleted_at, INTERVAL 30 DAY),
                    NOW()
                ) AS sisa_hari
            FROM biaya_instalasi_samiran b
            INNER JOIN pelanggan_salam p ON p.id = b.pelanggan_id
            WHERE b.deleted_at IS NOT NULL
            ORDER BY b.deleted_at DESC
        ";

        $resultRiwayat = $koneksi->query($sqlRiwayat);

        if (!$resultRiwayat) {
            kirimJson(false, 'Gagal mengambil riwayat: ' . $koneksi->error);
        }

        $riwayat = [];
        while ($rowRiwayat = $resultRiwayat->fetch_assoc()) {
            $riwayat[] = $rowRiwayat;
        }

        kirimJson(true, 'Riwayat terhapus berhasil diambil.', $riwayat);
    }

    // Tampilkan SEMUA pelanggan. Biaya instalasi bersifat opsional
    // sampai admin mengisi nominal dan menyimpannya.
    // Pelanggan dengan data yang sedang berada di Riwayat Terhapus
    // tidak ditampilkan di Data Aktif agar tidak muncul dua kali.
    $sql = "
        SELECT
            b.id,
            p.id AS pelanggan_id,
            p.id_pelanggan,
            p.nama,
            p.alamat,
            b.nominal,
            b.status_bayar,
            b.tanggal_instalasi,
            b.tanggal_bayar,
            b.keterangan,
            CASE WHEN b.id IS NULL THEN 0 ELSE 1 END AS has_biaya
        FROM pelanggan_salam p
        LEFT JOIN biaya_instalasi_samiran b
            ON b.pelanggan_id = p.id
            AND b.deleted_at IS NULL
        WHERE NOT EXISTS (
            SELECT 1
            FROM biaya_instalasi_samiran bx
            WHERE bx.pelanggan_id = p.id
              AND bx.deleted_at IS NOT NULL
        )
        ORDER BY p.nama ASC
    ";

    $result = $koneksi->query($sql);

    if (!$result) {
        kirimJson(false, 'Gagal mengambil data: ' . $koneksi->error);
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    kirimJson(true, 'Data pelanggan berhasil diambil.', $data);
}

// PROSES SIMPAN / UBAH DATA
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    // SIMPAN BIAYA INSTALASI
    if ($action === 'simpan') {
        $pelangganId = (int) ($_POST['pelanggan_id'] ?? 0);
        $nominal = (float) ($_POST['nominal'] ?? 0);
        $status = $_POST['status_bayar'] ?? 'Belum Lunas';
        $tanggalInstalasi = trim($_POST['tanggal_instalasi'] ?? '');
        $tanggalBayar = trim($_POST['tanggal_bayar'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($pelangganId <= 0 || $nominal <= 0) {
            kirimJson(false, 'Pelanggan dan nominal biaya wajib diisi.');
        }

        if (!in_array($status, ['Lunas', 'Belum Lunas'], true)) {
            kirimJson(false, 'Status pembayaran tidak valid.');
        }

        if (!tanggalValid($tanggalInstalasi) || !tanggalValid($tanggalBayar)) {
            kirimJson(false, 'Format tanggal tidak valid.');
        }

        if ($tanggalInstalasi === '') {
            $tanggalInstalasi = null;
        }

        if ($status === 'Lunas') {
            if ($tanggalBayar === '') {
                $tanggalBayar = date('Y-m-d');
            }
        } else {
            $tanggalBayar = null;
        }

        $cek = $koneksi->prepare(
            "SELECT id FROM pelanggan_salam WHERE id = ?"
        );
        $cek->bind_param('i', $pelangganId);
        $cek->execute();
        $pelangganAda = $cek->get_result()->num_rows > 0;
        $cek->close();

        if (!$pelangganAda) {
            kirimJson(false, 'Data pelanggan tidak ditemukan.');
        }

        $sql = "
            INSERT INTO biaya_instalasi_samiran
                (pelanggan_id, nominal, status_bayar,
                 tanggal_instalasi, tanggal_bayar, keterangan)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                 nominal = VALUES(nominal),
                 status_bayar = VALUES(status_bayar),
                 tanggal_instalasi = VALUES(tanggal_instalasi),
                 tanggal_bayar = VALUES(tanggal_bayar),
                 keterangan = VALUES(keterangan),
                 deleted_at = NULL
        ";

        $stmt = $koneksi->prepare($sql);

        if (!$stmt) {
            kirimJson(false, 'Gagal menyiapkan penyimpanan data.');
        }

        $stmt->bind_param(
            'idssss',
            $pelangganId,
            $nominal,
            $status,
            $tanggalInstalasi,
            $tanggalBayar,
            $keterangan
        );

        if (!$stmt->execute()) {
            kirimJson(false, 'Gagal menyimpan biaya instalasi: ' . $stmt->error);
        }

        $stmt->close();
kirimJson(true, 'Biaya instalasi berhasil disimpan.');
}

if ($action === 'edit') {
    $id = (int) ($_POST['id'] ?? 0);
    $pelangganId = (int) ($_POST['pelanggan_id'] ?? 0);
    $nominal = (float) ($_POST['nominal'] ?? 0);
    $status = $_POST['status_bayar'] ?? 'Belum Lunas';
    $tanggalInstalasi = trim($_POST['tanggal_instalasi'] ?? '');
    $tanggalBayar = trim($_POST['tanggal_bayar'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($id <= 0 || $pelangganId <= 0 || $nominal <= 0) {
        kirimJson(false, 'Data ID, pelanggan, dan nominal wajib diisi.');
    }

    if (!in_array($status, ['Lunas', 'Belum Lunas'], true)) {
        kirimJson(false, 'Status pembayaran tidak valid.');
    }

    if (!tanggalValid($tanggalInstalasi) || !tanggalValid($tanggalBayar)) {
        kirimJson(false, 'Format tanggal tidak valid.');
    }

    if ($tanggalInstalasi === '') {
        $tanggalInstalasi = null;
    }

    if ($status === 'Lunas') {
        if ($tanggalBayar === '') {
            $tanggalBayar = date('Y-m-d');
        }
    } else {
        $tanggalBayar = null;
    }

    $sql = "
        UPDATE biaya_instalasi_samiran
        SET pelanggan_id = ?,
            nominal = ?,
            status_bayar = ?,
            tanggal_instalasi = ?,
            tanggal_bayar = ?,
            keterangan = ?
        WHERE id = ? AND deleted_at IS NULL
    ";

    $stmt = $koneksi->prepare($sql);

    if (!$stmt) {
        kirimJson(false, 'Gagal menyiapkan perubahan data.');
    }

    $stmt->bind_param(
        'idssssi',
        $pelangganId,
        $nominal,
        $status,
        $tanggalInstalasi,
        $tanggalBayar,
        $keterangan,
        $id
    );

    if (!$stmt->execute()) {
        kirimJson(false, 'Gagal mengubah data: ' . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        kirimJson(false, 'Data tidak ditemukan atau tidak mengalami perubahan.');
    }

    $stmt->close();
    kirimJson(true, 'Data biaya instalasi berhasil diperbarui.');
}
   
    // UBAH STATUS PEMBAYARAN
    if ($action === 'ubah_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status_bayar'] ?? '';

        if ($id <= 0 || !in_array($status, ['Lunas', 'Belum Lunas'], true)) {
            kirimJson(false, 'Data perubahan status tidak valid.');
        }

        if ($status === 'Lunas') {
            $sql = "
                UPDATE biaya_instalasi_samiran
                SET status_bayar = 'Lunas',
                    tanggal_bayar = CURDATE()
                WHERE id = ?
            ";
        } else {
            $sql = "
                UPDATE biaya_instalasi_samiran
                SET status_bayar = 'Belum Lunas',
                    tanggal_bayar = NULL
                WHERE id = ?
            ";
        }

        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param('i', $id);

        if (!$stmt->execute()) {
            kirimJson(false, 'Gagal mengubah status pembayaran.');
        }

        if ($stmt->affected_rows === 0) {
            $cek = $koneksi->prepare(
                "SELECT id FROM biaya_instalasi_samiran WHERE id = ?"
            );
            $cek->bind_param('i', $id);
            $cek->execute();
            $ada = $cek->get_result()->num_rows > 0;
            $cek->close();

            if (!$ada) {
                kirimJson(false, 'Data biaya instalasi tidak ditemukan.');
            }
        }

        $stmt->close();
        kirimJson(true, 'Status pembayaran berhasil diperbarui.');
    }

    // HAPUS BIAYA INSTALASI
   if ($action === 'hapus') {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        kirimJson(false, 'ID biaya instalasi tidak valid.');
    }

    $stmt = $koneksi->prepare(
        "UPDATE biaya_instalasi_samiran
         SET deleted_at = NOW()
         WHERE id = ? AND deleted_at IS NULL"
    );

    if (!$stmt) {
        kirimJson(false, 'Gagal menyiapkan penghapusan data.');
    }

    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        kirimJson(false, 'Gagal menghapus data: ' . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        kirimJson(false, 'Data biaya instalasi tidak ditemukan.');
    }

    $stmt->close();

    kirimJson(true, 'Data berhasil dipindahkan ke Riwayat Terhapus.');
}


// TAMBAHKAN FUNGSI PULIHKAN DI SINI
if ($action === 'pulihkan') {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        kirimJson(false, 'ID biaya instalasi tidak valid.');
    }

    $stmt = $koneksi->prepare(
        "UPDATE biaya_instalasi_samiran
         SET deleted_at = NULL
         WHERE id = ? AND deleted_at IS NOT NULL"
    );

    if (!$stmt) {
        kirimJson(false, 'Gagal menyiapkan pemulihan data.');
    }

    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        kirimJson(false, 'Gagal memulihkan data: ' . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        kirimJson(false, 'Data tidak ditemukan atau sudah dipulihkan.');
    }

    $stmt->close();

    kirimJson(true, 'Data biaya instalasi berhasil dipulihkan.');
}


kirimJson(false, 'Perintah tidak dikenal.');

}

kirimJson(false, 'Metode permintaan tidak diizinkan.');