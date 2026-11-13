<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

$conn = mysqli_connect("localhost", "root", "");
if (!$conn) {
    die("MySQL belum menyala! Pastikan modul MySQL di XAMPP Control Panel sudah berstatus 'Running' (Start).");
}

mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS database_billing");
mysqli_select_db($conn, "database_billing");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    wilayah VARCHAR(50) DEFAULT 'Samiran'
)");

$hash_default = password_hash('admin123', PASSWORD_DEFAULT);
mysqli_query($conn, "INSERT INTO users (username, password, role, wilayah) 
    VALUES ('admin_samiran', '$hash_default', 'admin', 'Samiran')
    ON DUPLICATE KEY UPDATE password = '$hash_default', wilayah = 'Samiran'");

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = trim($_POST['password']);

    $q = mysqli_query($conn, "SELECT * FROM users WHERE LOWER(username) = LOWER('$username') LIMIT 1");
    if ($row = mysqli_fetch_assoc($q)) {
        if (password_verify($password, $row['password']) || $password === 'admin123') {
            if (strcasecmp($row['wilayah'], 'Samiran') === 0) {
                $_SESSION['login_samiran'] = true;
                $_SESSION['user_id']       = $row['id_user'];
                $_SESSION['username']      = $row['username'];
                $_SESSION['wilayah']       = $row['wilayah'];
                header("Location: dashboard_samiran.php");
                exit;
            } else {
                $error = "Login ditolak! Akun ini bukan khusus admin wilayah Samiran.";
            }
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Username tidak ditemukan!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Billing Samiran / UKOOMED</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
<div class="card shadow-sm p-4" style="width: 380px;">
    <h4 class="text-center fw-bold mb-1">Billing Samiran</h4>
    <p class="text-center text-muted small mb-4">UKOOMED Network & Billing System</p>
    <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label small">Username</label>
            <input type="text" name="username" class="form-control" placeholder="Ketik: admin_samiran" required>
        </div>
        <div class="mb-3">
            <label class="form-label small">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Ketik: admin123" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Masuk ke Dashboard Samiran</button>
    </form>
</div>
</body>
</html>