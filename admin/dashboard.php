<?php
require_once '../config/database.php';
check_access(['admin']);

$total_siswa = $conn->query("SELECT COUNT(*) AS total FROM siswa")->fetch_assoc()['total'];
$total_guru  = $conn->query("SELECT COUNT(*) AS total FROM guru")->fetch_assoc()['total'];
$total_kelas = $conn->query("SELECT COUNT(*) AS total FROM kelas")->fetch_assoc()['total'];
$total_presensi = $conn->query("SELECT COUNT(*) AS total FROM presensi WHERE is_draft = 0")->fetch_assoc()['total'];

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar">
        <h2>Dashboard Administrator</h2>
        <span>Halo, <?= htmlspecialchars($_SESSION['nama_lengkap']); ?></span>
    </div>

    <div class="cards-grid">
        <div class="card"><div class="title">Total Siswa</div><div class="value"><?= $total_siswa; ?></div></div>
        <div class="card"><div class="title">Total Guru</div><div class="value"><?= $total_guru; ?></div></div>
        <div class="card"><div class="title">Total Kelas</div><div class="value"><?= $total_kelas; ?></div></div>
        <div class="card"><div class="title">Presensi Final</div><div class="value"><?= $total_presensi; ?></div></div>
    </div>

    <h3>Presensi Terbaru</h3>
    <div class="table-responsive" style="margin-top: 15px;">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Mata Pelajaran</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $q = "SELECT p.tanggal, u.nama_lengkap, k.nama_kelas, m.nama_mapel, p.status 
                      FROM presensi p
                      JOIN siswa s ON p.siswa_id = s.id
                      JOIN users u ON s.user_id = u.id
                      JOIN kelas k ON p.kelas_id = k.id
                      JOIN mapel m ON p.mapel_id = m.id
                      WHERE p.is_draft = 0
                      ORDER BY p.tanggal DESC, p.id DESC LIMIT 5";
                $res = $conn->query($q);
                while ($row = $res->fetch_assoc()):
                ?>
                <tr>
                    <td><?= $row['tanggal']; ?></td>
                    <td><?= htmlspecialchars($row['nama_lengkap']); ?></td>
                    <td><?= htmlspecialchars($row['nama_kelas']); ?></td>
                    <td><?= htmlspecialchars($row['nama_mapel']); ?></td>
                    <td><span class="badge badge-<?= strtolower($row['status']); ?>"><?= $row['status']; ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include '../includes/footer.php'; ?>