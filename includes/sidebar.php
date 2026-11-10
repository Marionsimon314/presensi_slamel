<?php
$role = $_SESSION['role'] ?? '';

// Penyesuaian path agar link selalu tepat dari folder mana pun (admin, guru, siswa, orangtua)
$base_url = (in_array($role, ['admin', 'guru', 'siswa', 'orang_tua'])) ? '../' : '';

// Mengambil nama file halaman yang sedang diakses
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="brand" style="display: flex; align-items: center; gap: 12px; padding: 15px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
        <img src="<?= $base_url; ?>assets/logo.jpg" alt="Logo SLA Mebali" style="width: 38px; height: 38px; object-fit: contain; background: white; padding: 2px; border-radius: 50%;">
        <span style="font-weight: bold; font-size: 1.1rem; color: #ffffff; letter-spacing: 0.5px;">SLA MEBALI</span>
    </div>
    <nav>
        <?php if ($role === 'admin'): ?>
            <a href="dashboard.php" class="<?= ($current_page == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
            <a href="siswa.php" class="<?= ($current_page == 'siswa.php') ? 'active' : ''; ?>">Data Siswa</a>
            <a href="guru.php" class="<?= ($current_page == 'guru.php') ? 'active' : ''; ?>">Data Guru</a>
            <a href="kelas.php" class="<?= ($current_page == 'kelas.php') ? 'active' : ''; ?>">Data Kelas</a>
            <a href="matapelajaran.php" class="<?= ($current_page == 'matapelajaran.php') ? 'active' : ''; ?>">Mata Pelajaran</a>
            <a href="pengguna.php" class="<?= ($current_page == 'pengguna.php') ? 'active' : ''; ?>">Akun Pengguna</a>
            <a href="presensi.php" class="<?= ($current_page == 'presensi.php') ? 'active' : ''; ?>">Kelola Presensi</a>
            <a href="laporan.php" class="<?= ($current_page == 'laporan.php') ? 'active' : ''; ?>">Laporan Presensi</a>
            <a href="audit_trail.php" class="<?= ($current_page == 'audit_trail.php') ? 'active' : ''; ?>">Audit Trail</a>
        <?php elseif ($role === 'guru'): ?>
            <a href="dashboard.php" class="<?= ($current_page == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
            <a href="input_presensi.php" class="<?= ($current_page == 'input_presensi.php') ? 'active' : ''; ?>">Catat Presensi</a>
            <a href="kelola_presensi.php" class="<?= ($current_page == 'kelola_presensi.php') ? 'active' : ''; ?>">Kelola Presensi</a>
            <a href="laporan.php" class="<?= ($current_page == 'laporan.php') ? 'active' : ''; ?>">Laporan Kelas</a>
            <a href="audit_trail.php" class="<?= ($current_page == 'audit_trail.php') ? 'active' : ''; ?>">Riwayat Perubahan</a>
        <?php elseif ($role === 'siswa'): ?>
            <a href="dashboard.php" class="<?= ($current_page == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
            <a href="riwayat.php" class="<?= ($current_page == 'riwayat.php') ? 'active' : ''; ?>">Riwayat Presensi</a>
        <?php elseif ($role === 'orang_tua'): ?>
            <a href="dashboard.php" class="<?= ($current_page == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
            <a href="riwayat.php" class="<?= ($current_page == 'riwayat.php') ? 'active' : ''; ?>">Presensi Anak</a>
        <?php endif; ?>

        <!-- Menu Ganti Password untuk Semua Role -->
        <a href="<?= $base_url; ?>ganti_password.php" class="<?= ($current_page == 'ganti_password.php') ? 'active' : ''; ?>">Ganti Password</a>
        
        <!-- Logout -->
        <a href="<?= $base_url; ?>logout.php">Logout</a>
    </nav>
</aside>