<?php
require_once '../config/database.php';
check_access(['admin']);

$where = [];
if (!empty($_GET['tgl_mulai']) && !empty($_GET['tgl_selesai'])) {
    $m = $conn->real_escape_string($_GET['tgl_mulai']);
    $s = $conn->real_escape_string($_GET['tgl_selesai']);
    $where[] = "DATE(a.waktu) BETWEEN '$m' AND '$s'";
}

$sql = "SELECT a.*, u.nama_lengkap FROM audit_trail a JOIN users u ON a.user_id = u.id";
if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY a.waktu DESC";
$result = $conn->query($sql);

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar">
        <h2>Riwayat Perubahan Data (Audit Trail)</h2>
    </div>

    <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px;">
        <input type="date" name="tgl_mulai" class="form-control" value="<?= $_GET['tgl_mulai'] ?? ''; ?>">
        <input type="date" name="tgl_selesai" class="form-control" value="<?= $_GET['tgl_selesai'] ?? ''; ?>">
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Tindakan</th>
                    <th>Rincian Perubahan</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['waktu']; ?></td>
                    <td><?= htmlspecialchars($row['nama_lengkap']); ?></td>
                    <td><b><?= htmlspecialchars($row['tindakan']); ?></b></td>
                    <td><?= htmlspecialchars($row['rincian_perubahan']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include '../includes/footer.php'; ?>