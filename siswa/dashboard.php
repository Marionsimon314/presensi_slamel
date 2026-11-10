<?php
require_once '../config/database.php';
check_access(['siswa']);

$user_id = $_SESSION['user_id'];
$siswa = $conn->query("SELECT s.id, s.nis, k.nama_kelas FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE s.user_id = $user_id")->fetch_assoc();
$siswa_id = $siswa['id'];

// Hitung rekap dengan Aturan 3x Terlambat = 1x Alpa
$h = $conn->query("SELECT COUNT(*) as t FROM presensi WHERE siswa_id=$siswa_id AND status='Hadir' AND is_draft=0")->fetch_assoc()['t'];
$t = $conn->query("SELECT COUNT(*) as t FROM presensi WHERE siswa_id=$siswa_id AND status='Terlambat' AND is_draft=0")->fetch_assoc()['t'];
$i = $conn->query("SELECT COUNT(*) as t FROM presensi WHERE siswa_id=$siswa_id AND status='Izin' AND is_draft=0")->fetch_assoc()['t'];
$s = $conn->query("SELECT COUNT(*) as t FROM presensi WHERE siswa_id=$siswa_id AND status='Sakit' AND is_draft=0")->fetch_assoc()['t'];
$a_murni = $conn->query("SELECT COUNT(*) as t FROM presensi WHERE siswa_id=$siswa_id AND status='Alpa' AND is_draft=0")->fetch_assoc()['t'];

$konversi_alpa = floor($t / 3);
$total_alpa = $a_murni + $konversi_alpa;

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar">
        <h2>Dashboard Siswa</h2>
        <span><?= htmlspecialchars($_SESSION['nama_lengkap']); ?> (NIS: <?= $siswa['nis']; ?> | Kelas: <?= $siswa['nama_kelas']; ?>)</span>
    </div>

    <div class="cards-grid">
        <div class="card"><div class="title">Hadir</div><div class="value"><?= $h; ?></div></div>
        <div class="card"><div class="title">Terlambat</div><div class="value"><?= $t; ?></div></div>
        <div class="card"><div class="title">Izin</div><div class="value"><?= $i; ?></div></div>
        <div class="card"><div class="title">Sakit</div><div class="value"><?= $s; ?></div></div>
        <div class="card"><div class="title">Total Alpa (Inc. Konversi)</div><div class="value" style="color:red;"><?= $total_alpa; ?></div></div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>