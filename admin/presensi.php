<?php
require_once '../config/database.php';
check_access(['admin']);

$msg = '';

// Koreksi Presensi Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['koreksi_presensi'])) {
    $p_id = intval($_POST['presensi_id']);
    $status_baru = $_POST['status'];
    $keterangan  = trim($_POST['keterangan']);

    // Ambil data lama untuk Audit Trail
    $old = $conn->query("SELECT status, keterangan FROM presensi WHERE id = $p_id")->fetch_assoc();
    $rincian = "Koreksi Admin: Status (" . $old['status'] . " -> $status_baru), Ket: (" . $old['keterangan'] . " -> $keterangan)";

    $stmt = $conn->prepare("UPDATE presensi SET status = ?, keterangan = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status_baru, $keterangan, $p_id);
    if ($stmt->execute()) {
        log_audit($conn, $_SESSION['user_id'], $p_id, "KOREKSI_ADMIN", $rincian);
        $msg = "Data presensi berhasil dikoreksi dan dicatat di Audit Trail.";
    }
}

// Filter Options
$kelas_id = $_GET['kelas_id'] ?? '';
$tanggal  = $_GET['tanggal'] ?? '';

$where = ["p.is_draft = 0"];
if (!empty($kelas_id)) $where[] = "p.kelas_id = " . intval($kelas_id);
if (!empty($tanggal))  $where[] = "p.tanggal = '" . $conn->real_escape_string($tanggal) . "'";

$where_sql = implode(" AND ", $where);

$kelas_list = $conn->query("SELECT * FROM kelas");
$presensi_list = $conn->query("SELECT p.id, p.tanggal, u.nama_lengkap, k.nama_kelas, m.nama_mapel, p.status, p.keterangan 
                               FROM presensi p
                               JOIN siswa s ON p.siswa_id = s.id
                               JOIN users u ON s.user_id = u.id
                               JOIN kelas k ON p.kelas_id = k.id
                               JOIN mapel m ON p.mapel_id = m.id
                               WHERE $where_sql
                               ORDER BY p.tanggal DESC, u.nama_lengkap ASC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar"><h2>Mengelola & Koreksi Presensi Siswa</h2></div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg); ?></div><?php endif; ?>

    <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px;">
        <select name="kelas_id" class="form-control">
            <option value="">-- Semua Kelas --</option>
            <?php while ($k = $kelas_list->fetch_assoc()): ?>
                <option value="<?= $k['id']; ?>" <?= $kelas_id == $k['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($k['nama_kelas']); ?></option>
            <?php endwhile; ?>
        </select>
        <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal); ?>">
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Mata Pelajaran</th>
                    <th>Status</th>
                    <th>Keterangan</th>
                    <th>Aksi Koreksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($p = $presensi_list->fetch_assoc()): ?>
                <tr>
                    <td><?= $p['tanggal']; ?></td>
                    <td><?= htmlspecialchars($p['nama_lengkap']); ?></td>
                    <td><?= htmlspecialchars($p['nama_kelas']); ?></td>
                    <td><?= htmlspecialchars($p['nama_mapel']); ?></td>
                    <td><span class="badge badge-<?= strtolower($p['status']); ?>"><?= $p['status']; ?></span></td>
                    <td><?= htmlspecialchars($p['keterangan']); ?></td>
                    <td>
                        <form method="POST" style="display:flex; gap:5px;">
                            <input type="hidden" name="presensi_id" value="<?= $p['id']; ?>">
                            <select name="status" class="form-control" style="padding:2px; font-size:12px;">
                                <option value="Hadir" <?= $p['status']=='Hadir'?'selected':''; ?>>Hadir</option>
                                <option value="Terlambat" <?= $p['status']=='Terlambat'?'selected':''; ?>>Terlambat</option>
                                <option value="Izin" <?= $p['status']=='Izin'?'selected':''; ?>>Izin</option>
                                <option value="Sakit" <?= $p['status']=='Sakit'?'selected':''; ?>>Sakit</option>
                                <option value="Alpa" <?= $p['status']=='Alpa'?'selected':''; ?>>Alpa</option>
                            </select>
                            <input type="text" name="keterangan" value="<?= htmlspecialchars($p['keterangan']); ?>" class="form-control" style="padding:2px; font-size:12px;" placeholder="Ket">
                            <button type="submit" name="koreksi_presensi" class="btn btn-warning btn-sm">Simpan</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include '../includes/footer.php'; ?>