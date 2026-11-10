<?php
require_once __DIR__ . '/config/database.php';

// Memastikan pengguna sudah login
check_access(['admin', 'guru', 'siswa', 'orang_tua']);

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];
$msg     = '';
$err     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $pass_lama  = trim($_POST['pass_lama']);
    $pass_baru  = trim($_POST['pass_baru']);
    $konfirmasi = trim($_POST['konfirmasi_pass']);

    if (empty($pass_lama) || empty($pass_baru) || empty($konfirmasi)) {
        $err = "Semua kolom password wajib diisi!";
    } elseif ($pass_baru !== $konfirmasi) {
        $err = "Konfirmasi password baru tidak cocok!";
    } elseif (strlen($pass_baru) < 6) {
        $err = "Password baru minimal harus 6 karakter!";
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($pass_lama, $user['password'])) {
            $hashed_new = password_hash($pass_baru, PASSWORD_DEFAULT);
            $u_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $u_stmt->bind_param("si", $hashed_new, $user_id);

            if ($u_stmt->execute()) {
                $msg = "Password Anda berhasil diperbarui!";
            } else {
                $err = "Gagal memperbarui password database.";
            }
            $u_stmt->close();
        } else {
            $err = "Password lama yang Anda masukkan salah!";
        }
    }
}

$redirect_dashboard = match($role) {
    'admin'     => 'admin/dashboard.php',
    'guru'      => 'guru/dashboard.php',
    'siswa'     => 'siswa/dashboard.php',
    'orang_tua' => 'orangtua/dashboard.php',
    default     => 'index.php'
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - Presensi SLA Mebali</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .layout-container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 25px; background-color: #f8fafc; }
        .topbar { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-wrapper input {
            padding-right: 42px !important;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            transition: color 0.2s;
        }
        .toggle-password:hover {
            color: #1b365d;
        }
        .toggle-password svg {
            width: 20px;
            height: 20px;
        }
    </style>
</head>
<body>
<div class="layout-container">
    <aside class="sidebar">
        <div class="brand" style="display: flex; align-items: center; gap: 12px; padding: 15px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
            <img src="assets/logo.jpg" alt="Logo SLA Mebali" style="width: 38px; height: 38px; object-fit: contain; background: white; padding: 2px; border-radius: 50%;">
            <span style="font-weight: bold; font-size: 1.1rem; color: #ffffff; letter-spacing: 0.5px;">SLA MEBALI</span>
        </div>
        <nav>
            <a href="<?= $redirect_dashboard; ?>">Dashboard</a>
            <a href="ganti_password.php" class="active">Ganti Password</a>
            <a href="logout.php">Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h2 style="margin: 0; color: #1b365d;">Ganti Password</h2>
            <span style="color: #64748b; font-size: 14px;">Pengguna: <b><?= htmlspecialchars($_SESSION['nama_lengkap']); ?></b> (<?= ucfirst(str_replace('_', ' ', $role)); ?>)</span>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success" style="max-width: 480px; margin: 0 auto 20px auto; background-color: #d1e7dd; color: #0f5132; padding: 12px 20px; border-radius: 6px;">
                <?= htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($err): ?>
            <div class="alert alert-danger" style="max-width: 480px; margin: 0 auto 20px auto; background-color: #f8d7da; color: #842029; padding: 12px 20px; border-radius: 6px;">
                <?= htmlspecialchars($err); ?>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width: 480px; margin: 40px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
            <h3 style="margin-top: 0; color: #1b365d; margin-bottom: 20px; text-align: center;">Form Ubah Password</h3>
            
            <form method="POST">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Password Saat Ini / Lama</label>
                    <div class="password-wrapper">
                        <input type="password" id="pass_lama" name="pass_lama" class="form-control" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        <span class="toggle-password" onclick="toggleVisibility('pass_lama', this)">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Password Baru</label>
                    <div class="password-wrapper">
                        <input type="password" id="pass_baru" name="pass_baru" class="form-control" required placeholder="Minimal 6 karakter" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        <span class="toggle-password" onclick="toggleVisibility('pass_baru', this)">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Ulangi Password Baru</label>
                    <div class="password-wrapper">
                        <input type="password" id="konfirmasi_pass" name="konfirmasi_pass" class="form-control" required placeholder="Ketik ulang password baru..." style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        <span class="toggle-password" onclick="toggleVisibility('konfirmasi_pass', this)">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <a href="<?= $redirect_dashboard; ?>" class="btn" style="background-color: #64748b; color: white; text-decoration: none; padding: 10px 18px; border-radius: 5px; font-weight: 600;">Kembali</a>
                    <button type="submit" name="update_password" class="btn btn-primary" style="background-color: #1b365d; color: white; border: none; padding: 10px 20px; border-radius: 5px; font-weight: 600; cursor: pointer;">Simpan Password Baru</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
    const eyeOpen = `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />`;
    const eyeClosed = `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />`;

    function toggleVisibility(inputId, btnElem) {
        const inputField = document.getElementById(inputId);
        const svgElem = btnElem.querySelector('svg');
        if (inputField.type === "password") {
            inputField.type = "text";
            svgElem.innerHTML = eyeClosed;
        } else {
            inputField.type = "password";
            svgElem.innerHTML = eyeOpen;
        }
    }
</script>

</body>
</html>