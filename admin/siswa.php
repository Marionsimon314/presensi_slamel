<?php
require_once '../config/database.php';
check_access(['admin']);

$msg = '';
$err = '';

// Process Add / Edit / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_siswa'])) {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $nama     = trim($_POST['nama_lengkap']);
        $nis      = trim($_POST['nis']);
        $kelas_id = intval($_POST['kelas_id']);
        $jk       = $_POST['jenis_kelamin'];

        // Cek apakah username sudah dipakai
        $cek_u = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $cek_u->bind_param("s", $username);
        $cek_u->execute();
        if ($cek_u->get_result()->num_rows > 0) {
            $err = "Gagal menambah siswa: Username <b>'" . htmlspecialchars($username) . "'</b> sudah digunakan! Silakan gunakan username lain.";
            $cek_u->close();
        } else {
            $cek_u->close();
            $conn->begin_transaction();
            try {
                $u_stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, role, status) VALUES (?, ?, ?, 'siswa', 'aktif')");
                $u_stmt->bind_param("sss", $username, $password, $nama);
                $u_stmt->execute();
                $user_id = $u_stmt->insert_id;

                $s_stmt = $conn->prepare("INSERT INTO siswa (user_id, nis, kelas_id, jenis_kelamin) VALUES (?, ?, ?, ?)");
                $s_stmt->bind_param("isis", $user_id, $nis, $kelas_id, $jk);
                $s_stmt->execute();

                $conn->commit();
                $msg = "Siswa berhasil ditambahkan!";
            } catch (Exception $e) {
                $conn->rollback();
                $err = "Gagal menambah siswa: NIS atau data duplikat sudah terdaftar.";
            }
        }
    } elseif (isset($_POST['edit_siswa'])) {
        $user_id  = intval($_POST['user_id']);
        $siswa_id = intval($_POST['siswa_id']);
        $nis      = trim($_POST['nis']);
        $nama     = trim($_POST['nama_lengkap']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $kelas_id = intval($_POST['kelas_id']);
        $jk       = $_POST['jenis_kelamin'];

        $conn->begin_transaction();
        try {
            // Update tabel users (password diubah hanya jika diisi)
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $u_stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, nama_lengkap = ? WHERE id = ?");
                $u_stmt->bind_param("sssi", $username, $hashed, $nama, $user_id);
            } else {
                $u_stmt = $conn->prepare("UPDATE users SET username = ?, nama_lengkap = ? WHERE id = ?");
                $u_stmt->bind_param("ssi", $username, $nama, $user_id);
            }
            $u_stmt->execute();

            // Update tabel siswa
            $s_stmt = $conn->prepare("UPDATE siswa SET nis = ?, kelas_id = ?, jenis_kelamin = ? WHERE id = ?");
            $s_stmt->bind_param("sisi", $nis, $kelas_id, $jk, $siswa_id);
            $s_stmt->execute();

            $conn->commit();
            $msg = "Data siswa berhasil diperbarui!";
        } catch (Exception $e) {
            $conn->rollback();
            $err = "Gagal memperbarui data siswa (Username atau NIS mungkin sudah digunakan).";
        }
    } elseif (isset($_POST['hapus_siswa'])) {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $msg = "Siswa berhasil dihapus!";
        } else {
            $err = "Gagal menghapus siswa.";
        }
    }
}

// Search Filter
$search = $_GET['search'] ?? '';
$where = "";
if (!empty($search)) {
    $s_clean = $conn->real_escape_string($search);
    $where = "WHERE u.nama_lengkap LIKE '%$s_clean%' OR s.nis LIKE '%$s_clean%' OR u.username LIKE '%$s_clean%'";
}

// Ambil Pilihan Kelas (disimpan ke array agar query bisa di-loop 2x untuk Form Tambah & Modal Edit)
$res_kelas = $conn->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas");
$kelas_array = [];
if ($res_kelas) {
    while ($k = $res_kelas->fetch_assoc()) {
        $kelas_array[] = $k;
    }
}

$siswa_list = $conn->query("SELECT s.id as siswa_id, u.id as user_id, u.nama_lengkap, u.username, s.nis, s.jenis_kelamin, k.nama_kelas, k.id as kelas_id 
                            FROM siswa s 
                            JOIN users u ON s.user_id = u.id 
                            JOIN kelas k ON s.kelas_id = k.id 
                            $where 
                            ORDER BY u.nama_lengkap ASC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar">
        <h2>Mengelola Data Siswa</h2>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg; ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= $err; ?></div><?php endif; ?>

    <!-- Form Tambah Siswa -->
    <div class="card" style="margin-bottom: 25px;">
        <h3>Tambah Siswa Baru</h3>
        <form method="POST" style="margin-top: 15px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label>NIS</label>
                    <input type="text" name="nis" class="form-control" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Kelas</label>
                    <select name="kelas_id" class="form-control" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($kelas_array as $k): ?>
                            <option value="<?= $k['id']; ?>"><?= htmlspecialchars($k['nama_kelas']); ?> (<?= $k['tingkat']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="form-control" required>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="tambah_siswa" class="btn btn-success" style="margin-top: 10px; padding: 10px 20px; font-weight: 600;">Simpan Siswa</button>
        </form>
    </div>

    <!-- Filter Pencarian -->
    <form method="GET" style="margin-bottom: 15px; display: flex; gap: 10px;">
        <input type="text" name="search" class="form-control" placeholder="Cari NIS atau Nama Siswa..." value="<?= htmlspecialchars($search); ?>">
        <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">Cari</button>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>NIS</th>
                    <th>Nama Lengkap</th>
                    <th>Username</th>
                    <th>Kelas</th>
                    <th>L/P</th>
                    <th style="width: 160px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                if ($siswa_list && $siswa_list->num_rows > 0):
                    while ($row = $siswa_list->fetch_assoc()): 
                ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td><b><?= htmlspecialchars($row['nis']); ?></b></td>
                    <td><?= htmlspecialchars($row['nama_lengkap']); ?></td>
                    <td><?= htmlspecialchars($row['username']); ?></td>
                    <td><?= htmlspecialchars($row['nama_kelas']); ?></td>
                    <td><?= $row['jenis_kelamin']; ?></td>
                    <td>
                        <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                            <!-- Tombol Edit (Oranye) -->
                            <button type="button" 
                                    onclick="bukaModalEditSiswa(<?= $row['siswa_id']; ?>, <?= $row['user_id']; ?>, '<?= addslashes(htmlspecialchars($row['nis'])); ?>', '<?= addslashes(htmlspecialchars($row['nama_lengkap'])); ?>', '<?= addslashes(htmlspecialchars($row['username'])); ?>', <?= $row['kelas_id']; ?>, '<?= $row['jenis_kelamin']; ?>')" 
                                    style="background-color: #e67e22; color: white; border: none; padding: 6px 14px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                Edit
                            </button>

                            <!-- Tombol Hapus (Merah) -->
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Yakin menghapus siswa ini?');">
                                <input type="hidden" name="user_id" value="<?= $row['user_id']; ?>">
                                <button type="submit" 
                                        name="hapus_siswa" 
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
                    <td colspan="7" style="text-align: center;">Belum ada data siswa.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modal Edit Siswa -->
<div id="modalEditSiswa" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
    <div class="card" style="width: 100%; max-width: 520px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0; color: #1b365d;">Edit Data Siswa</h3>
        <form method="POST" style="margin-top: 15px;">
            <input type="hidden" name="siswa_id" id="edit_siswa_id">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label>NIS</label>
                    <input type="text" name="nis" id="edit_nis" class="form-control" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control" required autocomplete="off">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="edit_username" class="form-control" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Password Baru <small style="color:#7f8c8d;">(Opsional)</small></label>
                    <input type="password" name="password" id="edit_password" class="form-control" placeholder="Kosongkan jika tak diubah">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                <div class="form-group">
                    <label>Kelas</label>
                    <select name="kelas_id" id="edit_kelas_id" class="form-control" required>
                        <?php foreach ($kelas_array as $k): ?>
                            <option value="<?= $k['id']; ?>"><?= htmlspecialchars($k['nama_kelas']); ?> (<?= $k['tingkat']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jenis Kelamin</label>
                    <select name="jenis_kelamin" id="edit_jk" class="form-control" required>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-danger" onclick="tutupModalEditSiswa()" style="background-color: #7f8c8d; border: none;">Batal</button>
                <button type="submit" name="edit_siswa" class="btn btn-primary" style="background-color: #e67e22; border: none; font-weight: 600;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalEditSiswa(siswaId, userId, nis, nama, username, kelasId, jk) {
    document.getElementById('edit_siswa_id').value = siswaId;
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_nis').value = nis;
    document.getElementById('edit_nama_lengkap').value = nama;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_kelas_id').value = kelasId;
    document.getElementById('edit_jk').value = jk;
    document.getElementById('edit_password').value = '';
    document.getElementById('modalEditSiswa').style.display = 'flex';
}

function tutupModalEditSiswa() {
    document.getElementById('modalEditSiswa').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>