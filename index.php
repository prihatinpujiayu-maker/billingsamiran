<?php
session_start();

// Jika belum login sebagai admin Samiran, paksa buka halaman login
if (!isset($_SESSION['wilayah']) || $_SESSION['wilayah'] !== 'Samiran') {
    header("Location: login.php");
    exit;
}

// Jika sudah login, arahkan ke dashboard
header("Location: dashboard_samiran.php");
exit;
?>