<?php
require_once '../config/database.php';
check_access(['guru']);

$user_id = $_SESSION['user_id'];
$guru_id = $conn->query("SELECT id FROM guru WHERE user_id = $user_id")->fetch_assoc()['id'];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_presensi'])) {
    $kelas_id = intval($_POST['kelas_id']);
    $mapel_id = intval($_POST['mapel_id']);
    $tanggal  = $_POST['tanggal'];
    $is_draft = (($_POST['simpan_presensi'] ?? '') === 'draft') ? 1 : 0;
    $valid_status = ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa'];
    $tgl_ok = DateTime::createFromFormat('Y-m-d', $tanggal);

    if (!$tgl_ok || $tgl_ok->format('Y-m-d') !== $tanggal) { $_POST['status'] = []; $msg = 'Format tanggal tidak valid.'; }
    foreach (($_POST['status'] ?? []) as $siswa_id => $status) {
        $siswa_id = intval($siswa_id);
        if (!in_array($status, $valid_status, true)) { continue; }
        $keterangan = $_POST['keterangan'][$siswa_id] ?? '';

        $chk = $conn->prepare("SELECT id FROM presensi WHERE siswa_id=? AND kelas_id=? AND mapel_id=? AND tanggal=?");
        $chk->bind_param("iiis", $siswa_id, $kelas_id, $mapel_id, $tanggal);
        $chk->execute();
        $res = $chk->get_result();

        if ($res->num_rows > 0) {
            $p_id = $res->fetch_assoc()['id'];
            $upd = $conn->prepare("UPDATE presensi SET status=?, keterangan=?, is_draft=? WHERE id=?");
            $upd->bind_param("ssii", $status, $keterangan, $is_draft, $p_id);
            $upd->execute();
            log_audit($conn, $user_id, $p_id, "UPDATE_PRESENSI", "Status diubah menjadi $status ($keterangan)");
        } else {
            $ins = $conn->prepare("INSERT INTO presensi (siswa_id, kelas_id, mapel_id, guru_id, tanggal, status, keterangan, is_draft) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("iiiisssi", $siswa_id, $kelas_id, $mapel_id, $guru_id, $tanggal, $status, $keterangan, $is_draft);
            $ins->execute();
            $p_id = $ins->insert_id;
            log_audit($conn, $user_id, $p_id, "INSERT_PRESENSI", "Input presensi status $status");
        }
    }
    if ($msg === '') { $msg = "Presensi berhasil disimpan " . ($is_draft ? "sebagai Draf." : "secara Final."); }
}

// Urutkan kelas VII - XII
$kelas_list = $conn->query("
    SELECT * FROM kelas 
    ORDER BY 
        CASE 
            WHEN UPPER(nama_kelas) LIKE 'XII%' THEN 6
            WHEN UPPER(nama_kelas) LIKE 'XI%' THEN 5
            WHEN UPPER(nama_kelas) LIKE 'X%' THEN 4
            WHEN UPPER(nama_kelas) LIKE 'IX%' THEN 3
            WHEN UPPER(nama_kelas) LIKE 'VIII%' THEN 2
            WHEN UPPER(nama_kelas) LIKE 'VII%' THEN 1
            ELSE 99
        END ASC,
        nama_kelas ASC
");

$selected_kelas = isset($_GET['kelas_id']) ? intval($_GET['kelas_id']) : 0;
$selected_mapel = isset($_GET['mapel_id']) ? intval($_GET['mapel_id']) : 0;

// Ambil mata pelajaran khusus milik guru yang sedang login (sesuai kelas jika dipilih)
if ($selected_kelas > 0) {
    $stmt_m = $conn->prepare("SELECT * FROM mapel WHERE guru_id = ? AND (kelas_id = ? OR kelas_id IS NULL) ORDER BY nama_mapel ASC");
    $stmt_m->bind_param("ii", $user_id, $selected_kelas);
} else {
    $stmt_m = $conn->prepare("SELECT * FROM mapel WHERE guru_id = ? ORDER BY nama_mapel ASC");
    $stmt_m->bind_param("i", $user_id);
}
$stmt_m->execute();
$mapel_list = $stmt_m->get_result();

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar">
        <h2>Pencatatan Presensi Siswa</h2>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg); ?></div><?php endif; ?>

    <form method="GET" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 10px;">
            <select name="kelas_id" class="form-control" required>
                <option value="">-- Pilih Kelas --</option>
                <?php while ($k = $kelas_list->fetch_assoc()): ?>
                    <option value="<?= $k['id']; ?>" <?= ($selected_kelas == $k['id']) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($k['nama_kelas']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="btn btn-primary">Tampilkan Siswa</button>
        </div>
    </form>

    <?php if ($selected_kelas > 0): 
        $siswa_query = $conn->query("SELECT s.id, u.nama_lengkap, s.nis FROM siswa s JOIN users u ON s.user_id = u.id WHERE s.kelas_id = $selected_kelas ORDER BY u.nama_lengkap ASC");
    ?>
    <form method="POST">
        <input type="hidden" name="kelas_id" value="<?= $selected_kelas; ?>">
        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <div style="flex:1;">
                <label>Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d'); ?>" required>
            </div>
            <div style="flex:1;">
                <label>Mata Pelajaran Anda</label>
                <select name="mapel_id" class="form-control" required>
                    <option value="">-- Pilih Mata Pelajaran --</option>
                    <?php while ($m = $mapel_list->fetch_assoc()): ?>
                        <option value="<?= $m['id']; ?>" <?= ($selected_mapel == $m['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($m['kode_mapel']); ?> - <?= htmlspecialchars($m['nama_mapel']); ?> (<?= htmlspecialchars($m['jadwal'] ?? ''); ?>, <?= htmlspecialchars($m['sks'] ?? ''); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th>Akumulasi Terlambat</th>
                        <th>Status Kehadiran</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($siswa_query && $siswa_query->num_rows > 0): ?>
                        <?php while ($s = $siswa_query->fetch_assoc()): 
                            $s_id = $s['id'];
                            $late_cnt = $conn->query("SELECT COUNT(*) as total FROM presensi WHERE siswa_id = $s_id AND status = 'Terlambat' AND is_draft = 0")->fetch_assoc()['total'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($s['nis']); ?></td>
                            <td><?= htmlspecialchars($s['nama_lengkap']); ?></td>
                            <td><b style="color: #dd6b20;"><?= $late_cnt; ?>x</b> (Konversi Alpa: <?= floor($late_cnt / 3); ?>)</td>
                            <td>
                                <select name="status[<?= $s['id']; ?>]" class="form-control" required>
                                    <option value="Hadir">Hadir</option>
                                    <option value="Terlambat">Terlambat</option>
                                    <option value="Izin">Izin</option>
                                    <option value="Sakit">Sakit</option>
                                    <option value="Alpa">Alpa</option>
                                </select>
                            </td>
                            <td><input type="text" name="keterangan[<?= $s['id']; ?>]" class="form-control" placeholder="Alasan / Catatan"></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center;">Belum ada siswa terdaftar di kelas ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px; display: flex; gap: 10px;">
            <button type="submit" name="simpan_presensi" value="final" class="btn btn-success">Simpan Final</button>
            <button type="submit" name="simpan_presensi" value="draft" class="btn btn-warning">Simpan Draf</button>
        </div>
    </form>
    <?php endif; ?>
</main>
<?php include '../includes/footer.php'; ?>