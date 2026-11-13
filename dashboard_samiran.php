<?php
include 'koneksi.php';
if (!isset($_SESSION['login_samiran']) || $_SESSION['login_samiran'] !== true) {
    header("Location: login.php");
    exit;
}

$periode_aktif = $_GET['periode'] ?? date('Y-m');
$filter_dusun  = $_GET['dusun'] ?? '';
$filter_ap     = $_GET['ap'] ?? '';
$filter_link   = $_GET['link'] ?? '';
$cari          = $_GET['cari'] ?? '';

// 1. PROSES TAMBAH PELANGGAN & PERANGKAT BARU
if (isset($_POST['simpan_pelanggan'])) {
    $id_pelanggan   = mysqli_real_escape_string($conn, $_POST['id_pelanggan']);
    $nama_lengkap   = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $id_wilayah     = (int)$_POST['id_wilayah'];
    $no_wa          = mysqli_real_escape_string($conn, $_POST['no_wa']);
    $tgl_pasang     = $_POST['tgl_pasang'];
    $ip_perangkat   = mysqli_real_escape_string($conn, $_POST['ip_perangkat']);
    $mac_perangkat  = mysqli_real_escape_string($conn, $_POST['mac_perangkat']);
    $id_ap          = (int)$_POST['id_ap'];
    $kategori_link  = $_POST['kategori_link'];
    $paket          = mysqli_real_escape_string($conn, $_POST['paket']);
    $tagihan_awal   = (int)$_POST['tagihan_awal'];
    $biaya_instal   = (int)$_POST['biaya_instalasi'];
    $sewa_router    = (int)$_POST['sewa_router'];

    $status_pel = ($kategori_link === 'PTP-Backhaul') ? 'Tidak Aktif' : 'Aktif';
    $total      = ($kategori_link === 'PTP-Backhaul') ? 0 : ($tagihan_awal + $biaya_instal + $sewa_router);
    $status_byr = ($total > 0) ? 'Belum Lunas' : 'Lunas';
    $no_inv     = "INV-SMR-" . date("Ym") . "-" . rand(1000, 9999);

    mysqli_query($conn, "INSERT INTO pelanggan_samiran 
        (id_pelanggan, nama_lengkap, id_wilayah, no_wa, tgl_pasang, ip_perangkat, mac_perangkat, id_ap, kategori_link, paket, tarif_bulanan, status_pelanggan)
        VALUES ('$id_pelanggan', '$nama_lengkap', $id_wilayah, '$no_wa', '$tgl_pasang', '$ip_perangkat', '$mac_perangkat', $id_ap, '$kategori_link', '$paket', $tagihan_awal, '$status_pel')");

    if ($kategori_link === 'End-User') {
        mysqli_query($conn, "INSERT INTO tagihan_samiran 
            (id_pelanggan, nomor_invoice, periode_bulan, tagihan_bulanan, biaya_instalasi, sewa_router, total_bayar, status_bayar)
            VALUES ('$id_pelanggan', '$no_inv', '$periode_aktif', $tagihan_awal, $biaya_instal, $sewa_router, $total, '$status_byr')");
    }

    header("Location: dashboard_samiran.php?periode=$periode_aktif");
    exit;
}

// 2. PROSES EDIT TAGIHAN, PERANGKAT, & STATUS OFF BULANAN
if (isset($_POST['update_pelanggan'])) {
    $id_pelanggan   = mysqli_real_escape_string($conn, $_POST['id_pelanggan']);
    $id_tagihan     = (int)$_POST['id_tagihan'];
    $nama_lengkap   = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $id_wilayah     = (int)$_POST['id_wilayah'];
    $no_wa          = mysqli_real_escape_string($conn, $_POST['no_wa']);
    $ip_perangkat   = mysqli_real_escape_string($conn, $_POST['ip_perangkat']);
    $mac_perangkat  = mysqli_real_escape_string($conn, $_POST['mac_perangkat']);
    $id_ap          = (int)$_POST['id_ap'];
    $paket          = mysqli_real_escape_string($conn, $_POST['paket']);
    $riwayat_paket  = mysqli_real_escape_string($conn, $_POST['riwayat_paket']);
    $status_pel     = $_POST['status_pelanggan'];

    // Jika status OFF Bulanan, tagihan bulan berjalan otomatis 0
    $tagihan_bln    = ($status_pel === 'OFF Bulanan') ? 0 : (int)$_POST['tagihan_bulanan'];
    $tunggakan_2025 = (int)$_POST['tunggakan_2025'];
    $biaya_instal   = (int)$_POST['biaya_instalasi'];
    $sewa_router    = (int)$_POST['sewa_router'];
    $diskon         = (int)$_POST['diskon'];
    $status_bayar   = $_POST['status_bayar'];
    $tgl_bayar      = !empty($_POST['tgl_bayar']) ? "'" . $_POST['tgl_bayar'] . "'" : "NULL";
    $ket_rapel      = mysqli_real_escape_string($conn, $_POST['keterangan_rapel']);

    $total_bayar    = max(0, ($tagihan_bln + $tunggakan_2025 + $biaya_instal + $sewa_router) - $diskon);

    mysqli_query($conn, "UPDATE pelanggan_samiran SET 
        nama_lengkap='$nama_lengkap', id_wilayah=$id_wilayah, no_wa='$no_wa', ip_perangkat='$ip_perangkat', 
        mac_perangkat='$mac_perangkat', id_ap=$id_ap, paket='$paket', 
        tarif_bulanan=$tagihan_bln, status_pelanggan='$status_pel', riwayat_paket='$riwayat_paket'
        WHERE id_pelanggan='$id_pelanggan'");

    if ($id_tagihan > 0) {
        mysqli_query($conn, "UPDATE tagihan_samiran SET 
            tagihan_bulanan=$tagihan_bln, tunggakan_2025=$tunggakan_2025, biaya_instalasi=$biaya_instal, 
            sewa_router=$sewa_router, diskon=$diskon, total_bayar=$total_bayar, 
            status_bayar='$status_bayar', tgl_bayar=$tgl_bayar, keterangan_rapel='$ket_rapel'
            WHERE id_tagihan=$id_tagihan");
    } else {
        $no_inv = "INV-SMR-" . date("Ym") . "-" . rand(1000, 9999);
        mysqli_query($conn, "INSERT INTO tagihan_samiran 
            (id_pelanggan, nomor_invoice, periode_bulan, tagihan_bulanan, tunggakan_2025, biaya_instalasi, sewa_router, diskon, total_bayar, status_bayar, tgl_bayar, keterangan_rapel)
            VALUES ('$id_pelanggan', '$no_inv', '$periode_aktif', $tagihan_bln, $tunggakan_2025, $biaya_instal, $sewa_router, $diskon, $total_bayar, '$status_bayar', $tgl_bayar, '$ket_rapel')");
    }

    header("Location: dashboard_samiran.php?periode=$periode_aktif");
    exit;
}

// 3. PROSES HAPUS 1 RECORD PELANGGAN
if (isset($_GET['hapus'])) {
    $id_hapus = mysqli_real_escape_string($conn, $_GET['hapus']);
    mysqli_query($conn, "DELETE FROM pelanggan_samiran WHERE id_pelanggan='$id_hapus'");
    header("Location: dashboard_samiran.php?periode=$periode_aktif");
    exit;
}

// QUERY STATISTIK DASHBOARD
$stat = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(CASE WHEN p.kategori_link = 'End-User' THEN 1 END) AS total_pel,
        SUM(CASE WHEN p.status_pelanggan = 'OFF Bulanan' THEN 1 ELSE 0 END) AS total_off,
        SUM(CASE WHEN t.status_bayar = 'Lunas' THEN t.total_bayar ELSE 0 END) AS total_lunas,
        SUM(CASE WHEN t.status_bayar = 'Belum Lunas' THEN t.total_bayar ELSE 0 END) AS total_belum,
        SUM(t.tunggakan_2025) AS total_tunggakan_2025
    FROM pelanggan_samiran p
    LEFT JOIN tagihan_samiran t ON p.id_pelanggan = t.id_pelanggan AND t.periode_bulan = '$periode_aktif'
"));

// QUERY TABEL UTAMA
$where = "WHERE 1=1";
if ($filter_dusun) $where .= " AND p.id_wilayah = " . (int)$filter_dusun;
if ($filter_ap)    $where .= " AND p.id_ap = " . (int)$filter_ap;
if ($filter_link)  $where .= " AND p.kategori_link = '" . mysqli_real_escape_string($conn, $filter_link) . "'";
if ($cari)         $where .= " AND (p.nama_lengkap LIKE '%$cari%' OR p.id_pelanggan LIKE '%$cari%' OR p.ip_perangkat LIKE '%$cari%' OR p.mac_perangkat LIKE '%$cari%')";

$query_data = mysqli_query($conn, "
    SELECT p.*, w.nama_dusun, a.identity_ap, a.ip_ap, 
           t.id_tagihan, t.nomor_invoice, t.tagihan_bulanan, t.tunggakan_2025, 
           t.biaya_instalasi, t.sewa_router, t.diskon, t.total_bayar, t.status_bayar, t.tgl_bayar, t.keterangan_rapel
    FROM pelanggan_samiran p
    LEFT JOIN master_wilayah_samiran w ON p.id_wilayah = w.id_wilayah
    LEFT JOIN access_points a ON p.id_ap = a.id_ap
    LEFT JOIN tagihan_samiran t ON p.id_pelanggan = t.id_pelanggan AND t.periode_bulan = '$periode_aktif'
    $where
    ORDER BY p.id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Billing Samiran / UKOOMED</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary px-4">
    <span class="navbar-brand fw-bold">Billing Samiran / UKOOMED</span>
    <div class="navbar-nav me-auto">
        <a class="nav-link active fw-semibold" href="dashboard_samiran.php">Data Pelanggan & Jaringan</a>
        <a class="nav-link" href="kas_bagihasil.php?periode=<?= $periode_aktif ?>">Rekap Kas & Bagi Hasil</a>
    </div>
    <div class="d-flex align-items-center text-white gap-3 small">
        <span>Wilayah: <strong>Samiran</strong> (<?= $_SESSION['username'] ?>)</span>
        <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
    </div>
</nav>

<div class="container-fluid p-4">
    <!-- Ringkasan Dashboard -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm p-3">
                <small class="text-muted">Pelanggan End-User</small>
                <h4 class="mb-0"><?= $stat['total_pel'] ?? 0 ?></h4>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm p-3">
                <small class="text-muted">Status OFF Bulanan</small>
                <h4 class="mb-0 text-warning"><?= $stat['total_off'] ?? 0 ?></h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3">
                <small class="text-muted">Lunas (<?= $periode_aktif ?>)</small>
                <h5 class="mb-0 text-success"><?= rupiah($stat['total_lunas'] ?? 0) ?></h5>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm p-3">
                <small class="text-muted">Belum Lunas</small>
                <h5 class="mb-0 text-danger"><?= rupiah($stat['total_belum'] ?? 0) ?></h5>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3">
                <small class="text-muted">Akumulasi Tunggakan 2025</small>
                <h5 class="mb-0 text-dark"><?= rupiah($stat['total_tunggakan_2025'] ?? 0) ?></h5>
            </div>
        </div>
    </div>

    <!-- Filter Wilayah, AP Induk, Jenis Link & Pencarian -->
    <div class="card border-0 shadow-sm mb-3 p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">Periode Bulan</label>
                <input type="month" name="periode" value="<?= $periode_aktif ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Dusun Samiran</label>
                <select name="dusun" class="form-select form-select-sm">
                    <option value="">Semua Dusun</option>
                    <?php
                    $qw = mysqli_query($conn, "SELECT * FROM master_wilayah_samiran ORDER BY nama_dusun ASC");
                    while ($w = mysqli_fetch_assoc($qw)) {
                        $sel = ($filter_dusun == $w['id_wilayah']) ? 'selected' : '';
                        echo "<option value='{$w['id_wilayah']}' $sel>{$w['nama_dusun']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">AP Induk (Pemancar)</label>
                <select name="ap" class="form-select form-select-sm">
                    <option value="">Semua AP</option>
                    <?php
                    $qa = mysqli_query($conn, "SELECT * FROM access_points ORDER BY identity_ap ASC");
                    while ($a = mysqli_fetch_assoc($qa)) {
                        $sel = ($filter_ap == $a['id_ap']) ? 'selected' : '';
                        echo "<option value='{$a['id_ap']}' $sel>{$a['identity_ap']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Jenis Link</label>
                <select name="link" class="form-select form-select-sm">
                    <option value="">Semua Link</option>
                    <option value="End-User" <?= $filter_link=='End-User'?'selected':'' ?>>End-User</option>
                    <option value="PTP-Backhaul" <?= $filter_link=='PTP-Backhaul'?'selected':'' ?>>PTP-Backhaul</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Cari Nama / IP / MAC</label>
                <input type="text" name="cari" value="<?= htmlspecialchars($cari) ?>" class="form-control form-control-sm" placeholder="Ketik kata kunci...">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                <button type="button" class="btn btn-sm btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#modalTambah">+ Tambah</button>
            </div>
        </form>
    </div>

    <!-- Tabel Utama Pelanggan & Jaringan -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>ID & Nama Pelanggan</th>
                        <th>Dusun</th>
                        <th>IP & MAC Perangkat</th>
                        <th>AP Induk & Link</th>
                        <th>Status</th>
                        <th>Rincian Tagihan</th>
                        <th>Status Bayar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($r = mysqli_fetch_assoc($query_data)): 
                    $pesan_wa = urlencode("Halo *{$r['nama_lengkap']}* ({$r['nama_dusun']}), rincian tagihan internet UKOOMED periode $periode_aktif:\n- Bulanan: " . rupiah($r['tagihan_bulanan']) . "\n- Tunggakan 2025: " . rupiah($r['tunggakan_2025']) . "\n- Instalasi/Router: " . rupiah($r['biaya_instalasi'] + $r['sewa_router']) . "\n- Diskon: -" . rupiah($r['diskon']) . "\n*Total: " . rupiah($r['total_bayar']) . "*\nStatus: *{$r['status_bayar']}*. Terima kasih.");
                    $wa_num = preg_replace('/^0/', '62', $r['no_wa']);
                ?>
                    <tr>
                        <td>
                            <strong><?= $r['nama_lengkap'] ?></strong><br>
                            <span class="text-muted"><?= $r['id_pelanggan'] ?> | BST: <?= $r['tgl_pasang'] ?: '-' ?></span>
                        </td>
                        <td><?= $r['nama_dusun'] ?: '-' ?></td>
                        <td>
                            <code><?= $r['ip_perangkat'] ?: '-' ?></code><br>
                            <span class="text-muted"><?= $r['mac_perangkat'] ?: '-' ?></span>
                        </td>
                        <td>
                            <strong><?= $r['identity_ap'] ?: '-' ?></strong> <small class="text-muted">(<?= $r['ip_ap'] ?: '-' ?>)</small><br>
                            <span class="badge <?= $r['kategori_link']=='End-User' ? 'bg-info text-dark' : 'bg-secondary' ?>"><?= $r['kategori_link'] ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $r['status_pelanggan']=='Aktif' ? 'bg-success' : ($r['status_pelanggan']=='OFF Bulanan' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                <?= $r['status_pelanggan'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($r['kategori_link'] === 'End-User'): ?>
                                <strong><?= rupiah($r['total_bayar']) ?></strong><br>
                                <span class="text-muted">Bln: <?= rupiah($r['tagihan_bulanan']) ?> | Tgk25: <?= rupiah($r['tunggakan_2025']) ?></span>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Link Infrastruktur (Non-Tagih)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['kategori_link'] === 'End-User'): ?>
                                <span class="badge <?= $r['status_bayar']=='Lunas' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $r['status_bayar'] ?? 'Belum Lunas' ?>
                                </span><br>
                                <small class="text-muted"><?= $r['tgl_bayar'] ? 'Tgl: '.$r['tgl_bayar'] : '' ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($r['kategori_link'] === 'End-User'): ?>
                                    <a href="https://wa.me/<?= $wa_num ?>?text=<?= $pesan_wa ?>" target="_blank" class="btn btn-success">WA</a>
                                    <a href="nota.php?id=<?= $r['id_tagihan'] ?>&ukuran=58" target="_blank" class="btn btn-secondary">Nota</a>
                                <?php endif; ?>
                                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $r['id'] ?>">Edit</button>
                                <a href="?hapus=<?= $r['id_pelanggan'] ?>&periode=<?= $periode_aktif ?>" onclick="return confirm('Hapus 1 data <?= $r['nama_lengkap'] ?>?')" class="btn btn-danger">Hapus</a>
                            </div>
                        </td>
                    </tr>

                    <!-- Modal Edit per Baris -->
                    <div class="modal fade" id="modalEdit<?= $r['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <form method="POST" class="modal-content">
                                <div class="modal-header">
                                    <h6 class="modal-title">Edit Data & Tagihan: <?= $r['nama_lengkap'] ?></h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body row g-2 small">
                                    <input type="hidden" name="id_pelanggan" value="<?= $r['id_pelanggan'] ?>">
                                    <input type="hidden" name="id_tagihan" value="<?= $r['id_tagihan'] ?? 0 ?>">
                                    <div class="col-md-5">
                                        <label>Nama Lengkap</label>
                                        <input type="text" name="nama_lengkap" value="<?= $r['nama_lengkap'] ?>" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Dusun Samiran</label>
                                        <select name="id_wilayah" class="form-select form-select-sm">
                                            <?php
                                            mysqli_data_seek($qw, 0);
                                            while ($w = mysqli_fetch_assoc($qw)) {
                                                $s = ($r['id_wilayah'] == $w['id_wilayah']) ? 'selected' : '';
                                                echo "<option value='{$w['id_wilayah']}' $s>{$w['nama_dusun']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label>No. WhatsApp</label>
                                        <input type="text" name="no_wa" value="<?= $r['no_wa'] ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4">
                                        <label>IP Perangkat (CPE)</label>
                                        <input type="text" name="ip_perangkat" value="<?= $r['ip_perangkat'] ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4">
                                        <label>MAC Perangkat</label>
                                        <input type="text" name="mac_perangkat" value="<?= $r['mac_perangkat'] ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4">
                                        <label>AP Induk</label>
                                        <select name="id_ap" class="form-select form-select-sm">
                                            <?php
                                            mysqli_data_seek($qa, 0);
                                            while ($a = mysqli_fetch_assoc($qa)) {
                                                $s = ($r['id_ap'] == $a['id_ap']) ? 'selected' : '';
                                                echo "<option value='{$a['id_ap']}' $s>{$a['identity_ap']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Paket</label>
                                        <input type="text" name="paket" value="<?= $r['paket'] ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4">
                                        <label>Status Pelanggan</label>
                                        <select name="status_pelanggan" class="form-select form-select-sm">
                                            <option value="Aktif" <?= $r['status_pelanggan']=='Aktif'?'selected':'' ?>>Aktif</option>
                                            <option value="OFF Bulanan" <?= $r['status_pelanggan']=='OFF Bulanan'?'selected':'' ?>>OFF Bulanan (Tagihan Rp 0)</option>
                                            <option value="Tidak Aktif" <?= $r['status_pelanggan']=='Tidak Aktif'?'selected':'' ?>>Tidak Aktif</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Riwayat Upgrade/Downgrade</label>
                                        <input type="text" name="riwayat_paket" value="<?= $r['riwayat_paket'] ?>" class="form-control form-control-sm">
                                    </div>
                                    <hr class="my-2">
                                    <div class="col-md-3">
                                        <label>Tagihan Bulanan</label>
                                        <input type="number" name="tagihan_bulanan" value="<?= $r['tagihan_bulanan'] ?? $r['tarif_bulanan'] ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3">
                                        <label>Tunggakan 2025</label>
                                        <input type="number" name="tunggakan_2025" value="<?= $r['tunggakan_2025'] ?? 0 ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <label>Biaya Instalasi</label>
                                        <input type="number" name="biaya_instalasi" value="<?= $r['biaya_instalasi'] ?? 0 ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <label>Sewa Router</label>
                                        <input type="number" name="sewa_router" value="<?= $r['sewa_router'] ?? 0 ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <label>Diskon</label>
                                        <input type="number" name="diskon" value="<?= $r['diskon'] ?? 0 ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4">
                                        <label>Status Bayar</label>
                                        <select name="status_bayar" class="form-select form-select-sm">
                                            <option value="Belum Lunas" <?= ($r['status_bayar']??'')=='Belum Lunas'?'selected':'' ?>>Belum Lunas</option>
                                            <option value="Lunas" <?= ($r['status_bayar']??'')=='Lunas'?'selected':'' ?>>Lunas</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Tanggal Bayar</label>
                                        <input type="date" name="tgl_bayar" value="<?= $r['tgl_bayar'] ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4">
                                        <label>Catatan Rapel / Bayar</label>
                                        <input type="text" name="keterangan_rapel" value="<?= $r['keterangan_rapel'] ?>" class="form-control form-control-sm" placeholder="Misal: Rapel 2 bulan">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" name="update_pelanggan" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Pelanggan Baru -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Tambah Pelanggan & Perangkat Baru (Samiran)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-2 small">
                <div class="col-md-4">
                    <label>ID Pelanggan *</label>
                    <input type="text" name="id_pelanggan" class="form-control form-control-sm" placeholder="SMR-004" required>
                </div>
                <div class="col-md-8">
                    <label>Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-4">
                    <label>Dusun Samiran</label>
                    <select name="id_wilayah" class="form-select form-select-sm">
                        <?php
                        mysqli_data_seek($qw, 0);
                        while ($w = mysqli_fetch_assoc($qw)) {
                            echo "<option value='{$w['id_wilayah']}'>{$w['nama_dusun']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>No. WhatsApp</label>
                    <input type="text" name="no_wa" class="form-control form-control-sm" placeholder="0812xxxx">
                </div>
                <div class="col-md-4">
                    <label>Tanggal Pasang (BST)</label>
                    <input type="date" name="tgl_pasang" value="<?= date('Y-m-d') ?>" class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label>IP Perangkat (CPE)</label>
                    <input type="text" name="ip_perangkat" class="form-control form-control-sm" placeholder="192.168.200.xxx">
                </div>
                <div class="col-md-4">
                    <label>MAC Address</label>
                    <input type="text" name="mac_perangkat" class="form-control form-control-sm" placeholder="DC:2C:6E:xx:xx:xx">
                </div>
                <div class="col-md-4">
                    <label>AP Induk (Parent AP)</label>
                    <select name="id_ap" class="form-select form-select-sm">
                        <?php
                        mysqli_data_seek($qa, 0);
                        while ($a = mysqli_fetch_assoc($qa)) {
                            echo "<option value='{$a['id_ap']}'>{$a['identity_ap']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>Jenis Link</label>
                    <select name="kategori_link" class="form-select form-select-sm">
                        <option value="End-User">End-User (Pelanggan)</option>
                        <option value="PTP-Backhaul">PTP-Backhaul (Infrastruktur)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>Paket Internet</label>
                    <input type="text" name="paket" class="form-control form-control-sm" placeholder="10 Mbps">
                </div>
                <div class="col-md-4">
                    <label>Tagihan Bulanan (Rp)</label>
                    <input type="number" name="tagihan_awal" value="100000" class="form-control form-control-sm">
                </div>
                <div class="col-md-6">
                    <label>Biaya Instalasi (Rp)</label>
                    <input type="number" name="biaya_instalasi" value="0" class="form-control form-control-sm">
                </div>
                <div class="col-md-6">
                    <label>Sewa Router (Rp)</label>
                    <input type="number" name="sewa_router" value="0" class="form-control form-control-sm">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="simpan_pelanggan" class="btn btn-primary btn-sm">Simpan Data</button>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>