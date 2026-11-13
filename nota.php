<?php
include 'koneksi.php';
$id_tagihan = (int)($_GET['id'] ?? 0);
$ukuran     = in_array($_GET['ukuran'] ?? '58', ['57', '58', '80']) ? $_GET['ukuran'] : '58';

$data = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT t.*, p.nama_lengkap, p.paket, w.nama_dusun
    FROM tagihan_samiran t
    JOIN pelanggan_samiran p ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN master_wilayah_samiran w ON p.id_wilayah = w.id_wilayah
    WHERE t.id_tagihan = $id_tagihan
"));

if (!$data) die("Data nota tidak ditemukan.");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota <?= $data['nomor_invoice'] ?></title>
    <style>
        body { font-family: monospace; font-size: 12px; margin: 0; padding: 10px; }
        .struk { width: <?= $ukuran ?>mm; margin: auto; }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; font-size: 11px; }
        td.right { text-align: right; }
        @media print { .no-print { display: none; } @page { margin: 0; } }
    </style>
</head>
<body>
<div class="no-print center" style="margin-bottom: 15px;">
    Ukuran Kertas:
    <a href="?id=<?= $id_tagihan ?>&ukuran=57">57 mm</a> |
    <a href="?id=<?= $id_tagihan ?>&ukuran=58">58 mm</a> |
    <a href="?id=<?= $id_tagihan ?>&ukuran=80">80 mm</a> |
    <button onclick="window.print()">Print / Save PDF</button>
</div>

<div class="struk">
    <div class="center">
        <strong>UKOOMED - BILLING SAMIRAN</strong><br>
        Layanan Internet Wilayah Samiran
    </div>
    <div class="line"></div>
    <table>
        <tr><td>No. Inv</td><td class="right"><?= $data['nomor_invoice'] ?></td></tr>
        <tr><td>Periode</td><td class="right"><?= $data['periode_bulan'] ?></td></tr>
        <tr><td>ID Pel</td><td class="right"><?= $data['id_pelanggan'] ?></td></tr>
        <tr><td>Nama</td><td class="right"><?= $data['nama_lengkap'] ?></td></tr>
        <tr><td>Dusun</td><td class="right"><?= $data['nama_dusun'] ?></td></tr>
        <tr><td>Paket</td><td class="right"><?= $data['paket'] ?></td></tr>
    </table>
    <div class="line"></div>
    <table>
        <tr><td>Tagihan Bulanan</td><td class="right"><?= rupiah($data['tagihan_bulanan']) ?></td></tr>
        <?php if ($data['tunggakan_2025'] > 0): ?>
        <tr><td>Tunggakan 2025</td><td class="right"><?= rupiah($data['tunggakan_2025']) ?></td></tr>
        <?php endif; ?>
        <?php if ($data['biaya_instalasi'] > 0): ?>
        <tr><td>Biaya Instalasi</td><td class="right"><?= rupiah($data['biaya_instalasi']) ?></td></tr>
        <?php endif; ?>
        <?php if ($data['sewa_router'] > 0): ?>
        <tr><td>Sewa Router</td><td class="right"><?= rupiah($data['sewa_router']) ?></td></tr>
        <?php endif; ?>
        <?php if ($data['diskon'] > 0): ?>
        <tr><td>Diskon</td><td class="right">-<?= rupiah($data['diskon']) ?></td></tr>
        <?php endif; ?>
        <tr><td><strong>TOTAL BAYAR</strong></td><td class="right"><strong><?= rupiah($data['total_bayar']) ?></strong></td></tr>
    </table>
    <div class="line"></div>
    <table>
        <tr><td>Status</td><td class="right"><strong><?= strtoupper($data['status_bayar']) ?></strong></td></tr>
        <?php if ($data['tgl_bayar']): ?>
        <tr><td>Tgl Bayar</td><td class="right"><?= $data['tgl_bayar'] ?></td></tr>
        <?php endif; ?>
    </table>
    <div class="line"></div>
    <div class="center">Terima kasih atas kepercayaan Anda.</div>
</div>
</body>
</html>