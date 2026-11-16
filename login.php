<?php
session_start();
require_once 'config/database.php';

$error = '';

if (isset($_POST['login'])) {
    $role     = trim($_POST['role']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password) && !empty($role)) {
        $stmt = $conn->prepare("SELECT id, username, password, nama_lengkap, role, status FROM users WHERE username = ? AND role = ?");
        $stmt->bind_param("ss", $username, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($user['status'] !== 'aktif') {
                $error = "Akun Anda dinonaktifkan. Silakan hubungi Administrator.";
            } 
            elseif (password_verify($password, $user['password']) || $password === $user['password']) {
                session_regenerate_id(true);
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['role']         = $user['role'];

                switch ($user['role']) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'guru':
                        header("Location: guru/dashboard.php");
                        break;
                    case 'siswa':
                        header("Location: siswa/dashboard.php");
                        break;
                    case 'orang_tua':
                        header("Location: orangtua/dashboard.php");
                        break;
                    default:
                        header("Location: index.php");
                        break;
                }
                exit();
            } else {
                $error = "Password yang Anda masukkan salah!";
            }
        } else {
            $error = "Akun tidak terdaftar atau role tidak sesuai!";
        }
        $stmt->close();
    } else {
        $error = "Silakan isi semua bidang login.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Presensi Advent Mebali</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #1b365d 0%, #2c5282 100%);
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .login-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 10px;
            background: #ffffff;
            padding: 4px;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .login-header h2 {
            color: #1b365d;
            font-size: 20px;
            margin-bottom: 4px;
        }
        .login-header span {
            font-size: 0.85rem;
            color: #64748b;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #334155;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: #3182ce;
        }

        /* Container password + ikon mata elegan */
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
            color: #475569;
            transition: color 0.2s;
        }
        .toggle-password:hover {
            color: #1b365d;
        }
        .toggle-password svg {
            width: 20px;
            height: 20px;
        }

        .btn-primary {
            width: 100%;
            padding: 10px;
            background: #3182ce;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 5px;
        }
        .btn-primary:hover {
            background: #2b6cb0;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            text-align: center;
            border: 1px solid #fca5a5;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <img src="assets/img/logo.jpg" alt="Logo SLA Mebali" class="login-logo">
        <h2>SISTEM PRESENSI</h2>
        <span>Sekolah Lanjutan Advent Mebali</span>
    </div>

    <?php if ($error): ?>
        <div class="alert-danger">
            <?= htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="role">Masuk Sebagai</label>
            <select name="role" id="role" class="form-control" required>
                <option value="admin">Administrator (TU)</option>
                <option value="guru">Guru / Wali Kelas</option>
                <option value="siswa">Siswa</option>
                <option value="orang_tua">Orang Tua Siswa</option>
            </select>
        </div>
        <div class="form-group">
            <label for="username">ID Login (Username / NIP / NIS)</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan ID Login" required autocomplete="off">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan Password" required>
                <span class="toggle-password" id="togglePassword" title="Lihat Password">
                    <!-- SVG Mata Terbuka -->
                    <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </span>
            </div>
        </div>
        <button type="submit" name="login" class="btn-primary">Masuk ke Sistem</button>
    </form>
</div>

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    const eyeIcon = document.querySelector('#eyeIcon');

    // SVG Path untuk Mata Terbuka & Tertutup
    const eyeOpen = `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />`;
    
    const eyeClosed = `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />`;

    togglePassword.addEventListener('click', function () {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        eyeIcon.innerHTML = type === 'password' ? eyeOpen : eyeClosed;
    });
</script>

</body>
</html>