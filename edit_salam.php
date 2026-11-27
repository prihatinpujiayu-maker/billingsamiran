<?php
session_start();
require_once __DIR__ . '/db_salam.php';
require_once __DIR__ . '/salam_bootstrap.php';
require_once __DIR__ . '/pelanggan_detail_helper.php';
require_once __DIR__ . '/tagihan_periode_helper.php';
require_once __DIR__ . '/riwayat_paket_helper.php';
require_once __DIR__ . '/diskon_helper.php';

salamRequireLogin();
$pppoeManualCsrf = $_SESSION['pppoe_manual_csrf'] ?? bin2hex(random_bytes(24));
$_SESSION['pppoe_manual_csrf'] = $pppoeManualCsrf;
$canAccessAllWilayah = salamCanAccessAllWilayah();

$id = (int) ($_GET['id'] ?? 0);
$isModal = isset($_GET['modal']) && $_GET['modal'] === '1';
if ($id <= 0) {
    exit('ID pelanggan tidak valid.');
}

foreach (['tarif_langganan', 'tanggal_bayar', 'nominal_dibayar', 'alamat'] as $column) {
    if (!salamTableHasColumn($koneksi, $column)) {
        exit('Struktur database Salam belum diperbarui. Import database_update_salam_only.sql terlebih dahulu.');
    }
}

$masterData = salamFindPelangganById($koneksi, $id);
if (!$masterData) {
    exit('Data pelanggan tidak ditemukan atau bukan wilayah akun ini.');
}
$requestedPeriode = salamPeriodeYm($_GET['periode'] ?? ($masterData['waktu'] ?? date('Y-m')));
$data = salamFindTagihanPeriode($koneksi, $id, $requestedPeriode);
if (!$data) {
    exit('Tagihan pada periode yang dipilih tidak ditemukan atau bukan wilayah akun ini.');
}
$isHistorical = (($data['sumber_data'] ?? '') === 'riwayat');
if (!salamDetailPelangganTableReady($koneksi)) {
    exit('Database fitur profil pelanggan belum dipasang. Import database_update_billing_pppoe_FINAL.sql terlebih dahulu.');
}
$detail = salamGetPelangganDetail($koneksi, $id);
$riwayatPaket = salamGetSemuaRiwayatPaket($koneksi, $id);
$riwayatDiskon = salamGetSemuaDiskonPelanggan($koneksi, $id);

$requestedYm = salamPeriodeYm($requestedPeriode ?? ($data['waktu'] ?? date('Y-m')));
$stmtCurDisk = $koneksi->prepare("SELECT * FROM diskon_pelanggan_salam WHERE pelanggan_id = ? AND periode = ? LIMIT 1");
$diskonPeriodeIni = null;
if ($stmtCurDisk) {
    $stmtCurDisk->bind_param('is', $id, $requestedYm);
    $stmtCurDisk->execute();
    $diskonPeriodeIni = $stmtCurDisk->get_result()->fetch_assoc();
    $stmtCurDisk->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $idPelanggan = trim((string) ($_POST['id_pelanggan'] ?? ''));
    $kode = trim((string) ($_POST['kode_pelanggan'] ?? ''));
    $nomor = trim((string) ($_POST['nomor_pelanggan'] ?? ''));
    $alamat = $canAccessAllWilayah
        ? salamNormalisasiAlamatInput($_POST['alamat'] ?? '')
        : salamWilayahLogin();
    $paket = trim((string) ($_POST['paket'] ?? ''));
    $alasanPaket = trim((string) ($_POST['alasan_paket'] ?? ''));
    $tanggalEfektifPaket = trim((string) ($_POST['tanggal_efektif_paket'] ?? ''));
    // Admin hanya mengisi satu nominal: Tagihan.
    // tarif_langganan tetap disimpan internal agar nominal dapat muncul lagi saat bulan baru.
    $tagihanPeriode = (float) str_replace(',', '.', (string) ($_POST['tagihan'] ?? 0));
    $nominalDiskonInput = isset($_POST['nominal_diskon']) ? (float) str_replace(',', '.', (string) $_POST['nominal_diskon']) : 0.0;
    $keteranganDiskonInput = trim((string) ($_POST['keterangan_diskon'] ?? ''));
    $tarifTersimpan = (float) ($data['tarif_langganan'] ?? 0);
    if ($tarifTersimpan <= 0) {
        $tarifTersimpan = (float) ($data['tagihan'] ?? 0);
    }
    $periodeTagihan = trim((string) ($_POST['waktu'] ?? ''));
    $masaAktifSampai = trim((string) ($_POST['langganan_selesai'] ?? ''));
    $statusBayar = ($_POST['status_bayar'] ?? 'Belum Lunas') === 'Lunas'
        ? 'Lunas'
        : 'Belum Lunas';
    $namaKtp = trim((string) ($_POST['nama_ktp'] ?? ''));
    $nik = preg_replace('/\s+/', '', trim((string) ($_POST['nik'] ?? ''))) ?? '';
    try {
        [$koordinatX, $koordinatY] = salamParseKoordinatBilling(
            $_POST['koordinat_x'] ?? '',
            $_POST['koordinat_y'] ?? ''
        );
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
        $koordinatX = null;
        $koordinatY = null;
    }
    if ($nik !== '' && !preg_match('/^[0-9]{8,32}$/', $nik)) {
        $errorMessage = 'NIK hanya boleh berisi angka.';
    }

    if ($isHistorical) {
        $historyId = (int) ($data['tagihan_riwayat_id'] ?? 0);
        if ($historyId <= 0) {
            $errorMessage = 'Riwayat tagihan tidak ditemukan.';
        } elseif ($tagihanPeriode < 0) {
            $errorMessage = 'Tagihan tidak boleh negatif.';
        } elseif (!salamDateIsValid($masaAktifSampai)) {
            $errorMessage = 'Masa aktif sampai harus berupa tanggal yang valid.';
        } elseif (salamPeriodeYm($periodeTagihan, $requestedPeriode) !== $requestedPeriode) {
            $errorMessage = 'Periode riwayat tidak dapat dipindahkan. Pilih periode dari Dashboard.';
        } else {
            $nominalRiwayat = $tagihanPeriode > 0
                ? $tagihanPeriode
                : (float) ($data['nominal_tagihan_asli'] ?? $data['tarif_langganan'] ?? 0);
            if ($statusBayar === 'Lunas') {
                $stmtHistory = $koneksi->prepare("UPDATE tagihan_salam
                    SET nominal_tagihan=?, tanggal_jatuh_tempo=?, status_bayar='Lunas',
                        tanggal_bayar=NOW(), nominal_dibayar=?
                    WHERE id=?");
                $stmtHistory->bind_param('dsdi', $nominalRiwayat, $masaAktifSampai, $nominalRiwayat, $historyId);
            } else {
                $stmtHistory = $koneksi->prepare("UPDATE tagihan_salam
                    SET nominal_tagihan=?, tanggal_jatuh_tempo=?, status_bayar='Belum Lunas',
                        tanggal_bayar=NULL, nominal_dibayar=NULL
                    WHERE id=?");
                $stmtHistory->bind_param('dsi', $nominalRiwayat, $masaAktifSampai, $historyId);
            }
            $okHistory = $stmtHistory->execute();
            $historyError = $stmtHistory->error;
            $stmtHistory->close();
            if ($okHistory) {
                if ($isModal) {
                    ?>
                    <!doctype html><html lang="id"><head><meta charset="utf-8"><style>
                    html,body{height:100%;margin:0;font-family:Segoe UI,Arial;display:grid;place-items:center;background:#f5f7f9}
                    .msg{background:#fff;padding:22px 28px;border-radius:12px;box-shadow:0 16px 35px rgba(0,0,0,.1);text-align:center}
                    .msg h3{margin:0 0 6px;color:#2c3e50}.msg p{margin:0;color:#6c757d}
                    </style></head><body><div class="msg"><h3>Tagihan periode lama berhasil diperbarui</h3><p>Menutup jendela...</p></div>
                    <script>setTimeout(()=>{window.parent.postMessage(JSON.stringify({action:'close',success:true}),'*')},650)</script>
                    </body></html>
                    <?php
                    exit;
                }
                header('Location: dashboard_salam.php');
                exit;
            }
            $errorMessage = 'Riwayat tagihan gagal diperbarui' . ($historyError !== '' ? ': ' . $historyError : '.');
        }
    }

    if (!$isHistorical) {
    if (!empty($errorMessage)) {
        // Pesan validasi profil pelanggan sudah ditentukan di atas.
    } elseif ($nama === '' || $paket === '') {
        $errorMessage = 'Nama dan paket wajib diisi.';
    } elseif ($tagihanPeriode < 0) {
        $errorMessage = 'Tagihan tidak boleh negatif.';
    } elseif (!salamDateIsValid($periodeTagihan) || !salamDateIsValid($masaAktifSampai)) {
        $errorMessage = 'Periode tagihan dan masa aktif sampai harus berupa tanggal yang valid.';
    } elseif ($masaAktifSampai < $periodeTagihan) {
        $errorMessage = 'Masa aktif sampai tidak boleh lebih awal dari periode tagihan.';
    } else {
        // Satu input nominal untuk admin:
        // - Belum Lunas dan nominal > 0: nominal itu juga menjadi tarif bulan berikutnya.
        // - Lunas: tagihan saat ini menjadi Rp0, tetapi tarif yang tersimpan tetap dipertahankan.
        // - Dari Lunas ke Belum Lunas saat nominal masih 0: sistem mengembalikan tarif tersimpan.
        $tarif = $tarifTersimpan;
        if ($statusBayar === 'Belum Lunas' && $tagihanPeriode > 0) {
            $tarif = $tagihanPeriode;
        } elseif ($statusBayar === 'Belum Lunas' && $tagihanPeriode <= 0) {
            $tagihanPeriode = $tarifTersimpan;
        }

        if ($statusBayar === 'Lunas') {
            $nominalDibayar = $tagihanPeriode > 0
                ? $tagihanPeriode
                : ((float) ($data['tagihan'] ?? 0) > 0
                    ? (float) $data['tagihan']
                    : $tarifTersimpan);

            $sql = "UPDATE pelanggan_salam
                    SET nama = ?,
                        id_pelanggan = ?,
                        kode_pelanggan = ?,
                        nomor_pelanggan = ?,
                        alamat = ?,
                        paket = ?,
                        tarif_langganan = ?,
                        waktu = ?,
                        langganan_selesai = ?,
                        status_bayar = 'Lunas',
                        tagihan = 0,
                        tanggal_bayar = NOW(),
                        nominal_dibayar = ?
                    WHERE id = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param(
                'ssssssdssdi',
                $nama,
                $idPelanggan,
                $kode,
                $nomor,
                $alamat,
                $paket,
                $tarif,
                $periodeTagihan,
                $masaAktifSampai,
                $nominalDibayar,
                $id
            );
        } else {
            $sql = "UPDATE pelanggan_salam
                    SET nama = ?,
                        id_pelanggan = ?,
                        kode_pelanggan = ?,
                        nomor_pelanggan = ?,
                        alamat = ?,
                        paket = ?,
                        tarif_langganan = ?,
                        waktu = ?,
                        langganan_selesai = ?,
                        status_bayar = 'Belum Lunas',
                        tagihan = ?,
                        tanggal_bayar = NULL,
                        nominal_dibayar = NULL
                    WHERE id = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param(
                'ssssssdssdi',
                $nama,
                $idPelanggan,
                $kode,
                $nomor,
                $alamat,
                $paket,
                $tarif,
                $periodeTagihan,
                $masaAktifSampai,
                $tagihanPeriode,
                $id
            );
        }

        $fotoLama = (string) ($detail['foto_rumah'] ?? '');
        $fotoBaru = null;
        $ok = false;
        $dbError = '';
        try {
            $koneksi->begin_transaction();
            if (!$stmt || !$stmt->execute()) {
                throw new RuntimeException($stmt ? $stmt->error : $koneksi->error);
            }
            if ($stmt) {
                $stmt->close();
                $stmt = null;
            }

            $fotoFinal = $fotoLama;
            $fotoBaru = salamFotoRumahUpload($_FILES['foto_rumah'] ?? [], $idPelanggan !== '' ? $idPelanggan : ('pelanggan-' . $id));
            if ($fotoBaru !== null) {
                $fotoFinal = $fotoBaru;
            }

            salamUpsertPelangganDetailBilling(
                $koneksi,
                $id,
                $namaKtp,
                $nik,
                $fotoFinal
            );
            salamUpdatePelangganKoordinatBilling($koneksi, $id, $koordinatX, $koordinatY);

            // Pencatatan Riwayat Perubahan Paket Pelanggan (Upgrade / Downgrade / Penyesuaian Tarif)
            $paketLama = trim((string) ($data['paket'] ?? ''));
            $isPaketBeda = ($paket !== '' && strcasecmp($paketLama, $paket) !== 0);
            $isTarifBeda = abs($tarif - $tarifTersimpan) > 0.01;
            if (!$isHistorical && ($isPaketBeda || $isTarifBeda)) {
                $tglEfektifFinal = ($tanggalEfektifPaket !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalEfektifPaket))
                    ? $tanggalEfektifPaket
                    : ($periodeTagihan ?: date('Y-m-d'));

                salamCatatRiwayatPaket($koneksi, [
                    'pelanggan_id' => $id,
                    'id_pelanggan' => $idPelanggan ?: ($data['id_pelanggan'] ?? ''),
                    'nama_pelanggan' => $nama ?: ($data['nama'] ?? ''),
                    'paket_lama' => $paketLama ?: '-',
                    'paket_baru' => $paket,
                    'tarif_lama' => $tarifTersimpan,
                    'tarif_baru' => $tarif,
                    'tanggal_efektif' => $tglEfektifFinal,
                    'alasan' => $alasanPaket,
                    'admin_nama' => $_SESSION['username'] ?? 'Admin',
                    'sumber' => 'manual',
                ]);
            }

            // Pencatatan / Pembaruan Diskon Pelanggan
            $periodeYmFinal = salamPeriodeYm($periodeTagihan ?: ($requestedPeriode ?: date('Y-m')));
            if ($nominalDiskonInput > 0 || $keteranganDiskonInput !== '') {
                $tarifNormalDiskon = $tarifTersimpan > 0 ? $tarifTersimpan : $tarif;
                salamCatatDiskonPelanggan($koneksi, [
                    'pelanggan_id' => $id,
                    'id_pelanggan' => $idPelanggan ?: ($data['id_pelanggan'] ?? ''),
                    'nama_pelanggan' => $nama ?: ($data['nama'] ?? ''),
                    'periode' => $periodeYmFinal,
                    'nominal_diskon' => $nominalDiskonInput,
                    'keterangan' => $keteranganDiskonInput,
                    'tarif_normal' => $tarifNormalDiskon,
                    'tarif_setelah_diskon' => max(0, $tarifNormalDiskon - $nominalDiskonInput),
                    'admin_nama' => $_SESSION['username'] ?? 'Admin',
                    'sumber' => 'manual',
                ]);
            } elseif (isset($_POST['nominal_diskon']) && $nominalDiskonInput == 0 && $keteranganDiskonInput === '') {
                $delDisk = $koneksi->prepare("DELETE FROM diskon_pelanggan_salam WHERE pelanggan_id = ? AND periode = ?");
                if ($delDisk) {
                    $delDisk->bind_param('is', $id, $periodeYmFinal);
                    $delDisk->execute();
                    $delDisk->close();
                }
            }

            $koneksi->commit();
            $ok = true;
            $detail = salamGetPelangganDetail($koneksi, $id);
            $riwayatPaket = salamGetSemuaRiwayatPaket($koneksi, $id);
            $riwayatDiskon = salamGetSemuaDiskonPelanggan($koneksi, $id);
            if ($fotoBaru !== null && $fotoLama !== '' && $fotoLama !== $fotoBaru) {
                salamHapusFotoRumah($fotoLama);
            }
        } catch (Throwable $e) {
            $koneksi->rollback();
            if ($stmt) {
                $stmt->close();
            }
            if ($fotoBaru !== null) {
                salamHapusFotoRumah($fotoBaru);
            }
            $dbError = $e->getMessage();
        }

        if ($ok) {
            if ($isModal) {
                ?>
                <!doctype html>
                <html lang="id"><head><meta charset="utf-8"><style>
                html,body{height:100%;margin:0;font-family:Segoe UI,Arial;display:grid;place-items:center;background:#f5f7f9}
                .msg{background:#fff;padding:22px 28px;border-radius:12px;box-shadow:0 16px 35px rgba(0,0,0,.1);text-align:center}
                .msg h3{margin:0 0 6px;color:#2c3e50}.msg p{margin:0;color:#6c757d}
                </style></head><body><div class="msg"><h3>Data berhasil diperbarui</h3><p>Menutup jendela...</p></div>
                <script>setTimeout(()=>{window.parent.postMessage(JSON.stringify({action:'close',success:true}),'*')},650)</script>
                </body></html>
                <?php
                exit;
            }

            header('Location: dashboard_salam.php');
            exit;
        }

        $errorMessage = 'Data gagal diperbarui' . ($dbError !== '' ? ': ' . $dbError : '.');
    }

    }

    $data['nama'] = $isHistorical ? ($data['nama'] ?? $nama) : $nama;
    $data['id_pelanggan'] = $isHistorical ? ($data['id_pelanggan'] ?? $idPelanggan) : $idPelanggan;
    $data['kode_pelanggan'] = $isHistorical ? ($data['kode_pelanggan'] ?? $kode) : $kode;
    $data['nomor_pelanggan'] = $isHistorical ? ($data['nomor_pelanggan'] ?? $nomor) : $nomor;
    $data['alamat'] = $isHistorical ? ($data['alamat'] ?? $alamat) : $alamat;
    $data['paket'] = $isHistorical ? ($data['paket'] ?? $paket) : $paket;
    $data['tarif_langganan'] = $isHistorical ? ($data['tarif_langganan'] ?? $tagihanPeriode) : $tarif;
    $data['tagihan'] = $statusBayar === 'Lunas' ? 0 : $tagihanPeriode;
    $data['waktu'] = $isHistorical ? ($requestedPeriode . '-01') : $periodeTagihan;
    $data['langganan_selesai'] = $masaAktifSampai;
    $data['status_bayar'] = $statusBayar;
    if (!$isHistorical) $detail['nama_ktp'] = $namaKtp ?? ($detail['nama_ktp'] ?? '');
    if (!$isHistorical) $detail['nik'] = $nik ?? ($detail['nik'] ?? '');
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Tagihan Pelanggan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root{--bg:#f5f7fb;--card:#fff;--accent:#3498db;--muted:#6c757d;--danger:#e74c3c}
        *{box-sizing:border-box}html,body{height:100%;margin:0;font-family:Segoe UI,Arial;background:var(--bg);color:#222}
        .wrap{min-height:100%;display:flex;align-items:center;justify-content:center;padding:0}
        .card{width:100%;height:100%;background:var(--card);overflow:auto}.card__body{padding:22px;min-height:100%;display:flex;flex-direction:column}
        .grid{display:flex;gap:12px;flex-wrap:wrap}.col{flex:1;min-width:160px}
        label.small{display:block;color:var(--muted);font-size:13px;margin-bottom:6px}
        input,select{width:100%;padding:10px 12px;border-radius:8px;border:1px solid #e6eef6;background:#fff;font-size:14px;box-sizing:border-box}
        .note{font-size:12px;color:var(--muted);margin-top:6px;line-height:1.4}
        .actions{display:flex;gap:10px;justify-content:flex-end;padding:12px 0 0;margin-top:auto}
        .btn{padding:10px 14px;border-radius:8px;border:0;cursor:pointer;font-weight:600;text-decoration:none;display:inline-block;text-align:center}
        .btn-primary{background:linear-gradient(90deg,var(--accent),#2980b9);color:#fff}.btn-muted{background:#f1f5f9;color:#213}
        .error-message{background:#ffeeee;color:var(--danger);padding:12px;border-radius:6px;margin-bottom:15px;border-left:4px solid var(--danger)}
        
        /* Tombol Aksi Riwayat & Diskon */
        .btn-action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s ease;
        }
        .btn-edit-item {
            background: #e0f2fe;
            color: #0369a1;
        }
        .btn-edit-item:hover {
            background: #bae6fd;
            color: #0284c7;
            transform: scale(1.08);
        }
        .btn-delete-item {
            background: #fee2e2;
            color: #b91c1c;
            margin-left: 4px;
        }
        .btn-delete-item:hover {
            background: #fecaca;
            color: #dc2626;
            transform: scale(1.08);
        }
        .btn-add-riwayat {
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-add-riwayat:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(37,99,235,0.25);
        }
        
        @media(max-width:520px){.grid{flex-direction:column}.actions{flex-direction:column}.btn{width:100%}}
    
        /* MOBILE RESPONSIVE FIX FINAL — UI ONLY */
        @media(max-width:520px){
            html,body{height:100%;min-height:100%;overflow:hidden}
            .wrap{height:100%;min-height:0;display:block}
            .card{height:100%;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;touch-action:pan-y}
            .card__body{padding:16px 14px 22px;min-height:100%;display:flex;flex-direction:column}
            .grid{flex-direction:column;gap:12px}.col{width:100%;min-width:0}
            input,select,textarea{max-width:100%;min-height:44px;font-size:16px}
            input[type="file"]{width:100%;min-width:0;max-width:100%}
            .actions{flex-direction:column-reverse;gap:8px;padding-bottom:max(6px,env(safe-area-inset-bottom))}
            .btn{width:100%;min-height:44px;display:flex;align-items:center;justify-content:center}
            img{max-width:100%;height:auto}
        }
</style>
</head>
<body>
<div class="wrap">
    <div class="card" role="dialog" aria-label="Edit Tagihan Pelanggan">
        <div class="card__body">
            <?php if (!empty($errorMessage)): ?>
                <div class="error-message"><strong>Error:</strong> <?= htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <?php if ($isHistorical): ?>
                <div style="margin-bottom:14px;padding:10px 12px;border-radius:9px;background:#eef6ff;border:1px solid #cfe4fb;color:#315b7d;font-size:13px;line-height:1.45;">
                    <b>Periode lama: <?= htmlspecialchars(salamBulananIndonesia($requestedPeriode . '-01', false)); ?></b><br>
                    Data pelanggan ditampilkan sebagai referensi. Yang dapat diperbarui di halaman ini hanya nominal tagihan, masa aktif, dan status pembayaran periode tersebut.
                </div>
                <?php endif; ?>
                <div style="font-weight:700;color:#2c3e50;margin-bottom:10px;">Data Pelanggan</div>
                <div class="grid">
                    <div class="col">
                        <label class="small">Nama</label>
                        <input type="text" name="nama" <?= $isHistorical ? 'readonly' : ''; ?> value="<?= htmlspecialchars((string) $data['nama']); ?>" required>
                    </div>
                    <div class="col">
                        <label class="small">ID Pelanggan</label>
                        <input type="text" name="id_pelanggan" <?= $isHistorical ? 'readonly' : ''; ?> value="<?= htmlspecialchars((string) ($data['id_pelanggan'] ?? '')); ?>">
                    </div>
                </div>

                <div class="grid" style="margin-top:12px">
                    <div class="col">
                        <label class="small">Nomor WhatsApp</label>
                        <input type="text" name="nomor_pelanggan" <?= $isHistorical ? 'readonly' : ''; ?> value="<?= htmlspecialchars((string) ($data['nomor_pelanggan'] ?? '')); ?>" placeholder="08... / 62...">
                    </div>
                    <div class="col">
                        <label class="small">Kode Pelanggan</label>
                        <input type="text" name="kode_pelanggan" <?= $isHistorical ? 'readonly' : ''; ?> value="<?= htmlspecialchars((string) ($data['kode_pelanggan'] ?? '')); ?>">
                    </div>
                </div>

                <div class="grid" style="margin-top:12px">
                    <div class="col">
                        <label class="small">Alamat</label>
                        <input type="text" name="alamat" list="alamat-salam-options" autocomplete="off" value="<?= htmlspecialchars($canAccessAllWilayah ? salamNamaWilayahTampilan((string) ($data['alamat'] ?? '')) : salamWilayahLogin()); ?>" placeholder="Pilih alamat atau ketik manual" <?= ($canAccessAllWilayah && !$isHistorical) ? '' : 'readonly'; ?>>
                        <datalist id="alamat-salam-options">
                            <?php foreach (array_values(salamDaftarWilayahResmi()) as $wilayahOption): ?>
                                <option value="<?= htmlspecialchars($wilayahOption); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col">
                        <label class="small">Paket</label>
                        <input type="text" name="paket" <?= $isHistorical ? 'readonly' : ''; ?> value="<?= htmlspecialchars((string) ($data['paket'] ?? '')); ?>" required>
                    </div>
                </div>

                <?php if (!$isHistorical): ?>
                <div id="paket-change-note-box" style="margin-top:12px;padding:12px 14px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;">
                    <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                        <span>📋</span> Catatan Perubahan Paket (Jika Paket / Tarif Diubah)
                    </div>
                    <div class="grid">
                        <div class="col" style="flex:2;">
                            <label class="small">Alasan / Keterangan Perubahan</label>
                            <input type="text" name="alasan_paket" placeholder="Contoh: Upgrade kecepatan / permintaan pelanggan" value="">
                        </div>
                        <div class="col" style="flex:1;min-width:160px;">
                            <label class="small">Tanggal Efektif</label>
                            <input type="date" name="tanggal_efektif_paket" value="<?= htmlspecialchars((string) ($data['waktu'] ?? date('Y-m-d'))); ?>">
                        </div>
                    </div>
                    <div class="note">Diisi jika Anda mengubah nama paket atau tarif agar riwayat perubahan (Upgrade / Downgrade) tercatat di sistem.</div>
                </div>
                <?php endif; ?>

                <div style="margin-top:18px;padding-top:14px;border-top:1px solid #e6eef6;">
                    <div style="font-weight:700;color:#2c3e50;margin-bottom:10px;">Data Identitas</div>
                    <div class="grid">
                        <div class="col">
                            <label class="small">Nama sesuai KTP</label>
                            <input type="text" name="nama_ktp" <?= $isHistorical ? 'readonly' : ''; ?> value="<?= htmlspecialchars((string) ($detail['nama_ktp'] ?? '')); ?>">
                        </div>
                        <div class="col">
                            <label class="small">NIK</label>
                            <input type="text" name="nik" <?= $isHistorical ? 'readonly' : ''; ?> inputmode="numeric" maxlength="32" value="<?= htmlspecialchars((string) ($detail['nik'] ?? '')); ?>">
                        </div>
                    </div>
                    <div class="grid" style="margin-top:12px">
                        <div class="col">
                            <label class="small">Foto Rumah <span style="font-weight:400;color:#7f8c8d;">(opsional)</span></label>
                            <div style="margin-bottom:8px;">
                                <?php if (!empty($detail['foto_rumah'])): ?>
                                    <img src="<?= htmlspecialchars((string) $detail['foto_rumah']); ?>"
                                         alt="Foto rumah"
                                         style="width:100%;max-width:240px;height:130px;object-fit:cover;border-radius:10px;border:1px solid #e2e8f0;background:#f8fafc;">
                                <?php else: ?>
                                    <div style="width:100%;max-width:240px;height:130px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;color:#94a3b8;display:flex;align-items:center;justify-content:center;text-align:center;padding:12px;">
                                        <span>🏠<br>Foto rumah belum tersedia</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="foto_rumah" accept="image/jpeg,image/png,image/webp" <?= $isHistorical ? 'disabled' : ''; ?>>
                            <div class="note">
                                Jika tidak memilih foto baru, foto lama tetap digunakan. Maksimal 5 MB (JPG/PNG/WEBP).
                            </div>
                        </div>
                    </div>


                </div>

                <div style="margin-top:18px;padding-top:14px;border-top:1px solid #e6eef6;">
                    <div style="font-weight:700;color:#2c3e50;margin-bottom:10px;">Koordinat Billing <span style="font-weight:400;color:#64748b;font-size:12px;">(Opsional dan dapat diedit)</span></div>
                    <div class="grid">
                        <div class="col">
                            <label class="small">Koordinat X / Longitude</label>
                            <input type="number" name="koordinat_x" min="-180" max="180" step="any" <?= $isHistorical ? 'readonly' : ''; ?> value="<?= htmlspecialchars((string) ($detail['koordinat_x'] ?? '')); ?>" placeholder="Contoh: 110.483205">
                        </div>
                        <div class="col">
                            <label class="small">Koordinat Y / Latitude</label>
                            <input type="number" name="koordinat_y" min="-90" max="90" step="any" <?= $isHistorical ? 'readonly' : ''; ?> value="<?= htmlspecialchars((string) ($detail['koordinat_y'] ?? '')); ?>" placeholder="Contoh: -7.852331">
                        </div>
                    </div>
                    <div class="note">Koordinat ini milik Billing dan hanya menjadi fallback pencocokan. Tidak mengubah koordinat, IP, username, atau status PPPoE.</div>
                </div>

                <div style="margin-top:18px;padding-top:14px;border-top:1px solid #e6eef6;">
                    <div style="font-weight:700;color:#2c3e50;margin-bottom:10px;">Tagihan</div>
                <div class="grid" style="margin-top:12px">
                    <div class="col">
                        <label class="small">Periode Tagihan</label>
                        <input type="date" name="waktu" value="<?= htmlspecialchars((string) ($data['waktu'] ?? '')); ?>" required <?= $isHistorical ? 'readonly' : ''; ?>>
                        
                    </div>
                    <div class="col">
                        <label class="small">Masa Aktif Sampai</label>
                        <input type="date" name="langganan_selesai" value="<?= htmlspecialchars((string) ($data['langganan_selesai'] ?? '')); ?>" required>
                        
                    </div>
                </div>

                <div class="grid" style="margin-top:12px">
                    <div class="col">
                        <label class="small">Tagihan</label>
                        <input type="number" id="tagihan" name="tagihan" value="<?= htmlspecialchars((string) ($data['tagihan'] ?? 0)); ?>" min="0" step="1" required>
                        <input type="hidden" id="tarif-tersimpan" value="<?= htmlspecialchars((string) ($data['tarif_langganan'] ?? $data['tagihan'] ?? 0)); ?>">
                        
                    </div>
                    <div class="col">
                        <label class="small">Status Bayar</label>
                        <select name="status_bayar">
                            <option value="Lunas" <?= ($data['status_bayar'] ?? '') === 'Lunas' ? 'selected' : ''; ?>>Lunas</option>
                            <option value="Belum Lunas" <?= ($data['status_bayar'] ?? '') !== 'Lunas' ? 'selected' : ''; ?>>Belum Lunas</option>
                        </select>
                        
                    </div>
                </div>

                <div class="grid" style="margin-top:12px;background:#faf5ff;padding:12px;border-radius:8px;border:1px solid #f3e8ff;">
                    <div class="col">
                        <label class="small" style="font-weight:700;color:#6b21a8;display:flex;align-items:center;gap:4px;">
                            <span>🏷️ Potongan Diskon (Rp)</span>
                            <span style="font-weight:400;color:#9333ea;font-size:11px;">(Kasus khusus potongan tagihan)</span>
                        </label>
                        <input type="number" id="nominal_diskon" name="nominal_diskon" 
                               value="<?= htmlspecialchars((string) ($diskonPeriodeIni['nominal_diskon'] ?? '')); ?>" 
                               min="0" step="1000" placeholder="0" style="border-color:#d8b4fe;">
                    </div>
                    <div class="col">
                        <label class="small" style="font-weight:700;color:#6b21a8;">Keterangan / Alasan Diskon</label>
                        <input type="text" name="keterangan_diskon" id="keterangan_diskon" 
                               value="<?= htmlspecialchars((string) ($diskonPeriodeIni['keterangan'] ?? '')); ?>" 
                               placeholder="Contoh: disk 20k / Promo khusus / Keringanan" style="border-color:#d8b4fe;">
                    </div>
                    <div class="col-12 note" style="color:#7e22ce;margin-top:6px;width:100%;">
                        <i class="fas fa-info-circle"></i> <strong>Catatan Diskon:</strong> Diskon memotong nominal tagihan pada periode ini tanpa mengubah paket langganan dasar (berbeda dari upgrade/downgrade).
                    </div>
                </div>

                </div>

                <div style="margin-top:18px;padding-top:14px;border-top:1px solid #e6eef6;">
                    <div style="margin-top:0;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
                        <div style="font-weight:700;color:#2c3e50;margin-bottom:8px;">Informasi Jaringan <span style="font-weight:400;color:#64748b;font-size:12px;">(Hanya lihat)</span></div>
                        <div class="grid">
                            <div class="col">
                                <label class="small">Koordinat X / Longitude</label>
                                <div id="pppoe-coordinate-x" style="padding:10px 12px;border-radius:8px;background:#eef2f7;color:#475569;">Mencocokkan otomatis...</div>
                            </div>
                            <div class="col">
                                <label class="small">Koordinat Y / Latitude</label>
                                <div id="pppoe-coordinate-y" style="padding:10px 12px;border-radius:8px;background:#eef2f7;color:#475569;">Mencocokkan otomatis...</div>
                            </div>
                        </div>
                        <div class="grid" style="margin-top:10px">
                            <div class="col">
                                <label class="small">IP / User / Status</label>
                                <div id="pppoe-network-info" style="padding:10px 12px;border-radius:8px;background:#eef2f7;color:#475569;">Data berasal dari PPPoE asli.</div>
                            </div>
                        </div>
                        <div id="pppoe-coordinate-note" class="note">
                            Billing yang menyesuaikan ke data PPPoE. Admin tidak dapat mengubah ID teknis, username, koordinat PPPoE, IP, atau status jaringan.
                        </div>
                        <?php if (!$isHistorical): ?>
                        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" id="pppoe-manual-choose" class="btn btn-primary">Pilih PPPoE Manual</button>
                            <button type="button" id="pppoe-manual-disconnect" class="btn btn-muted" style="display:none;">Putuskan Koneksi Manual</button>
                        </div>
                        <div id="pppoe-manual-list" style="display:none;margin-top:10px;padding:10px;border:1px solid #dbe5ee;border-radius:8px;background:#fff;"></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e6eef6;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                        <div style="font-weight:700;color:#2c3e50;font-size:14px;display:flex;align-items:center;gap:6px;">
                            <span style="display:inline-flex;width:24px;height:24px;border-radius:6px;background:#e0f2fe;color:#0284c7;align-items:center;justify-content:center;font-size:12px;">📊</span>
                            Riwayat Perubahan Paket (Upgrade / Downgrade)
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <button type="button" class="btn-add-riwayat" onclick="openTambahRiwayatModal()" title="Tambah catatan riwayat perubahan paket manual">
                                <i class="fas fa-plus"></i> Tambah Riwayat
                            </button>
                            <span style="background:#f1f5f9;color:#475569;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;">
                                <?= count($riwayatPaket); ?> Catatan
                            </span>
                        </div>
                    </div>

                    <?php if (empty($riwayatPaket)): ?>
                        <div style="padding:20px 16px;text-align:center;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;color:#64748b;font-size:13px;">
                            <p style="margin:0 0 10px 0;">Belum ada riwayat perubahan paket untuk pelanggan ini.</p>
                            <button type="button" class="btn-add-riwayat" onclick="openTambahRiwayatModal()">
                                <i class="fas fa-plus"></i> Tambah Catatan Riwayat Baru
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;background:#fff;">
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;font-size:12px;text-align:left;">
                                    <thead>
                                        <tr style="background:#f8fafc;color:#475569;border-bottom:1px solid #e2e8f0;">
                                            <th style="padding:8px 10px;font-weight:600;">No</th>
                                            <th style="padding:8px 10px;font-weight:600;">Tanggal Efektif</th>
                                            <th style="padding:8px 10px;font-weight:600;">Jenis</th>
                                            <th style="padding:8px 10px;font-weight:600;">Perubahan Paket</th>
                                            <th style="padding:8px 10px;font-weight:600;">Perubahan Tarif</th>
                                            <th style="padding:8px 10px;font-weight:600;">Selisih</th>
                                            <th style="padding:8px 10px;font-weight:600;">Alasan</th>
                                            <th style="padding:8px 10px;font-weight:600;">Dicatat Oleh</th>
                                            <th style="padding:8px 10px;font-weight:600;text-align:center;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $noR = 1; foreach ($riwayatPaket as $item):
                                            $jenis = (string) ($item['jenis_perubahan'] ?? 'Penyesuaian Tarif');
                                            $selisih = (float) ($item['selisih_tarif'] ?? 0);
                                            $badgeStyle = 'background:#f1f5f9;color:#475569;';
                                            $icon = '↔';
                                            if ($jenis === 'Upgrade') {
                                                $badgeStyle = 'background:#dcfce7;color:#15803d;';
                                                $icon = '↑';
                                            } elseif ($jenis === 'Downgrade') {
                                                $badgeStyle = 'background:#ffedd5;color:#c2410c;';
                                                $icon = '↓';
                                            }
                                            $isHistoricalRow = (($item['sumber'] ?? 'manual') === 'deteksi_historis');
                                            $tglPerubahan = !empty($item['tanggal_perubahan']) ? salamTanggalWaktuIndonesia($item['tanggal_perubahan']) : '-';
                                            $tglEfektif = !empty($item['tanggal_efektif']) ? salamBulananIndonesia($item['tanggal_efektif'], true) : '-';
                                            $rowPayload = [
                                                'id' => (int) ($item['id'] ?? 0),
                                                'pelanggan_id' => (int) $id,
                                                'tanggal_efektif' => (string) ($item['tanggal_efektif'] ?? date('Y-m-d')),
                                                'jenis_perubahan' => $jenis,
                                                'paket_lama' => (string) ($item['paket_lama'] ?? ''),
                                                'paket_baru' => (string) ($item['paket_baru'] ?? ''),
                                                'tarif_lama' => (float) ($item['tarif_lama'] ?? 0),
                                                'tarif_baru' => (float) ($item['tarif_baru'] ?? 0),
                                                'alasan' => (string) ($item['alasan'] ?? ''),
                                                'sumber' => (string) ($item['sumber'] ?? 'manual'),
                                            ];
                                        ?>
                                        <tr style="border-bottom:1px solid #f1f5f9;">
                                            <td style="padding:8px 10px;color:#94a3b8;"><?= $noR++; ?></td>
                                            <td style="padding:8px 10px;white-space:nowrap;">
                                                <div style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($tglEfektif); ?></div>
                                                <?php if (!$isHistoricalRow && $tglPerubahan !== '-'): ?>
                                                    <div style="font-size:10px;color:#94a3b8;">Dibuat: <?= htmlspecialchars($tglPerubahan); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:8px 10px;white-space:nowrap;">
                                                <span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;<?= $badgeStyle; ?>">
                                                    <?= $icon . ' ' . htmlspecialchars($jenis); ?>
                                                </span>
                                            </td>
                                            <td style="padding:8px 10px;">
                                                <div style="color:#64748b;font-size:11px;"><?= htmlspecialchars((string)$item['paket_lama']); ?></div>
                                                <div style="font-weight:600;color:#0f172a;">➔ <?= htmlspecialchars((string)$item['paket_baru']); ?></div>
                                            </td>
                                            <td style="padding:8px 10px;white-space:nowrap;">
                                                <div style="color:#64748b;font-size:11px;"><?= htmlspecialchars(salamRupiah($item['tarif_lama'] ?? 0)); ?></div>
                                                <div style="font-weight:600;color:#0f172a;">➔ <?= htmlspecialchars(salamRupiah($item['tarif_baru'] ?? 0)); ?></div>
                                            </td>
                                            <td style="padding:8px 10px;white-space:nowrap;">
                                                <?php if ($selisih > 0): ?>
                                                    <span style="font-weight:700;color:#16a34a;">+<?= htmlspecialchars(salamRupiah($selisih)); ?></span>
                                                <?php elseif ($selisih < 0): ?>
                                                    <span style="font-weight:700;color:#dc2626;"><?= htmlspecialchars(salamRupiah($selisih)); ?></span>
                                                <?php else: ?>
                                                    <span style="color:#64748b;">Rp0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:8px 10px;max-width:220px;color:#334155;">
                                                <?= htmlspecialchars((string)($item['alasan'] ?: '-')); ?>
                                                <?php if ($isHistoricalRow): ?>
                                                    <div style="font-size:10px;color:#64748b;font-style:italic;">(Deteksi Arsip Tagihan)</div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:8px 10px;white-space:nowrap;color:#64748b;font-size:11px;">
                                                <?= htmlspecialchars((string)($item['admin_nama'] ?: 'Admin')); ?>
                                            </td>
                                            <td style="padding:8px 10px;white-space:nowrap;text-align:center;">
                                                <button type="button" class="btn-action-icon btn-edit-item" 
                                                        onclick='openEditRiwayatModal(<?= json_encode($rowPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' 
                                                        title="Edit Riwayat Perubahan Paket">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn-action-icon btn-delete-item" 
                                                        onclick="hapusRiwayatPaket(<?= (int)($item['id'] ?? 0); ?>, '<?= htmlspecialchars(addslashes((string)$item['paket_baru'])); ?>', '<?= (string)($item['tanggal_efektif'] ?? ''); ?>')" 
                                                        title="Hapus Riwayat Perubahan Paket">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Riwayat Diskon Pelanggan -->
                <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e6eef6;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                        <div style="font-weight:700;color:#2c3e50;font-size:14px;display:flex;align-items:center;gap:6px;">
                            <span style="display:inline-flex;width:24px;height:24px;border-radius:6px;background:#f3e8ff;color:#7e22ce;align-items:center;justify-content:center;font-size:12px;">🏷️</span>
                            Riwayat Diskon Pelanggan
                        </div>
                        <span style="background:#f3e8ff;color:#6b21a8;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;">
                            <?= count($riwayatDiskon); ?> Catatan Diskon
                        </span>
                    </div>

                    <?php if (empty($riwayatDiskon)): ?>
                        <div style="padding:16px;text-align:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;color:#94a3b8;font-size:13px;">
                            Belum ada riwayat diskon khusus untuk pelanggan ini (tagihan berjalan normal sesuai paket).
                        </div>
                    <?php else: ?>
                        <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;background:#fff;">
                            <div style="padding:9px 12px;background:#faf5ff;border-bottom:1px solid #f3e8ff;font-size:12px;color:#6b21a8;display:flex;align-items:center;gap:6px;">
                                <i class="fas fa-info-circle"></i>
                                <span><strong>Kasus Khusus Diskon:</strong> Diskon memotong nominal tagihan pada periode tertentu dan <u>terpisah</u> dari perubahan paket (upgrade/downgrade).</span>
                            </div>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;font-size:12px;text-align:left;">
                                    <thead>
                                        <tr style="background:#f8fafc;color:#475569;border-bottom:1px solid #e2e8f0;">
                                            <th style="padding:8px 10px;font-weight:600;">No</th>
                                            <th style="padding:8px 10px;font-weight:600;">Periode Tagihan</th>
                                            <th style="padding:8px 10px;font-weight:600;">Tarif Normal</th>
                                            <th style="padding:8px 10px;font-weight:600;">Potongan Diskon</th>
                                            <th style="padding:8px 10px;font-weight:600;">Tagihan Bersih</th>
                                            <th style="padding:8px 10px;font-weight:600;">Keterangan Billing</th>
                                            <th style="padding:8px 10px;font-weight:600;">Sumber Data</th>
                                            <th style="padding:8px 10px;font-weight:600;text-align:center;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $noD = 1; foreach ($riwayatDiskon as $dItem):
                                            $nomDisk = (float) ($dItem['nominal_diskon'] ?? 0);
                                            $tglPeriode = salamBulananIndonesia($dItem['periode'] . '-01', false);
                                        ?>
                                        <tr style="border-bottom:1px solid #f1f5f9;">
                                            <td style="padding:8px 10px;color:#94a3b8;"><?= $noD++; ?></td>
                                            <td style="padding:8px 10px;font-weight:600;color:#1e293b;white-space:nowrap;"><?= htmlspecialchars($tglPeriode); ?></td>
                                            <td style="padding:8px 10px;color:#64748b;white-space:nowrap;"><?= htmlspecialchars(salamRupiah($dItem['tarif_normal'] ?? 0)); ?></td>
                                            <td style="padding:8px 10px;white-space:nowrap;">
                                                <span style="font-weight:700;color:#7e22ce;">-<?= htmlspecialchars(salamRupiah($nomDisk)); ?></span>
                                                <span style="display:inline-block;padding:1px 6px;background:#f3e8ff;color:#6b21a8;border-radius:10px;font-size:10px;font-weight:700;margin-left:4px;">
                                                    <?= htmlspecialchars($dItem['label_singkat']); ?>
                                                </span>
                                            </td>
                                            <td style="padding:8px 10px;font-weight:700;color:#16a34a;white-space:nowrap;"><?= htmlspecialchars(salamRupiah($dItem['tarif_setelah_diskon'] ?? 0)); ?></td>
                                            <td style="padding:8px 10px;color:#334155;font-style:italic;"><?= htmlspecialchars((string)($dItem['keterangan'] ?: '-')); ?></td>
                                            <td style="padding:8px 10px;color:#64748b;font-size:11px;white-space:nowrap;"><?= htmlspecialchars((string)($dItem['admin_nama'] ?: 'Sistem (Data Billing)')); ?></td>
                                            <td style="padding:8px 10px;white-space:nowrap;text-align:center;">
                                                <button type="button" class="btn-action-icon btn-delete-item" 
                                                        onclick="hapusDiskonPelanggan(<?= (int)($dItem['id'] ?? 0); ?>, '<?= htmlspecialchars(addslashes((string)$tglPeriode)); ?>')" 
                                                        title="Hapus Catatan Diskon">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="actions">
                    <?php if ($isModal): ?>
                        <button type="button" class="btn btn-muted" onclick="window.parent.postMessage(JSON.stringify({action:'close'}),'*')">Batal</button>
                    <?php else: ?>
                        <a class="btn btn-muted" href="dashboard_salam.php">Batal</a>
                    <?php endif; ?>
                    <button type="submit" name="update" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    const defaults = <?= json_encode(array_values(salamDaftarWilayahResmi()), JSON_UNESCAPED_UNICODE); ?>;
    const datalist = document.getElementById('alamat-salam-options');
    if (!datalist) return;

    function escapeOptionValue(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function render(items) {
        const seen = new Set();
        const merged = [];
        [...defaults, ...(Array.isArray(items) ? items : [])].forEach(item => {
            const value = String(item || '').trim();
            if (!value || value.toLowerCase() === 'semua' || value.toLowerCase() === 'semua alamat') return;
            const key = value.toLowerCase().replace(/\s+/g, ' ');
            if (seen.has(key)) return;
            seen.add(key);
            merged.push(value);
        });
        datalist.innerHTML = merged.map(item => `<option value="${escapeOptionValue(item)}"></option>`).join('');
    }

    render(defaults);
    fetch('get_alamat_salam.php')
        .then(response => response.json())
        .then(data => render(Array.isArray(data.alamat) ? data.alamat : []))
        .catch(() => render(defaults));
})();
</script>
<script>
(function () {
    const status = document.querySelector('select[name="status_bayar"]');
    const tagihan = document.getElementById('tagihan');
    const tarifTersimpan = document.getElementById('tarif-tersimpan');
    if (!status || !tagihan || !tarifTersimpan) return;

    status.addEventListener('change', function () {
        if (this.value === 'Lunas') {
            tagihan.value = '0';
            return;
        }
        if (Number(tagihan.value || 0) <= 0) {
            tagihan.value = tarifTersimpan.value || '0';
        }
    });

    const nominalDiskonInput = document.getElementById('nominal_diskon');
    if (nominalDiskonInput) {
        nominalDiskonInput.addEventListener('input', function () {
            const disk = Number(this.value || 0);
            const base = Number(tarifTersimpan.value || 0);
            if (status.value !== 'Lunas') {
                if (disk > 0 && base > 0) {
                    tagihan.value = Math.max(0, base - disk);
                } else if (disk === 0 && base > 0) {
                    tagihan.value = base;
                }
            }
        });
    }
})();
</script>
<script>
(function () {
    const billingCustomerId = <?= (int) $id ?>;
    const xBox = document.getElementById('pppoe-coordinate-x');
    const yBox = document.getElementById('pppoe-coordinate-y');
    const networkBox = document.getElementById('pppoe-network-info');
    const note = document.getElementById('pppoe-coordinate-note');
    const chooseButton = document.getElementById('pppoe-manual-choose');
    const disconnectButton = document.getElementById('pppoe-manual-disconnect');
    const candidateList = document.getElementById('pppoe-manual-list');
    const csrfToken = <?= json_encode($pppoeManualCsrf) ?>;

    if (!xBox || !yBox || !networkBox) return;

    async function loadReadonlyPppoeInfo() {
        try {
            const response = await fetch('get_pppoe_readonly.php', { cache: 'no-store' });
            const payload = await response.json();

            if (!response.ok || !payload.success || !Array.isArray(payload.data)) {
                throw new Error(payload.message || 'Data PPPoE tidak tersedia.');
            }

            const item = payload.data.find(row =>
                row.billing && Number(row.billing.id) === Number(billingCustomerId)
            );

            if (!item) {
                xBox.textContent = '-';
                yBox.textContent = '-';
                networkBox.textContent = 'Belum ditemukan pasangan otomatis pada data PPPoE.';
                if (note) {
                    note.textContent = 'Data Billing tetap dapat diedit. Sistem tidak membuat koordinat atau status dummy.';
                }
                if (chooseButton) chooseButton.textContent = 'Pilih PPPoE Manual';
                if (disconnectButton) disconnectButton.style.display = 'none';
                return;
            }

            xBox.textContent = item.longitude ?? '-';
            yBox.textContent = item.latitude ?? '-';
            networkBox.textContent =
                `IP: ${item.ip || '-'} • User: ${item.user || '-'} • Status: ${item.status || 'UNKNOWN'}`;

            if (note) {
                note.textContent = `${item.match_method === 'manual' ? 'Terhubung manual' : 'Terhubung otomatis'} ke ID PPPoE ${item.id || '-'}. Semua informasi jaringan hanya dibaca dari PPPoE.`;
            }
            if (chooseButton) chooseButton.textContent = item.match_method === 'manual' ? 'Ganti PPPoE Manual' : 'Pilih PPPoE Manual';
            if (disconnectButton) disconnectButton.style.display = item.match_method === 'manual' ? 'inline-flex' : 'none';
        } catch (error) {
            xBox.textContent = 'Tidak tersedia';
            yBox.textContent = 'Tidak tersedia';
            networkBox.textContent = 'Sumber PPPoE sedang tidak dapat dibaca.';
            if (note) {
                note.textContent = 'Data Billing tetap dapat diedit tanpa mengubah PPPoE.';
            }
        }
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    }

    async function saveManual(action, pppoeId = '') {
        const response = await fetch('pppoe_manual_connect.php', {
            method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-Token':csrfToken},
            body: JSON.stringify({action, billing_id: billingCustomerId, pppoe_id: pppoeId})
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Proses koneksi manual gagal.');
        alert(payload.message);
        if (candidateList) candidateList.style.display = 'none';
        await loadReadonlyPppoeInfo();
    }

    if (chooseButton && candidateList) chooseButton.addEventListener('click', async function () {
        candidateList.style.display = 'block';
        candidateList.textContent = 'Mencari kandidat PPPoE dari wilayah yang sama...';
        try {
            const response = await fetch(`pppoe_manual_connect.php?billing_id=${billingCustomerId}`, {cache:'no-store'});
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Kandidat tidak dapat dibaca.');
            if (!payload.candidates.length) { candidateList.textContent = 'Tidak ada kandidat PPPoE pada wilayah yang sama.'; return; }
            candidateList.innerHTML = '<b>Pilih salah satu kandidat:</b>' + payload.candidates.map(item => {
                const distance = item.distance === null ? 'jarak tidak tersedia' : `${item.distance} meter`;
                return `<div style="margin-top:8px;padding:9px;border:1px solid #e2e8f0;border-radius:7px;display:flex;justify-content:space-between;gap:8px;align-items:center"><span><b>${escapeHtml(item.user || item.lokasi || item.id)}</b><br><small>${escapeHtml(item.lokasi)} • ${escapeHtml(item.status)} • kemiripan ${escapeHtml(item.score)}% • ${escapeHtml(distance)}</small></span><button type="button" class="btn btn-primary" data-pppoe-id="${escapeHtml(item.id)}">Hubungkan</button></div>`;
            }).join('');
            candidateList.querySelectorAll('[data-pppoe-id]').forEach(button => button.addEventListener('click', () => {
                if (confirm('Hubungkan pelanggan ini ke PPPoE yang dipilih?')) saveManual('connect', button.dataset.pppoeId).catch(error => alert(error.message));
            }));
        } catch (error) { candidateList.textContent = error.message; }
    });
    if (disconnectButton) disconnectButton.addEventListener('click', function () {
        if (confirm('Putuskan pilihan manual dan gunakan matching otomatis kembali?')) saveManual('disconnect').catch(error => alert(error.message));
    });

    loadReadonlyPppoeInfo();
})();
</script>

<script>
// --- LOGIKA CRUD RIWAYAT PERUBAHAN PAKET & DISKON ---
(function() {
    const currentPelangganId = <?= (int) $id; ?>;
    const currentPaketNama = <?= json_encode((string) ($data['paket'] ?? '')); ?>;
    const currentTarif = <?= (float) ($data['tarif_langganan'] ?? $data['tagihan'] ?? 0); ?>;

    function safeEscape(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    }

    function getRiwayatModalHtml(isEdit, data = {}) {
        const tgl = data.tanggal_efektif || <?= json_encode(date('Y-m-d')); ?>;
        const pLama = data.paket_lama !== undefined ? data.paket_lama : currentPaketNama;
        const pBaru = data.paket_baru !== undefined ? data.paket_baru : '';
        const tLama = data.tarif_lama !== undefined ? data.tarif_lama : currentTarif;
        const tBaru = data.tarif_baru !== undefined ? data.tarif_baru : '';
        const jenis = data.jenis_perubahan || 'Penyesuaian Tarif';
        const alasan = data.alasan || '';

        return `
        <div style="text-align:left;font-size:13px;color:#334155;padding:4px 2px;">
            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:600;margin-bottom:5px;color:#1e293b;">
                    Tanggal Efektif <span style="color:#ef4444;">*</span>
                </label>
                <input type="date" id="swal-tgl-efektif" class="swal2-input" 
                       style="width:100%;margin:0;font-size:13px;height:38px;padding:6px 10px;" 
                       value="${safeEscape(tgl)}">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:5px;color:#1e293b;">Paket Lama</label>
                    <input type="text" id="swal-paket-lama" class="swal2-input" 
                           style="width:100%;margin:0;font-size:13px;height:38px;padding:6px 10px;" 
                           placeholder="Contoh: Paket 150K" value="${safeEscape(pLama)}">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:5px;color:#1e293b;">
                        Paket Baru <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="text" id="swal-paket-baru" class="swal2-input" 
                           style="width:100%;margin:0;font-size:13px;height:38px;padding:6px 10px;" 
                           placeholder="Contoh: Paket 100K" value="${safeEscape(pBaru)}">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:5px;color:#1e293b;">Tarif Lama (Rp)</label>
                    <input type="number" id="swal-tarif-lama" class="swal2-input" 
                           style="width:100%;margin:0;font-size:13px;height:38px;padding:6px 10px;" 
                           placeholder="150000" step="1000" min="0" value="${tLama}">
                </div>
                <div>
                    <label style="display:block;font-weight:600;margin-bottom:5px;color:#1e293b;">
                        Tarif Baru (Rp) <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="number" id="swal-tarif-baru" class="swal2-input" 
                           style="width:100%;margin:0;font-size:13px;height:38px;padding:6px 10px;" 
                           placeholder="100000" step="1000" min="0" value="${tBaru}">
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:600;margin-bottom:5px;color:#1e293b;">Jenis Perubahan</label>
                <select id="swal-jenis-perubahan" class="swal2-select" 
                        style="width:100%;margin:0;font-size:13px;height:38px;padding:6px 10px;display:block;">
                    <option value="Upgrade" ${jenis === 'Upgrade' ? 'selected' : ''}>↑ Upgrade (Kenaikan Tarif)</option>
                    <option value="Downgrade" ${jenis === 'Downgrade' ? 'selected' : ''}>↓ Downgrade (Penurunan Tarif)</option>
                    <option value="Penyesuaian Tarif" ${jenis === 'Penyesuaian Tarif' ? 'selected' : ''}>↔ Penyesuaian Tarif (Tarif Sama)</option>
                </select>
            </div>

            <div style="margin-bottom:6px;">
                <label style="display:block;font-weight:600;margin-bottom:5px;color:#1e293b;">Alasan / Keterangan</label>
                <input type="text" id="swal-alasan" class="swal2-input" 
                       style="width:100%;margin:0;font-size:13px;height:38px;padding:6px 10px;" 
                       placeholder="Contoh: Permintaan pelanggan / penyesuaian pemakaian" value="${safeEscape(alasan)}">
            </div>
        </div>
        `;
    }

    function attachRiwayatSwalEvents() {
        const tLamaEl = document.getElementById('swal-tarif-lama');
        const tBaruEl = document.getElementById('swal-tarif-baru');
        const jenisEl = document.getElementById('swal-jenis-perubahan');
        if (!tLamaEl || !tBaruEl || !jenisEl) return;

        function autoJenis() {
            const tLama = parseFloat(tLamaEl.value || 0);
            const tBaru = parseFloat(tBaruEl.value || 0);
            if (tLama > 0 && tBaru > 0) {
                if (tBaru > tLama) jenisEl.value = 'Upgrade';
                else if (tBaru < tLama) jenisEl.value = 'Downgrade';
                else jenisEl.value = 'Penyesuaian Tarif';
            }
        }
        tLamaEl.addEventListener('input', autoJenis);
        tBaruEl.addEventListener('input', autoJenis);
    }

    function submitRiwayatApi(payload) {
        Swal.fire({
            title: 'Menyimpan...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        fetch('api_riwayat_paket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: res.message || 'Riwayat perubahan paket berhasil disimpan.',
                    timer: 1400,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Gagal!', res.message || 'Terjadi kesalahan saat menyimpan.', 'error');
            }
        })
        .catch(err => {
            Swal.fire('Error!', err.message || 'Koneksi ke server terputus.', 'error');
        });
    }

    window.openTambahRiwayatModal = function() {
        Swal.fire({
            title: 'Tambah Riwayat Perubahan Paket',
            html: getRiwayatModalHtml(false),
            showCancelButton: true,
            confirmButtonText: 'Simpan Riwayat',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            didOpen: attachRiwayatSwalEvents,
            preConfirm: () => {
                const tgl = document.getElementById('swal-tgl-efektif').value.trim();
                const pBaru = document.getElementById('swal-paket-baru').value.trim();
                const tBaruVal = document.getElementById('swal-tarif-baru').value;
                if (!tgl || !pBaru || tBaruVal === '') {
                    Swal.showValidationMessage('Tanggal Efektif, Paket Baru, dan Tarif Baru wajib diisi.');
                    return false;
                }
                return {
                    action: 'create',
                    pelanggan_id: currentPelangganId,
                    tanggal_efektif: tgl,
                    paket_lama: document.getElementById('swal-paket-lama').value.trim(),
                    paket_baru: pBaru,
                    tarif_lama: parseFloat(document.getElementById('swal-tarif-lama').value || 0),
                    tarif_baru: parseFloat(tBaruVal || 0),
                    jenis_perubahan: document.getElementById('swal-jenis-perubahan').value,
                    alasan: document.getElementById('swal-alasan').value.trim()
                };
            }
        }).then(result => {
            if (result.isConfirmed && result.value) {
                submitRiwayatApi(result.value);
            }
        });
    };

    window.openEditRiwayatModal = function(data) {
        Swal.fire({
            title: 'Edit Riwayat Perubahan Paket',
            html: getRiwayatModalHtml(true, data),
            showCancelButton: true,
            confirmButtonText: 'Perbarui Riwayat',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#0284c7',
            cancelButtonColor: '#64748b',
            didOpen: attachRiwayatSwalEvents,
            preConfirm: () => {
                const tgl = document.getElementById('swal-tgl-efektif').value.trim();
                const pBaru = document.getElementById('swal-paket-baru').value.trim();
                const tBaruVal = document.getElementById('swal-tarif-baru').value;
                if (!tgl || !pBaru || tBaruVal === '') {
                    Swal.showValidationMessage('Tanggal Efektif, Paket Baru, dan Tarif Baru wajib diisi.');
                    return false;
                }
                return {
                    action: (data.id && data.id > 0) ? 'update' : 'create',
                    id: data.id || 0,
                    pelanggan_id: currentPelangganId,
                    tanggal_efektif: tgl,
                    paket_lama: document.getElementById('swal-paket-lama').value.trim(),
                    paket_baru: pBaru,
                    tarif_lama: parseFloat(document.getElementById('swal-tarif-lama').value || 0),
                    tarif_baru: parseFloat(tBaruVal || 0),
                    jenis_perubahan: document.getElementById('swal-jenis-perubahan').value,
                    alasan: document.getElementById('swal-alasan').value.trim()
                };
            }
        }).then(result => {
            if (result.isConfirmed && result.value) {
                submitRiwayatApi(result.value);
            }
        });
    };

    window.hapusRiwayatPaket = function(id, paketBaru, tglEfektif) {
        if (!id || id <= 0) {
            Swal.fire('Informasi', 'Catatan ini tidak dapat dihapus langsung karena belum memiliki ID di database.', 'info');
            return;
        }

        Swal.fire({
            title: 'Hapus Catatan Riwayat?',
            text: `Catatan perubahan paket "${paketBaru}" (${tglEfektif}) akan dihapus secara permanen.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus Catatan',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                submitRiwayatApi({
                    action: 'delete',
                    id: id,
                    pelanggan_id: currentPelangganId
                });
            }
        });
    };

    window.hapusDiskonPelanggan = function(id, periodeLabel) {
        if (!id || id <= 0) return;

        Swal.fire({
            title: 'Hapus Catatan Diskon?',
            text: `Potongan diskon untuk periode ${periodeLabel} akan dihapus secara permanen.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus Diskon',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Menghapus...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                fetch('api_diskon.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: id, pelanggan_id: currentPelangganId })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Terhapus!',
                            text: res.message,
                            timer: 1400,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Gagal!', res.message || 'Gagal menghapus diskon.', 'error');
                    }
                })
                .catch(() => Swal.fire('Error!', 'Tidak dapat menghubungi server.', 'error'));
            }
        });
    };
})();
</script>
</body>
</html>
