<?php
require_once '../config/database.php';
check_access(['admin']);

$msg = '';
$err = '';

// Proses Tambah / Edit / Hapus Mata Pelajaran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_mapel'])) {
        $kode = trim($_POST['kode_mapel']);
        $nama = trim($_POST['nama_mapel']);

        if (!empty($kode) && !empty($nama)) {
            try {
                $stmt = $conn->prepare("INSERT INTO mapel (kode_mapel, nama_mapel) VALUES (?, ?)");
                $stmt->bind_param("ss", $kode, $nama);
                if ($stmt->execute()) {
                    $msg = "Mata pelajaran berhasil ditambahkan.";
                }
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                $err = "Gagal menambahkan! Kode Mapel <b>'" . htmlspecialchars($kode) . "'</b> sudah terdaftar di sistem.";
            }
        } else {
            $err = "Mohon isi Kode Mapel dan Nama Mata Pelajaran.";
        }
    } elseif (isset($_POST['edit_mapel'])) {
        $id   = intval($_POST['id']);
        $kode = trim($_POST['kode_mapel']);
        $nama = trim($_POST['nama_mapel']);

        if (!empty($id) && !empty($kode) && !empty($nama)) {
            try {
                $stmt = $conn->prepare("UPDATE mapel SET kode_mapel = ?, nama_mapel = ? WHERE id = ?");
                $stmt->bind_param("ssi", $kode, $nama, $id);
                if ($stmt->execute()) {
                    $msg = "Mata pelajaran berhasil diperbarui.";
                }
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                $err = "Gagal memperbarui! Kode Mapel <b>'" . htmlspecialchars($kode) . "'</b> sudah digunakan oleh mata pelajaran lain.";
            }
        } else {
            $err = "Mohon lengkapi data mata pelajaran.";
        }
    } elseif (isset($_POST['hapus_mapel'])) {
        $id = intval($_POST['id']);
        try {
            $stmt = $conn->prepare("DELETE FROM mapel WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $msg = "Mata pelajaran berhasil dihapus.";
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $err = "Gagal menghapus mata pelajaran karena masih terhubung dengan data presensi.";
        }
    }
}

// Ambil Seluruh Data Mapel dari Database
$mapel = $conn->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <h2>Mengelola Data Mata Pelajaran</h2>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= $msg; ?></div>
    <?php endif; ?>
    
    <?php if ($err): ?>
        <div class="alert alert-danger"><?= $err; ?></div>
    <?php endif; ?>

    <!-- Form Tambah Mapel -->
    <div class="card" style="margin-bottom: 25px;">
        <h3>Tambah Mata Pelajaran Baru</h3>
        <form method="POST" style="margin-top: 15px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="kode_mapel">Kode Mapel</label>
                <input type="text" name="kode_mapel" id="kode_mapel" class="form-control" placeholder="Contoh: MAT-X" required autocomplete="off">
            </div>
            <div class="form-group" style="flex: 2; min-width: 250px;">
                <label for="nama_mapel">Nama Mata Pelajaran</label>
                <input type="text" name="nama_mapel" id="nama_mapel" class="form-control" placeholder="Contoh: Matematika" required autocomplete="off">
            </div>
            <div class="form-group">
                <button type="submit" name="tambah_mapel" class="btn btn-success" style="padding: 10px 20px; font-weight: 600;">Tambah Mapel</button>
            </div>
        </form>
    </div>

    <!-- Tabel Data Mapel -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Kode Mapel</th>
                    <th>Nama Mata Pelajaran</th>
                    <th style="width: 160px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                if ($mapel && $mapel->num_rows > 0): 
                    while ($m = $mapel->fetch_assoc()): 
                ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td><b><?= htmlspecialchars($m['kode_mapel']); ?></b></td>
                    <td><?= htmlspecialchars($m['nama_mapel']); ?></td>
                    <td>
                        <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                            <!-- Tombol Edit (Oranye) -->
                            <button type="button" 
                                    onclick="bukaModalEdit(<?= $m['id']; ?>, '<?= addslashes(htmlspecialchars($m['kode_mapel'])); ?>', '<?= addslashes(htmlspecialchars($m['nama_mapel'])); ?>')" 
                                    style="background-color: #e67e22; color: white; border: none; padding: 6px 14px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                Edit
                            </button>
                            
                            <!-- Tombol Hapus (Merah) -->
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus mata pelajaran ini?');">
                                <input type="hidden" name="id" value="<?= $m['id']; ?>">
                                <button type="submit" 
                                        name="hapus_mapel" 
                                        style="background-color: #e74c3c; color: white; border: none; padding: 6px 14px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php 
                    endwhile; 
                else: 
                ?>
                <tr>
                    <td colspan="4" style="text-align: center;">Belum ada data mata pelajaran.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modal Edit Mata Pelajaran -->
<div id="modalEdit" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
    <div class="card" style="width: 100%; max-width: 480px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0; color: #1b365d;">Edit Mata Pelajaran</h3>
        <form method="POST" style="margin-top: 15px;">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="edit_kode_mapel">Kode Mapel</label>
                <input type="text" name="kode_mapel" id="edit_kode_mapel" class="form-control" required autocomplete="off">
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="edit_nama_mapel">Nama Mata Pelajaran</label>
                <input type="text" name="nama_mapel" id="edit_nama_mapel" class="form-control" required autocomplete="off">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-danger" onclick="tutupModalEdit()" style="background-color: #7f8c8d; border: none;">Batal</button>
                <button type="submit" name="edit_mapel" class="btn btn-primary" style="background-color: #e67e22; border: none; font-weight: 600;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalEdit(id, kode, nama) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_kode_mapel').value = kode;
    document.getElementById('edit_nama_mapel').value = nama;
    document.getElementById('modalEdit').style.display = 'flex';
}

function tutupModalEdit() {
    document.getElementById('modalEdit').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>