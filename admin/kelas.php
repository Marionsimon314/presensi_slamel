<?php
require_once '../config/database.php';
check_access(['admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_kelas'])) {
        $nama    = trim($_POST['nama_kelas']);
        $tingkat = $_POST['tingkat'];

        $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, tingkat) VALUES (?, ?)");
        $stmt->bind_param("ss", $nama, $tingkat);
        if ($stmt->execute()) {
            $msg = "Kelas berhasil ditambahkan.";
        }
    } elseif (isset($_POST['hapus_kelas'])) {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM kelas WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $msg = "Kelas berhasil dihapus.";
    }
}

// Mengurutkan data berdasarkan ID terkecil ke terbesar
$kelas = $conn->query("SELECT * FROM kelas ORDER BY id ASC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar"><h2>Mengelola Data Kelas (SMP / SMA)</h2></div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg); ?></div><?php endif; ?>

    <div class="card" style="margin-bottom: 25px;">
        <h3>Tambah Kelas</h3>
        <form method="POST" style="margin-top: 15px; display: flex; gap: 15px; align-items: flex-end;">
            <div class="form-group" style="flex: 2;">
                <label>Nama Kelas</label>
                <input type="text" name="nama_kelas" class="form-control" placeholder="Contoh: X IPA 1 atau VII A" required>
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Tingkat</label>
                <select name="tingkat" class="form-control" required>
                    <option value="SMP">SMP</option>
                    <option value="SMA">SMA</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" name="tambah_kelas" class="btn btn-success">Tambah</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Nama Kelas</th>
                    <th>Tingkat</th>
                    <th style="width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1; // Variabel penomoran otomatis
                while ($k = $kelas->fetch_assoc()): 
                ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td><?= htmlspecialchars($k['nama_kelas']); ?></td>
                    <td><?= htmlspecialchars($k['tingkat']); ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus kelas?');">
                            <input type="hidden" name="id" value="<?= $k['id']; ?>">
                            <button type="submit" name="hapus_kelas" class="btn btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include '../includes/footer.php'; ?>