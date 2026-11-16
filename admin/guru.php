<?php
require_once '../config/database.php';
check_access(['admin']);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_guru'])) {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $nama     = trim($_POST['nama_lengkap']);
        $nip      = trim($_POST['nip']);

        $conn->begin_transaction();
        try {
            $u_stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, role, status) VALUES (?, ?, ?, 'guru', 'aktif')");
            $u_stmt->bind_param("sss", $username, $password, $nama);
            $u_stmt->execute();
            $user_id = $u_stmt->insert_id;

            $g_stmt = $conn->prepare("INSERT INTO guru (user_id, nip) VALUES (?, ?)");
            $g_stmt->bind_param("is", $user_id, $nip);
            $g_stmt->execute();

            $conn->commit();
            $msg = "Data guru berhasil ditambahkan!";
        } catch (Exception $e) {
            $conn->rollback();
            $err = "Gagal menambah guru (Username atau NIP mungkin sudah terdaftar).";
        }
    } elseif (isset($_POST['edit_guru'])) {
        $user_id  = intval($_POST['user_id']);
        $guru_id  = intval($_POST['guru_id']);
        $nip      = trim($_POST['nip']);
        $nama     = trim($_POST['nama_lengkap']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        $conn->begin_transaction();
        try {
            // Update tabel users
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $u_stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, nama_lengkap = ? WHERE id = ?");
                $u_stmt->bind_param("sssi", $username, $hashed_password, $nama, $user_id);
            } else {
                $u_stmt = $conn->prepare("UPDATE users SET username = ?, nama_lengkap = ? WHERE id = ?");
                $u_stmt->bind_param("ssi", $username, $nama, $user_id);
            }
            $u_stmt->execute();

            // Update tabel guru
            $g_stmt = $conn->prepare("UPDATE guru SET nip = ? WHERE id = ?");
            $g_stmt->bind_param("si", $nip, $guru_id);
            $g_stmt->execute();

            $conn->commit();
            $msg = "Data guru berhasil diperbarui!";
        } catch (Exception $e) {
            $conn->rollback();
            $err = "Gagal memperbarui data guru (Username atau NIP mungkin sudah terdaftar).";
        }
    } elseif (isset($_POST['hapus_guru'])) {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $msg = "Data guru berhasil dihapus!";
        } else {
            $err = "Gagal menghapus data guru.";
        }
    }
}

$guru_list = $conn->query("SELECT g.id as guru_id, u.id as user_id, u.nama_lengkap, u.username, g.nip 
                           FROM guru g 
                           JOIN users u ON g.user_id = u.id 
                           ORDER BY u.nama_lengkap ASC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    .password-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }
    .password-wrapper input {
        padding-right: 40px !important;
    }
    .toggle-password-btn {
        position: absolute;
        right: 12px;
        cursor: pointer;
        user-select: none;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #475569;
        transition: color 0.2s;
    }
    .toggle-password-btn:hover {
        color: #1b365d;
    }
    .toggle-password-btn svg {
        width: 19px;
        height: 19px;
    }
</style>

<main class="main-content">
    <div class="topbar">
        <h2>Mengelola Data Guru</h2>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card" style="margin-bottom: 25px;">
        <h3>Tambah Guru Baru</h3>
        <form method="POST" style="margin-top: 15px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label>NIP</label>
                    <input type="text" name="nip" class="form-control" required autocomplete="off">
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
                    <div class="password-wrapper">
                        <input type="password" name="password" id="tambah_password" class="form-control" required>
                        <span class="toggle-password-btn" onclick="togglePasswordField('tambah_password', 'eyeIconTambah')" title="Lihat Password">
                            <svg id="eyeIconTambah" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                    </div>
                </div>
            </div>
            <button type="submit" name="tambah_guru" class="btn btn-success" style="margin-top: 15px; padding: 10px 20px; font-weight: 600;">Simpan Data Guru</button>
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>NIP</th>
                    <th>Nama Lengkap</th>
                    <th>Username</th>
                    <th style="width: 160px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                if ($guru_list && $guru_list->num_rows > 0):
                    while ($row = $guru_list->fetch_assoc()): 
                ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td><b><?= htmlspecialchars($row['nip']); ?></b></td>
                    <td><?= htmlspecialchars($row['nama_lengkap']); ?></td>
                    <td><?= htmlspecialchars($row['username']); ?></td>
                    <td>
                        <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                            <!-- Tombol Edit (Oranye) -->
                            <button type="button" 
                                    onclick="bukaModalEditGuru(<?= $row['guru_id']; ?>, <?= $row['user_id']; ?>, '<?= addslashes(htmlspecialchars($row['nip'])); ?>', '<?= addslashes(htmlspecialchars($row['nama_lengkap'])); ?>', '<?= addslashes(htmlspecialchars($row['username'])); ?>')" 
                                    style="background-color: #e67e22; color: white; border: none; padding: 6px 14px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                Edit
                            </button>

                            <!-- Tombol Hapus (Merah) -->
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Hapus data guru ini?');">
                                <input type="hidden" name="user_id" value="<?= $row['user_id']; ?>">
                                <button type="submit" 
                                        name="hapus_guru" 
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
                    <td colspan="5" style="text-align: center;">Belum ada data guru.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modal Edit Guru -->
<div id="modalEditGuru" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
    <div class="card" style="width: 100%; max-width: 500px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0; color: #1b365d;">Edit Data Guru</h3>
        <form method="POST" style="margin-top: 15px;">
            <input type="hidden" name="guru_id" id="edit_guru_id">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="form-group" style="margin-bottom: 12px;">
                <label>NIP</label>
                <input type="text" name="nip" id="edit_nip" class="form-control" required autocomplete="off">
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Nama Lengkap</label>
                <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control" required autocomplete="off">
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Username</label>
                <input type="text" name="username" id="edit_username" class="form-control" required autocomplete="off">
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Password Baru <small style="color: #7f8c8d;">(Kosongkan jika tidak ingin mengubah password)</small></label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="edit_password" class="form-control" placeholder="Password baru...">
                    <span class="toggle-password-btn" onclick="togglePasswordField('edit_password', 'eyeIconEdit')" title="Lihat Password">
                        <svg id="eyeIconEdit" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-danger" onclick="tutupModalEditGuru()" style="background-color: #7f8c8d; border: none;">Batal</button>
                <button type="submit" name="edit_guru" class="btn btn-primary" style="background-color: #e67e22; border: none; font-weight: 600;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
const eyeOpenSVG = `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />`;
const eyeClosedSVG = `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />`;

function togglePasswordField(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = eyeClosedSVG;
    } else {
        input.type = 'password';
        icon.innerHTML = eyeOpenSVG;
    }
}

function bukaModalEditGuru(guruId, userId, nip, nama, username) {
    document.getElementById('edit_guru_id').value = guruId;
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_nip').value = nip;
    document.getElementById('edit_nama_lengkap').value = nama;
    document.getElementById('edit_username').value = username;
    
    // Reset password input & ikon ke mode tersembunyi setiap modal dibuka
    const editPassInput = document.getElementById('edit_password');
    editPassInput.value = '';
    editPassInput.type = 'password';
    document.getElementById('eyeIconEdit').innerHTML = eyeOpenSVG;

    document.getElementById('modalEditGuru').style.display = 'flex';
}

function tutupModalEditGuru() {
    document.getElementById('modalEditGuru').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>