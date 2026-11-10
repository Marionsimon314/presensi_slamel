<?php
require_once '../config/database.php';
check_access(['orang_tua']);

$user_id = $_SESSION['user_id'];

// Ambil data anak dari tabel orang_tua
$stmt = $conn->prepare("SELECT ot.siswa_id, u.nama_lengkap 
                        FROM orang_tua ot 
                        JOIN siswa s ON ot.siswa_id = s.id 
                        JOIN users u ON s.user_id = u.id 
                        WHERE ot.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ortu = $stmt->get_result()->fetch_assoc();
$stmt->close();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <h2>Riwayat Presensi Anak <?= $ortu ? ': ' . htmlspecialchars($ortu['nama_lengkap']) : ''; ?></h2>
    </div>

    <?php if (!$ortu): ?>
        <div class="alert alert-danger" style="margin-top: 20px;">
            <b>Perhatian:</b> Akun Orang Tua Anda belum dihubungkan dengan data Siswa/Anak oleh Admin. Silakan hubungi Pihak Sekolah (TU).
        </div>
    <?php else: 
        $siswa_id = $ortu['siswa_id'];
        $history = $conn->query("SELECT p.tanggal, m.nama_mapel, p.status, p.keterangan 
                                 FROM presensi p
                                 JOIN mapel m ON p.mapel_id = m.id
                                 WHERE p.siswa_id = $siswa_id AND p.is_draft = 0
                                 ORDER BY p.tanggal DESC, p.id DESC");
    ?>

    <div class="table-responsive" style="margin-top: 20px;">
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Tanggal</th>
                    <th>Mata Pelajaran</th>
                    <th>Status Kehadiran</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                if ($history && $history->num_rows > 0):
                    while ($h = $history->fetch_assoc()): 
                ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td><?= date('d-m-Y', strtotime($h['tanggal'])); ?></td>
                    <td><?= htmlspecialchars($h['nama_mapel']); ?></td>
                    <td>
                        <span class="badge badge-<?= strtolower($h['status']); ?>">
                            <?= $h['status']; ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($h['keterangan'] ? $h['keterangan'] : '-'); ?></td>
                </tr>
                <?php 
                    endwhile; 
                else:
                ?>
                <tr>
                    <td colspan="5" style="text-align: center;">Belum ada riwayat presensi yang dikunci oleh guru.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>