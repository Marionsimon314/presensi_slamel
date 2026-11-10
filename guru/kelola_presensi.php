<?php
require_once '../config/database.php';
check_access(['guru']);

$user_id = $_SESSION['user_id'];
$guru_id = $conn->query("SELECT id FROM guru WHERE user_id = $user_id")->fetch_assoc()['id'];

$msg = '';
$err = '';

// Process Update oleh Guru (Edit Nama, Kelas, dan Status Presensi Siswa)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_siswa_presensi'])) {
        $p_id        = intval($_POST['presensi_id']);
        $nama_baru   = trim($_POST['nama_lengkap']);
        $kelas_id_baru = intval($_POST['kelas_id']);
        $status_baru = $_POST['status'];
        $keterangan  = trim($_POST['keterangan']);

        $conn->begin_transaction();
        try {
            if (!in_array($status_baru, ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa'], true)) {
                throw new Exception("Status presensi tidak valid.");
            }

            // Presensi harus milik guru yang sedang login; data siswa diambil dari database, bukan dari form
            $cek = $conn->prepare("SELECT p.status, p.siswa_id, s.user_id AS siswa_user_id FROM presensi p JOIN siswa s ON p.siswa_id = s.id WHERE p.id = ? AND p.guru_id = ?");
            $cek->bind_param("ii", $p_id, $guru_id);
            $cek->execute();
            $old = $cek->get_result()->fetch_assoc();
            if (!$old) {
                throw new Exception("Data presensi tidak ditemukan atau bukan milik Anda.");
            }
            $siswa_id      = intval($old['siswa_id']);
            $siswa_user_id = intval($old['siswa_user_id']);

            // 1. Edit Nama Siswa
            $u_stmt = $conn->prepare("UPDATE users SET nama_lengkap = ? WHERE id = ?");
            $u_stmt->bind_param("si", $nama_baru, $siswa_user_id);
            $u_stmt->execute();

            // 2. Edit Kelas Siswa
            $s_stmt = $conn->prepare("UPDATE siswa SET kelas_id = ? WHERE id = ?");
            $s_stmt->bind_param("ii", $kelas_id_baru, $siswa_id);
            $s_stmt->execute();

            // 3. Edit Absen/Presensi Siswa
            $rincian = "Perubahan Guru: Nama/Kelas diperbarui. Status Absen (" . $old['status'] . " -> $status_baru)";
            $p_stmt = $conn->prepare("UPDATE presensi SET status = ?, keterangan = ?, kelas_id = ? WHERE id = ? AND guru_id = ?");
            $p_stmt->bind_param("ssiii", $status_baru, $keterangan, $kelas_id_baru, $p_id, $guru_id);
            $p_stmt->execute();
            log_audit($conn, $user_id, $p_id, "UPDATE_GURU", $rincian);

            $conn->commit();
            $msg = "Data nama, kelas, dan presensi siswa berhasil diperbarui!";
        } catch (Exception $e) {
            $conn->rollback();
            $err = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
}

$kelas_opt = $conn->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas");

$presensi_list = $conn->query("SELECT p.id as presensi_id, p.tanggal, s.id as siswa_id, u.id as siswa_user_id, 
                               u.nama_lengkap, s.nis, s.kelas_id, k.nama_kelas, m.nama_mapel, p.status, p.keterangan 
                               FROM presensi p
                               JOIN siswa s ON p.siswa_id = s.id
                               JOIN users u ON s.user_id = u.id
                               JOIN kelas k ON p.kelas_id = k.id
                               JOIN mapel m ON p.mapel_id = m.id
                               WHERE p.guru_id = $guru_id AND p.is_draft = 0
                               ORDER BY p.tanggal DESC, u.nama_lengkap ASC LIMIT 50");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <h2>Kelola & Edit Presensi Siswa (Kewenangan Guru)</h2>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>NIS</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Mata Pelajaran</th>
                    <th>Status Absen</th>
                    <th>Keterangan</th>
                    <th>Aksi Edit</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($presensi_list && $presensi_list->num_rows > 0): ?>
                    <?php while ($p = $presensi_list->fetch_assoc()): ?>
                    <tr>
                        <td><?= $p['tanggal']; ?></td>
                        <td><?= htmlspecialchars($p['nis']); ?></td>
                        <td><b><?= htmlspecialchars($p['nama_lengkap']); ?></b></td>
                        <td><?= htmlspecialchars($p['nama_kelas']); ?></td>
                        <td><?= htmlspecialchars($p['nama_mapel']); ?></td>
                        <td><span class="badge badge-<?= strtolower($p['status']); ?>"><?= $p['status']; ?></span></td>
                        <td><?= htmlspecialchars($p['keterangan']); ?></td>
                        <td>
                            <button type="button" class="btn btn-warning btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($p)); ?>)">Edit Data & Absen</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">Belum ada data presensi final yang dicatat.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modal Edit Siswa & Absen oleh Guru -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:999;">
    <div style="background:#fff; padding:25px; border-radius:8px; width:90%; max-width:450px;">
        <h3>Edit Nama, Kelas, & Absen Siswa</h3>
        <form method="POST" style="margin-top:15px;">
            <input type="hidden" name="presensi_id" id="edit_presensi_id">
            <input type="hidden" name="siswa_user_id" id="edit_siswa_user_id">
            <input type="hidden" name="siswa_id" id="edit_siswa_id">

            <div class="form-group">
                <label>Nama Siswa</label>
                <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Kelas Siswa</label>
                <select name="kelas_id" id="edit_kelas_id" class="form-control" required>
                    <?php 
                    $kelas_opt->data_seek(0);
                    while ($k = $kelas_opt->fetch_assoc()): 
                    ?>
                        <option value="<?= $k['id']; ?>"><?= htmlspecialchars($k['nama_kelas']); ?> (<?= $k['tingkat']; ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Status Kehadiran (Absen)</label>
                <select name="status" id="edit_status" class="form-control" required>
                    <option value="Hadir">Hadir</option>
                    <option value="Terlambat">Terlambat</option>
                    <option value="Izin">Izin</option>
                    <option value="Sakit">Sakit</option>
                    <option value="Alpa">Alpa</option>
                </select>
            </div>

            <div class="form-group">
                <label>Keterangan</label>
                <input type="text" name="keterangan" id="edit_keterangan" class="form-control" placeholder="Catatan/Alasan">
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:15px;">
                <button type="button" class="btn btn-danger" onclick="closeEditModal()">Batal</button>
                <button type="submit" name="update_siswa_presensi" class="btn btn-success">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('edit_presensi_id').value = data.presensi_id;
    document.getElementById('edit_siswa_user_id').value = data.siswa_user_id;
    document.getElementById('edit_siswa_id').value = data.siswa_id;
    document.getElementById('edit_nama').value = data.nama_lengkap;
    document.getElementById('edit_kelas_id').value = data.kelas_id;
    document.getElementById('edit_status').value = data.status;
    document.getElementById('edit_keterangan').value = data.keterangan || '';
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>