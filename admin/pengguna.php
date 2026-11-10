<?php
require_once '../config/database.php';
check_access(['admin']);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Tambah Orang Tua
    if (isset($_POST['tambah_orang_tua'])) {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $nama     = trim($_POST['nama_lengkap']);
        $siswa_id = intval($_POST['siswa_id']);

        $conn->begin_transaction();
        try {
            $u_stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, role, status) VALUES (?, ?, ?, 'orang_tua', 'aktif')");
            $u_stmt->bind_param("sss", $username, $password, $nama);
            $u_stmt->execute();
            $user_id = $u_stmt->insert_id;

            $o_stmt = $conn->prepare("INSERT INTO orang_tua (user_id, siswa_id) VALUES (?, ?)");
            $o_stmt->bind_param("ii", $user_id, $siswa_id);
            $o_stmt->execute();

            $conn->commit();
            $msg = "Akun Orang Tua berhasil dibuat dan dihubungkan!";
        } catch (Exception $e) {
            $conn->rollback();
            $err = "Gagal membuat akun: " . $e->getMessage();
        }
    } 
    // 2. Edit User (Admin Akses Penuh)
    elseif (isset($_POST['edit_user'])) {
        $user_id   = intval($_POST['user_id']);
        $nama      = trim($_POST['nama_lengkap']);
        $username  = trim($_POST['username']);
        $status    = $_POST['status'];

        $conn->begin_transaction();
        try {
            $u_stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, username = ?, status = ? WHERE id = ?");
            $u_stmt->bind_param("sssi", $nama, $username, $status, $user_id);
            $u_stmt->execute();

            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $p_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $p_stmt->bind_param("si", $password, $user_id);
                $p_stmt->execute();
            }

            $conn->commit();
            $msg = "Pengguna berhasil diperbarui!";
        } catch (Exception $e) {
            $conn->rollback();
            $err = "Gagal mengedit pengguna: " . $e->getMessage();
        }
    } 
    // 3. Hapus User (Admin Akses Penuh)
    elseif (isset($_POST['hapus_user'])) {
        $user_id = intval($_POST['user_id']);
        if ($user_id == $_SESSION['user_id']) {
            $err = "Anda tidak dapat menghapus akun Anda sendiri yang sedang terhubung!";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $msg = "Pengguna berhasil dihapus!";
            } else {
                $err = "Gagal menghapus pengguna.";
            }
        }
    }
}

$siswa_opt = $conn->query("SELECT s.id, u.nama_lengkap, s.nis FROM siswa s JOIN users u ON s.user_id = u.id ORDER BY u.nama_lengkap ASC");
$users_list = $conn->query("SELECT id, username, nama_lengkap, role, status FROM users ORDER BY role, nama_lengkap ASC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="main-content">
    <div class="topbar"><h2>Mengelola & Edit Seluruh Akun Pengguna</h2></div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card" style="margin-bottom: 25px;">
        <h3>Buat Akun Orang Tua & Sambungkan ke Anak/Siswa</h3>
        <form method="POST" style="margin-top: 15px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label>Nama Orang Tua</label>
                    <input type="text" name="nama_lengkap" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Pilih Anak / Siswa</label>
                    <select name="siswa_id" class="form-control" required>
                        <option value="">-- Pilih Siswa --</option>
                        <?php while ($s = $siswa_opt->fetch_assoc()): ?>
                            <option value="<?= $s['id']; ?>"><?= htmlspecialchars($s['nama_lengkap']); ?> (NIS: <?= htmlspecialchars($s['nis']); ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="tambah_orang_tua" class="btn btn-success">Buat Akun Orang Tua</button>
        </form>
    </div>

    <h3>Daftar Seluruh Pengguna Sistem</h3>
    <div class="table-responsive" style="margin-top: 15px;">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Nama Lengkap</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Aksi Admin</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($u = $users_list->fetch_assoc()): ?>
                <tr>
                    <td><b><?= htmlspecialchars($u['username']); ?></b></td>
                    <td><?= htmlspecialchars($u['nama_lengkap']); ?></td>
                    <td><span class="badge badge-<?= $u['role'] === 'admin' ? 'hadir' : 'izin'; ?>"><?= strtoupper($u['role']); ?></span></td>
                    <td><span class="badge badge-<?= $u['status'] === 'aktif' ? 'hadir' : 'alpa'; ?>"><?= $u['status']; ?></span></td>
                    <td>
                        <button type="button" class="btn btn-warning btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($u)); ?>)">Edit</button>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus pengguna ini secara permanen?');">
                            <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                            <button type="submit" name="hapus_user" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modal Edit User oleh Admin -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:999;">
    <div style="background:#fff; padding:25px; border-radius:8px; width:90%; max-width:400px;">
        <h3>Edit Akun Pengguna</h3>
        <form method="POST" style="margin-top:15px;">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" id="edit_username" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Status Akun</label>
                <select name="status" id="edit_status" class="form-control" required>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </select>
            </div>
            <div class="form-group">
                <label>Password Baru (Kosongkan jika tidak diubah)</label>
                <input type="password" name="password" class="form-control">
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:15px;">
                <button type="button" class="btn btn-danger" onclick="closeEditModal()">Batal</button>
                <button type="submit" name="edit_user" class="btn btn-success">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('edit_user_id').value = data.id;
    document.getElementById('edit_nama').value = data.nama_lengkap;
    document.getElementById('edit_username').value = data.username;
    document.getElementById('edit_status').value = data.status;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>