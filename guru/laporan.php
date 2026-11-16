<?php
require_once '../config/database.php';
check_access(['guru']);

$user_id = $_SESSION['user_id'];
$guru = $conn->query("SELECT id FROM guru WHERE user_id = $user_id")->fetch_assoc();
$guru_id = $guru['id'] ?? 0;

$kelas_id = $_GET['kelas_id'] ?? '';
$bulan    = $_GET['bulan'] ?? date('Y-m');

// Ambil HANYA kelas yang diinputkan Admin pada tabel mapel untuk guru ini (berurutan VII - XII)
$stmt_kelas = $conn->prepare("
    SELECT DISTINCT k.id, k.nama_kelas 
    FROM kelas k
    JOIN mapel m ON m.kelas_id = k.id
    WHERE m.guru_id = ?
    ORDER BY 
        CASE 
            WHEN UPPER(k.nama_kelas) LIKE 'XII%' THEN 6
            WHEN UPPER(k.nama_kelas) LIKE 'XI%' THEN 5
            WHEN UPPER(k.nama_kelas) LIKE 'X%' THEN 4
            WHEN UPPER(k.nama_kelas) LIKE 'IX%' THEN 3
            WHEN UPPER(k.nama_kelas) LIKE 'VIII%' THEN 2
            WHEN UPPER(k.nama_kelas) LIKE 'VII%' THEN 1
            ELSE 99
        END ASC,
        k.nama_kelas ASC
");
$stmt_kelas->bind_param("i", $user_id);
$stmt_kelas->execute();
$kelas_list = $stmt_kelas->get_result();

// Simpan daftar ID kelas yang sah untuk validasi akses
$allowed_kelas_ids = [];
$kelas_options = [];
while ($row_k = $kelas_list->fetch_assoc()) {
    $allowed_kelas_ids[] = $row_k['id'];
    $kelas_options[] = $row_k;
}

$report_data = null;
$err = '';

if (!empty($kelas_id)) {
    $k_id = intval($kelas_id);

    // Pastikan guru hanya bisa melihat kelas yang memang diampu
    if (!in_array($k_id, $allowed_kelas_ids)) {
        $err = "Anda tidak memiliki akses ke laporan kelas tersebut.";
    } else {
        $stmt_rep = $conn->prepare("
            SELECT s.nis, u.nama_lengkap,
                SUM(CASE WHEN p.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN p.status = 'Terlambat' THEN 1 ELSE 0 END) as terlambat,
                SUM(CASE WHEN p.status = 'Izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN p.status = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN p.status = 'Alpa' THEN 1 ELSE 0 END) as alpa_murni
            FROM siswa s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN presensi p ON s.id = p.siswa_id 
                AND DATE_FORMAT(p.tanggal, '%Y-%m') = ? 
                AND p.is_draft = 0
                AND p.guru_id = ?
            WHERE s.kelas_id = ?
            GROUP BY s.id, s.nis, u.nama_lengkap
            ORDER BY u.nama_lengkap ASC
        ");
        $stmt_rep->bind_param("sii", $bulan, $guru_id, $k_id);
        $stmt_rep->execute();
        $report_data = $stmt_rep->get_result();
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar">
        <h2>Laporan Presensi Kelas (Guru)</h2>
    </div>

    <?php if ($err): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($err); ?></div>
    <?php endif; ?>

    <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px;">
        <select name="kelas_id" class="form-control" required>
            <option value="">-- Pilih Kelas yang Anda Ampu --</option>
            <?php foreach ($kelas_options as $k): ?>
                <option value="<?= $k['id']; ?>" <?= ($kelas_id == $k['id']) ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($k['nama_kelas']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($bulan); ?>" required>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
        <?php if (!empty($kelas_id) && empty($err)): ?>
            <button type="button" onclick="window.print()" class="btn btn-success">Cetak</button>
        <?php endif; ?>
    </form>

    <?php if (count($kelas_options) === 0): ?>
        <div class="card" style="padding: 20px; background: white; border-radius: 8px; color: #64748b;">
            Belum ada kelas yang ditugaskan kepada Anda oleh Administrator pada data Mata Pelajaran.
        </div>
    <?php elseif ($report_data && $report_data->num_rows > 0): ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>NIS</th>
                    <th>Nama Siswa</th>
                    <th>Hadir</th>
                    <th>Terlambat</th>
                    <th>Izin</th>
                    <th>Sakit</th>
                    <th>Alpa Murni</th>
                    <th>Konversi Late</th>
                    <th>Total Alpa</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = $report_data->fetch_assoc()): 
                    $konversi = floor($r['terlambat'] / 3);
                    $total_alpa = $r['alpa_murni'] + $konversi;
                ?>
                <tr>
                    <td><?= htmlspecialchars($r['nis']); ?></td>
                    <td><?= htmlspecialchars($r['nama_lengkap']); ?></td>
                    <td><?= $r['hadir']; ?></td>
                    <td><?= $r['terlambat']; ?></td>
                    <td><?= $r['izin']; ?></td>
                    <td><?= $r['sakit']; ?></td>
                    <td><?= $r['alpa_murni']; ?></td>
                    <td>+<?= $konversi; ?></td>
                    <td style="color:red; font-weight:bold;"><?= $total_alpa; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php elseif (!empty($kelas_id) && empty($err)): ?>
        <div class="card" style="padding: 20px; background: white; border-radius: 8px; color: #64748b;">
            Belum ada data siswa atau catatan presensi pada kelas dan bulan yang dipilih.
        </div>
    <?php endif; ?>
</main>
<?php include '../includes/footer.php'; ?>