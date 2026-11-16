<?php
require_once '../config/database.php';
check_access(['admin']);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Tambah Orang Tua
    if (isset($_POST['tambah_orang_tua'])) {
        $username       = trim($_POST['username']);
        $raw_password   = $_POST['password'];
        $password       = password_hash($raw_password, PASSWORD_DEFAULT);
        $nama           = trim($_POST['nama_lengkap']);
        $siswa_id       = intval($_POST['siswa_id']);

        $conn->begin_transaction();
        try {
            $u_stmt = $conn->prepare("INSERT INTO users (username, password, password_plain, nama_lengkap, role, status) VALUES (?, ?, ?, ?, 'orang_tua', 'aktif')");
            $u_stmt->bind_param("ssss", $username, $password, $raw_password, $nama);
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
        $user_id  = intval($_POST['user_id']);
        $nama     = trim($_POST['nama_lengkap']);
        $username = trim($_POST['username']);
        $status   = $_POST['status'];

        $conn->begin_transaction();
        try {
            $u_stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, username = ?, status = ? WHERE id = ?");
            $u_stmt->bind_param("sssi", $nama, $username, $status, $user_id);
            $u_stmt->execute();

            if (!empty($_POST['password'])) {
                $raw_password = $_POST['password'];
                $password     = password_hash($raw_password, PASSWORD_DEFAULT);
                $p_stmt = $conn->prepare("UPDATE users SET password = ?, password_plain = ? WHERE id = ?");
                $p_stmt->bind_param("ssi", $password, $raw_password, $user_id);
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

// Ambil seluruh user beserta password_plain dan kelompokkan berdasarkan role
$users_list = $conn->query("
    SELECT u.id, u.username, u.password_plain, u.nama_lengkap, u.role, u.status,
           anak_u.nama_lengkap AS nama_anak
    FROM users u
    LEFT JOIN orang_tua ot ON u.id = ot.user_id
    LEFT JOIN siswa s ON ot.siswa_id = s.id
    LEFT JOIN users anak_u ON s.user_id = anak_u.id
    ORDER BY u.nama_lengkap ASC
");

$grouped_users = [
    'admin'     => [],
    'guru'      => [],
    'siswa'     => [],
    'orang_tua' => []
];

if ($users_list && $users_list->num_rows > 0) {
    while ($row = $users_list->fetch_assoc()) {
        $role_key = $row['role'];
        if (isset($grouped_users[$role_key])) {
            $grouped_users[$role_key][] = $row;
        }
    }
}

// Konfigurasi tampilan tiap grup role
$role_sections = [
    'admin' => [
        'title' => 'Daftar Akun Administrator (TU)',
        'badge' => 'ADMIN',
        'color' => '#1b365d'
    ],
    'guru' => [
        'title' => 'Daftar Akun Guru / Wali Kelas',
        'badge' => 'GURU',
        'color' => '#2563eb'
    ],
    'siswa' => [
        'title' => 'Daftar Akun Siswa',
        'badge' => 'SISWA',
        'color' => '#0d9488'
    ],
    'orang_tua' => [
        'title' => 'Daftar Akun Orang Tua Siswa',
        'badge' => 'ORANG TUA',
        'color' => '#7c3aed'
    ]
];

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
    }
    .toggle-password-btn:hover {
        color: #1b365d;
    }
    .toggle-password-btn svg {
        width: 19px;
        height: 19px;
    }
    .table-pass-box {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 5px;
        font-family: monospace;
        font-size: 0.9rem;
    }
    .btn-eye-table {
        background: none;
        border: none;
        cursor: pointer;
        color: #475569;
        display: flex;
        align-items: center;
        padding: 0;
    }
    .btn-eye-table:hover {
        color: #1b365d;
    }
</style>

<main class="main-content">
    <div class="topbar"><h2>Mengelola & Edit Seluruh Akun Pengguna</h2></div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err); ?></div><?php endif; ?>

    <div class="card" style="margin-bottom: 30px;">
        <h3>Buat Akun Orang Tua & Sambungkan ke Anak/Siswa</h3>
        <form method="POST" style="margin-top: 15px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label>Nama Orang Tua</label>
                    <input type="text" name="nama_lengkap" class="form-control" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="tambah_ortu_password" class="form-control" required>
                        <span class="toggle-password-btn" onclick="togglePass('tambah_ortu_password', 'eyeOrtu')" title="Lihat Password">
                            <svg id="eyeOrtu" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                    </div>
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
            <button type="submit" name="tambah_orang_tua" class="btn btn-success" style="margin-top: 15px;">Buat Akun Orang Tua</button>
        </form>
    </div>

    <!-- ========================================================== -->
    <!-- TABEL PENGGUNA TERPISAH: ADMIN, GURU, SISWA, ORANG TUA     -->
    <!-- ========================================================== -->
    <?php foreach ($role_sections as $role_key => $info): 
        $list = $grouped_users[$role_key];
    ?>
    <div style="margin-bottom: 30px;">
        <h3 style="color: #1b365d; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
            <span style="background: <?= $info['color']; ?>; color: white; padding: 3px 10px; border-radius: 4px; font-size: 0.8rem;">
                <?= $info['badge']; ?>
            </span>
            <?= $info['title']; ?> (<?= count($list); ?> Akun)
        </h3>
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.05);">
                <thead>
                    <tr style="background-color: <?= $info['color']; ?>; color: white; text-align: left;">
                        <th style="padding: 12px; width: 50px;">No</th>
                        <th style="padding: 12px;">Username</th>
                        <th style="padding: 12px;">Password</th>
                        <th style="padding: 12px;">Nama Lengkap</th>
                        <?php if ($role_key === 'orang_tua'): ?>
                            <th style="padding: 12px;">Orang Tua Dari (Siswa)</th>
                        <?php endif; ?>
                        <th style="padding: 12px;">Status</th>
                        <th style="padding: 12px; width: 160px; text-align: center;">Aksi Admin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($list) > 0): 
                        $no = 1;
                        foreach ($list as $u): 
                            $has_plain = !empty($u['password_plain']);
                    ?>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 12px;"><?= $no++; ?></td>
                        <td style="padding: 12px;"><b><?= htmlspecialchars($u['username']); ?></b></td>
                        <td style="padding: 12px;">
                            <?php if ($has_plain): ?>
                                <div class="table-pass-box">
                                    <span id="pass_text_<?= $u['id']; ?>" data-pass="<?= htmlspecialchars($u['password_plain']); ?>" data-shown="0">••••••••</span>
                                    <button type="button" class="btn-eye-table" onclick="toggleTablePass(<?= $u['id']; ?>)" title="Lihat / Sembunyikan Password">
                                        <svg id="eye_table_<?= $u['id']; ?>" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </button>
                                </div>
                            <?php else: ?>
                                <small style="color: #94a3b8; font-style: italic;">Terenkripsi (Klik Edit untuk atur ulang)</small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px;"><?= htmlspecialchars($u['nama_lengkap']); ?></td>
                        <?php if ($role_key === 'orang_tua'): ?>
                            <td style="padding: 12px;"><?= htmlspecialchars($u['nama_anak'] ?? '-'); ?></td>
                        <?php endif; ?>
                        <td style="padding: 12px;">
                            <span class="badge badge-<?= $u['status'] === 'aktif' ? 'hadir' : 'alpa'; ?>">
                                <?= ucfirst($u['status']); ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <button type="button" class="btn btn-warning btn-sm" onclick='openEditModal(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Edit</button>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus pengguna ini secara permanen?');">
                                <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                                <button type="submit" name="hapus_user" class="btn btn-danger btn-sm">Hapus</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                    <tr>
                        <td colspan="<?= ($role_key === 'orang_tua') ? 7 : 6; ?>" style="text-align: center; padding: 16px; color: #64748b;">
                            Belum ada data akun <?= strtolower($info['badge']); ?>.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</main>

<!-- Modal Edit User oleh Admin -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:999;">
    <div style="background:#fff; padding:25px; border-radius:8px; width:90%; max-width:420px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0; color:#1b365d;">Edit Akun Pengguna</h3>
        <form method="POST" style="margin-top:15px;">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Nama Lengkap</label>
                <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Username</label>
                <input type="text" name="username" id="edit_username" class="form-control" required>
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Status Akun</label>
                <select name="status" id="edit_status" class="form-control" required>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Password Baru <small style="color:#64748b;">(Kosongkan jika tidak diubah)</small></label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="edit_user_password" class="form-control" placeholder="Masukkan password baru...">
                    <span class="toggle-password-btn" onclick="togglePass('edit_user_password', 'eyeEditUser')" title="Lihat Password">
                        <svg id="eyeEditUser" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                </div>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <button type="button" class="btn btn-danger" onclick="closeEditModal()">Batal</button>
                <button type="submit" name="edit_user" class="btn btn-success">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
const eyeOpenSVG = `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />`;
const eyeClosedSVG = `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />`;

function togglePass(inputId, iconId) {
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

function toggleTablePass(userId) {
    const span = document.getElementById('pass_text_' + userId);
    const icon = document.getElementById('eye_table_' + userId);
    const isShown = span.getAttribute('data-shown') === '1';

    if (isShown) {
        span.textContent = '••••••••';
        span.setAttribute('data-shown', '0');
        icon.innerHTML = eyeOpenSVG;
    } else {
        span.textContent = span.getAttribute('data-pass');
        span.setAttribute('data-shown', '1');
        icon.innerHTML = eyeClosedSVG;
    }
}

function openEditModal(data) {
    document.getElementById('edit_user_id').value = data.id;
    document.getElementById('edit_nama').value = data.nama_lengkap;
    document.getElementById('edit_username').value = data.username;
    document.getElementById('edit_status').value = data.status;

    const passInput = document.getElementById('edit_user_password');
    passInput.value = data.password_plain ? data.password_plain : '';
    passInput.type = 'password';
    document.getElementById('eyeEditUser').innerHTML = eyeOpenSVG;

    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>