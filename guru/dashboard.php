<?php
require_once '../config/database.php';
check_access(['guru']);

$user_id = $_SESSION['user_id'];
$guru = $conn->query("SELECT id FROM guru WHERE user_id = $user_id")->fetch_assoc();
$guru_id = $guru['id'];

// Draf presensi yang belum difinalisasi
$drafts = $conn->query("SELECT p.tanggal, k.nama_kelas, m.nama_mapel, COUNT(p.id) as total_siswa 
                        FROM presensi p
                        JOIN kelas k ON p.kelas_id = k.id
                        JOIN mapel m ON p.mapel_id = m.id
                        WHERE p.guru_id = $guru_id AND p.is_draft = 1
                        GROUP BY p.tanggal, p.kelas_id, p.mapel_id");

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar">
        <h2>Dashboard Guru</h2>
        <span>Selamat datang, <?= htmlspecialchars($_SESSION['nama_lengkap']); ?></span>
    </div>

    <h3>Draf Presensi Belum Final</h3>
    <div class="table-responsive" style="margin-top: 15px;">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Kelas</th>
                    <th>Mata Pelajaran</th>
                    <th>Jumlah Siswa</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($drafts->num_rows > 0): ?>
                    <?php while ($d = $drafts->fetch_assoc()): ?>
                    <tr>
                        <td><?= $d['tanggal']; ?></td>
                        <td><?= htmlspecialchars($d['nama_kelas']); ?></td>
                        <td><?= htmlspecialchars($d['nama_mapel']); ?></td>
                        <td><?= $d['total_siswa']; ?> Siswa</td>
                        <td><a href="input_presensi.php" class="btn btn-warning btn-sm">Lanjutkan Draf</a></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">Tidak ada draf presensi menggantung.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include '../includes/footer.php'; ?>