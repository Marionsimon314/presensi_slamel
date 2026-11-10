<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

switch ($_SESSION['role']) {
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
        header("Location: login.php");
        break;
}
exit();
?>