<?php
require_once '../config/database.php';
check_access(['guru']);

$kelas_id = $_GET['kelas_id'] ?? '';
$bulan    = $_GET['bulan'] ?? date('Y-m');

$kelas_list = $conn->query("SELECT * FROM kelas");
$report_data = [];

if (!empty($kelas_id)) {
    $k_id = intval($kelas_id);
    $b_clean = $conn->real_escape_string($bulan);

    $sql = "SELECT s.nis, u.nama_lengkap,
            SUM(CASE WHEN p.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
            SUM(CASE WHEN p.status = 'Terlambat' THEN 1 ELSE 0 END) as terlambat,
            SUM(CASE WHEN p.status = 'Izin' THEN 1 ELSE 0 END) as izin,
            SUM(CASE WHEN p.status = 'Sakit' THEN 1 ELSE 0 END) as sakit,
            SUM(CASE WHEN p.status = 'Alpa' THEN 1 ELSE 0 END) as alpa_murni
            FROM siswa s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN presensi p ON s.id = p.siswa_id AND DATE_FORMAT(p.tanggal, '%Y-%m') = '$b_clean' AND p.is_draft = 0
            WHERE s.kelas_id = $k_id
            GROUP BY s.id
            ORDER BY u.nama_lengkap ASC";
    $report_data = $conn->query($sql);
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar"><h2>Laporan Presensi Kelas (Guru)</h2></div>

    <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px;">
        <select name="kelas_id" class="form-control" required>
            <option value="">-- Pilih Kelas --</option>
            <?php while ($k = $kelas_list->fetch_assoc()): ?>
                <option value="<?= $k['id']; ?>" <?= $kelas_id == $k['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($k['nama_kelas']); ?></option>
            <?php endwhile; ?>
        </select>
        <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($bulan); ?>" required>
        <button type="submit" class="btn btn-primary">Tampilkan</button>
        <?php if (!empty($kelas_id)): ?>
            <button type="button" onclick="window.print()" class="btn btn-success">Cetak</button>
        <?php endif; ?>
    </form>

    <?php if ($report_data && $report_data->num_rows > 0): ?>
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
    <?php endif; ?>
</main>
<?php include '../includes/footer.php'; ?>