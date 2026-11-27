<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db_salam.php';
require_once __DIR__ . '/helpers_salam.php';
require_once __DIR__ . '/tagihan_periode_helper.php';
header('Content-Type: application/json; charset=utf-8');
salamRequireLogin();

$periode = salamPeriodeYm($_GET['periode'] ?? date('Y-m'));
$wilayahFilter = trim((string) ($_GET['wilayah'] ?? 'all'));

$conditions = [salamScopeCondition($koneksi, 'alamat')];
if (salamCanAccessAllWilayah() && $wilayahFilter !== '' && strtolower($wilayahFilter) !== 'all') {
    $conditions[] = salamWilayahFilterCondition($koneksi, $wilayahFilter, 'alamat');
}
$scope = implode(' AND ', $conditions);

$total = (int) ($koneksi->query("SELECT COUNT(*) total FROM pelanggan_salam WHERE {$scope}")?->fetch_assoc()['total'] ?? 0);

$periodeEsc = $koneksi->real_escape_string($periode);
$isYear2025 = (substr($periode, 0, 4) === '2025');

$sourceSql = "
    SELECT p.id AS pelanggan_id, p.status_bayar, p.alamat
    FROM pelanggan_salam p
    WHERE " . ($isYear2025 ? "1=0" : "DATE_FORMAT(p.waktu,'%Y-%m')='{$periodeEsc}'") . "

    UNION ALL

    SELECT t.pelanggan_id, t.status_bayar, t.alamat_snapshot AS alamat
    FROM tagihan_salam t
    WHERE " . ($isYear2025 ? "YEAR(t.periode)=2025" : "DATE_FORMAT(t.periode,'%Y-%m')='{$periodeEsc}' AND NOT EXISTS (
          SELECT 1 FROM pelanggan_salam p2
          WHERE p2.id=t.pelanggan_id
            AND DATE_FORMAT(p2.waktu,'%Y-%m')=DATE_FORMAT(t.periode,'%Y-%m')
      )") . "
";

$periodScopeConditions = [salamScopeCondition($koneksi, 'q.alamat')];
if (salamCanAccessAllWilayah() && $wilayahFilter !== '' && strtolower($wilayahFilter) !== 'all') {
    $periodScopeConditions[] = salamWilayahFilterCondition($koneksi, $wilayahFilter, 'q.alamat');
}
$periodScope = implode(' AND ', $periodScopeConditions);

$lunas = (int) ($koneksi->query("SELECT COUNT(*) total FROM ({$sourceSql}) q WHERE {$periodScope} AND q.status_bayar='Lunas'")?->fetch_assoc()['total'] ?? 0);
$belum = (int) ($koneksi->query("SELECT COUNT(*) total FROM ({$sourceSql}) q WHERE {$periodScope} AND q.status_bayar='Belum Lunas'")?->fetch_assoc()['total'] ?? 0);

require_once __DIR__ . '/diskon_helper.php';
$diskonCount = 0;
if (salamDiskonTableReady($koneksi)) {
    $diskonCount = (int) ($koneksi->query("
        SELECT COUNT(DISTINCT dps.pelanggan_id) total
        FROM diskon_pelanggan_salam dps
        INNER JOIN ({$sourceSql}) q ON q.pelanggan_id = dps.pelanggan_id
        WHERE {$periodScope} AND dps.periode = '{$periodeEsc}'
    ")?->fetch_assoc()['total'] ?? 0);
}

$koneksi->close();
echo json_encode([
    'total_pelanggan' => $total,
    'lunas_bulan_ini' => $lunas,
    'belum_lunas_bulan_ini' => $belum,
    'total_diskon' => $diskonCount,
    'periode' => $periode,
]);
