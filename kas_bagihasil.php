<?php
include 'koneksi.php';
if (!isset($_SESSION['wilayah']) || $_SESSION['wilayah'] !== 'Samiran') {
    header("Location: login.php");
    exit;
}

$periode = $_GET['periode'] ?? date('Y-m');

// Hitung pemasukan otomatis dari tagihan lunas bulan ini
$q_lunas = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_bayar) AS masuk FROM tagihan_samiran WHERE periode_bulan='$periode' AND status_bayar='Lunas'"));
$pemasukan_otomatis = (int)($q_lunas['masuk'] ?? 0);

if (isset($_POST['simpan_kas'])) {
    $pemasukan   = (int)$_POST['total_pemasukan'];
    $penarik     = (int)$_POST['bagi_hasil_penarik'];
    $operasional = (int)$_POST['biaya_operasional_5persen'];
    $instalasi   = (int)$_POST['setoran_instalasi'];
    $wifi_koin   = (int)$_POST['wifi_koin'];
    $freelance   = (int)$_POST['upah_freelance'];
    $catatan     = mysqli_real_escape_string($conn, $_POST['catatan']);

    mysqli_query($conn, "REPLACE INTO rekap_kas_samiran 
        (periode_bulan, total_pemasukan, bagi_hasil_penarik, biaya_operasional_5persen, setoran_instalasi, wifi_koin, upah_freelance, catatan)
        VALUES ('$periode', $pemasukan, $penarik, $operasional, $instalasi, $wifi_koin, $freelance, '$catatan')");
    header("Location: kas_bagihasil.php?periode=$periode&sukses=1");
    exit;
}

$kas = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM rekap_kas_samiran WHERE periode_bulan='$periode'"));
$pemasukan_val   = $kas['total_pemasukan'] ?? $pemasukan_otomatis;
$operasional_val = $kas['biaya_operasional_5persen'] ?? round($pemasukan_val * 0.05);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Kas & Bagi Hasil - Samiran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary px-4">
    <span class="navbar-brand fw-bold">Billing Samiran / UKOOMED</span>
    <div class="navbar-nav me-auto">
        <a class="nav-link" href="dashboard_samiran.php?periode=<?= $periode ?>">Data Pelanggan & Jaringan</a>
        <a class="nav-link active fw-semibold" href="kas_bagihasil.php">Rekap Kas & Bagi Hasil</a>
    </div>
</nav>

<div class="container py-4" style="max-width: 750px;">
    <div class="card border-0 shadow-sm p-4">
        <h5 class="mb-3">Rekapitulasi Keuangan & Bagi Hasil (Periode <?= $periode ?>)</h5>
        <?php if (isset($_GET['sukses'])): ?>
            <div class="alert alert-success py-2 small">Data rekap kas berhasil disimpan!</div>
        <?php endif; ?>
        <form method="POST" class="row g-3 small">
            <div class="col-md-6">
                <label class="form-label">Total Pemasukan Tagihan (Rp)</label>
                <input type="number" name="total_pemasukan" value="<?= $pemasukan_val ?>" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Biaya Operasional 5% (Rp)</label>
                <input type="number" name="biaya_operasional_5persen" value="<?= $operasional_val ?>" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Bagi Hasil Penarik / Kolektor (Rp)</label>
                <input type="number" name="bagi_hasil_penarik" value="<?= $kas['bagi_hasil_penarik'] ?? 0 ?>" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Setoran Biaya Instalasi (Rp)</label>
                <input type="number" name="setoran_instalasi" value="<?= $kas['setoran_instalasi'] ?? 0 ?>" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Pemasukan WiFi Koin (Rp)</label>
                <input type="number" name="wifi_koin" value="<?= $kas['wifi_koin'] ?? 0 ?>" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Upah Teknisi Freelance (Rp)</label>
                <input type="number" name="upah_freelance" value="<?= $kas['upah_freelance'] ?? 0 ?>" class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label">Catatan Keuangan</label>
                <textarea name="catatan" class="form-control" rows="2"><?= $kas['catatan'] ?? '' ?></textarea>
            </div>
            <div class="col-12">
                <button type="submit" name="simpan_kas" class="btn btn-primary">Simpan Rekap Kas</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>