<?php
require_once '../config/database.php';
check_access(['siswa']);

$user_id = $_SESSION['user_id'];
$siswa = $conn->query("SELECT id FROM siswa WHERE user_id = $user_id")->fetch_assoc();
$siswa_id = $siswa['id'];

$history = $conn->query("SELECT p.tanggal, m.nama_mapel, p.status, p.keterangan 
                         FROM presensi p
                         JOIN mapel m ON p.mapel_id = m.id
                         WHERE p.siswa_id = $siswa_id AND p.is_draft = 0
                         ORDER BY p.tanggal DESC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar"><h2>Riwayat Presensi Pribadi</h2></div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Mata Pelajaran</th>
                    <th>Status</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($h = $history->fetch_assoc()): ?>
                <tr>
                    <td><?= $h['tanggal']; ?></td>
                    <td><?= htmlspecialchars($h['nama_mapel']); ?></td>
                    <td><span class="badge badge-<?= strtolower($h['status']); ?>"><?= $h['status']; ?></span></td>
                    <td><?= htmlspecialchars($h['keterangan']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include '../includes/footer.php'; ?>