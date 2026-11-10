<?php
require_once '../config/database.php';
check_access(['guru']);

$user_id = $_SESSION['user_id'];
$logs = $conn->query("SELECT a.*, u.nama_lengkap 
                      FROM audit_trail a 
                      JOIN users u ON a.user_id = u.id 
                      WHERE a.user_id = $user_id 
                      ORDER BY a.waktu DESC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar"><h2>Riwayat Perubahan Data Oleh Anda</h2></div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Tindakan</th>
                    <th>Rincian Perubahan</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($l = $logs->fetch_assoc()): ?>
                <tr>
                    <td><?= $l['waktu']; ?></td>
                    <td><b><?= htmlspecialchars($l['tindakan']); ?></b></td>
                    <td><?= htmlspecialchars($l['rincian_perubahan']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include '../includes/footer.php'; ?>