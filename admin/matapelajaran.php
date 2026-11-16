<?php
require_once '../config/database.php';
check_access(['admin']);

$msg = '';
$err = '';

// Proses Tambah / Edit / Hapus Mata Pelajaran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_mapel'])) {
        $kelas_id   = !empty($_POST['kelas_id']) ? intval($_POST['kelas_id']) : NULL;
        $kode       = trim($_POST['kode_mapel']);
        $nama       = trim($_POST['nama_mapel']);
        $guru_id    = !empty($_POST['guru_id']) ? intval($_POST['guru_id']) : NULL;
        $waktu      = trim($_POST['waktu']);
        $jadwal     = trim($_POST['jadwal']);
        $ruangan    = trim($_POST['ruangan']);
        $max_absent = intval($_POST['max_absent']);

        if (!empty($kelas_id) && !empty($kode) && !empty($nama)) {
            $q_kls = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
            $q_kls->bind_param("i", $kelas_id);
            $q_kls->execute();
            $res_kls = $q_kls->get_result()->fetch_assoc();
            $nama_kls = strtoupper($res_kls['nama_kelas'] ?? '');
            $jenjang = (strpos($nama_kls, 'X') !== false || strpos($nama_kls, '10') !== false || strpos($nama_kls, '11') !== false || strpos($nama_kls, '12') !== false) ? 'SMA' : 'SMP';
            $q_kls->close();

            try {
                $stmt = $conn->prepare("INSERT INTO mapel (kode_mapel, nama_mapel, jenjang, kelas_id, guru_id, sks, jadwal, ruangan, max_absent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssiisssi", $kode, $nama, $jenjang, $kelas_id, $guru_id, $waktu, $jadwal, $ruangan, $max_absent);
                if ($stmt->execute()) {
                    $msg = "Mata pelajaran berhasil ditambahkan ke kelas <b>" . htmlspecialchars($res_kls['nama_kelas']) . "</b>.";
                }
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                $err = "Gagal menambahkan! Kode Mapel <b>'" . htmlspecialchars($kode) . "'</b> sudah terdaftar di kelas ini.";
            }
        } else {
            $err = "Mohon pilih Kelas serta isi Kode Mapel dan Nama Mata Pelajaran.";
        }
    } elseif (isset($_POST['edit_mapel'])) {
        $id         = intval($_POST['id']);
        $kelas_id   = !empty($_POST['kelas_id']) ? intval($_POST['kelas_id']) : NULL;
        $kode       = trim($_POST['kode_mapel']);
        $nama       = trim($_POST['nama_mapel']);
        $guru_id    = !empty($_POST['guru_id']) ? intval($_POST['guru_id']) : NULL;
        $waktu      = trim($_POST['waktu']);
        $jadwal     = trim($_POST['jadwal']);
        $ruangan    = trim($_POST['ruangan']);
        $max_absent = intval($_POST['max_absent']);

        if (!empty($id) && !empty($kelas_id) && !empty($kode) && !empty($nama)) {
            $q_kls = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
            $q_kls->bind_param("i", $kelas_id);
            $q_kls->execute();
            $res_kls = $q_kls->get_result()->fetch_assoc();
            $nama_kls = strtoupper($res_kls['nama_kelas'] ?? '');
            $jenjang = (strpos($nama_kls, 'X') !== false || strpos($nama_kls, '10') !== false || strpos($nama_kls, '11') !== false || strpos($nama_kls, '12') !== false) ? 'SMA' : 'SMP';
            $q_kls->close();

            try {
                $stmt = $conn->prepare("UPDATE mapel SET kode_mapel = ?, nama_mapel = ?, jenjang = ?, kelas_id = ?, guru_id = ?, sks = ?, jadwal = ?, ruangan = ?, max_absent = ? WHERE id = ?");
                $stmt->bind_param("sssiisssii", $kode, $nama, $jenjang, $kelas_id, $guru_id, $waktu, $jadwal, $ruangan, $max_absent, $id);
                if ($stmt->execute()) {
                    $msg = "Mata pelajaran berhasil diperbarui.";
                }
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                $err = "Gagal memperbarui! Kode Mapel <b>'" . htmlspecialchars($kode) . "'</b> sudah digunakan pada kelas tersebut.";
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

// Fetch Data Guru untuk Dropdown Select
$gurus = $conn->query("SELECT id, nama_lengkap FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC");

// Fetch Data Kelas Berurutan dari Kelas VII sampai XII (termasuk variasi A/B/IPA/IPS)
$kelas_res = $conn->query("
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

$daftar_kelas = [];
if ($kelas_res && $kelas_res->num_rows > 0) {
    while ($k = $kelas_res->fetch_assoc()) {
        $daftar_kelas[] = $k;
    }
}

// Ambil Seluruh Data Mapel beserta nama guru dan nama kelas
$mapel_res = $conn->query("
    SELECT m.*, u.nama_lengkap AS nama_guru, k.nama_kelas 
    FROM mapel m 
    LEFT JOIN users u ON m.guru_id = u.id 
    LEFT JOIN kelas k ON m.kelas_id = k.id 
    ORDER BY m.nama_mapel ASC
");

// Kelompokkan mapel berdasarkan kelas_id
$mapel_per_kelas = [];
$mapel_tanpa_kelas = [];
if ($mapel_res && $mapel_res->num_rows > 0) {
    while ($m = $mapel_res->fetch_assoc()) {
        if (!empty($m['kelas_id'])) {
            $mapel_per_kelas[$m['kelas_id']][] = $m;
        } else {
            $mapel_tanpa_kelas[] = $m;
        }
    }
}

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
    <div class="card" style="margin-bottom: 30px; padding: 20px; background: white; border-radius: 8px;">
        <h3>Tambah Mata Pelajaran Baru</h3>
        <form method="POST" style="margin-top: 15px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label for="kelas_id">Pilih Kelas</label>
                    <select name="kelas_id" id="kelas_id" class="form-control" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                            <option value="<?= $k['id']; ?>"><?= htmlspecialchars($k['nama_kelas']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="kode_mapel">Kode Mapel</label>
                    <input type="text" name="kode_mapel" id="kode_mapel" class="form-control" placeholder="Contoh: M.Prakar" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="nama_mapel">Nama Mata Pelajaran</label>
                    <input type="text" name="nama_mapel" id="nama_mapel" class="form-control" placeholder="Contoh: Prakar" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="guru_id">Guru / Lecturer</label>
                    <select name="guru_id" id="guru_id" class="form-control">
                        <option value="">-- Pilih Guru --</option>
                        <?php 
                        if ($gurus && $gurus->num_rows > 0) {
                            $gurus->data_seek(0);
                            while ($g = $gurus->fetch_assoc()): 
                        ?>
                            <option value="<?= $g['id']; ?>"><?= htmlspecialchars($g['nama_lengkap']); ?></option>
                        <?php 
                            endwhile; 
                        } 
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="waktu">Waktu</label>
                    <input type="text" name="waktu" id="waktu" class="form-control" placeholder="Contoh: 09.00 - 10.20" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="jadwal">Jadwal (Schedule)</label>
                    <input type="text" name="jadwal" id="jadwal" class="form-control" placeholder="Contoh: Senin" value="Senin - Jumat">
                </div>
                <div class="form-group">
                    <label for="ruangan">Ruangan (Room)</label>
                    <input type="text" name="ruangan" id="ruangan" class="form-control" placeholder="Contoh: VII A" value="Ruang Kelas">
                </div>
                <div class="form-group">
                    <label for="max_absent">Max Absent</label>
                    <input type="number" name="max_absent" id="max_absent" class="form-control" value="3">
                </div>
            </div>
            <div style="margin-top: 15px; text-align: right;">
                <button type="submit" name="tambah_mapel" class="btn btn-success" style="padding: 10px 25px; font-weight: 600; background-color: #16a34a; border: none; color: white; border-radius: 5px; cursor: pointer;">Tambah Mapel</button>
            </div>
        </form>
    </div>

    <!-- ========================================== -->
    <!-- TAMPILAN MATA PELAJARAN TERPISAH PER KELAS -->
    <!-- ========================================== -->
    <?php foreach ($daftar_kelas as $k): 
        $k_id = $k['id'];
        $nama_k = $k['nama_kelas'];
        $list_m = $mapel_per_kelas[$k_id] ?? [];
        $is_sma = (strpos(strtoupper($nama_k), 'X') !== false || strpos(strtoupper($nama_k), '10') !== false || strpos(strtoupper($nama_k), '11') !== false || strpos(strtoupper($nama_k), '12') !== false);
        $badge_color = $is_sma ? '#0d9488' : '#2563eb';
        $header_color = $is_sma ? '#1e293b' : '#1b365d';
    ?>
    <div style="margin-bottom: 30px;">
        <h3 style="color: #1b365d; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
            <span style="background: <?= $badge_color; ?>; color: white; padding: 4px 12px; border-radius: 4px; font-size: 0.85rem;">
                Kelas <?= htmlspecialchars($nama_k); ?>
            </span>
            Daftar Mata Pelajaran Kelas <?= htmlspecialchars($nama_k); ?>
        </h3>
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.05);">
                <thead>
                    <tr style="background-color: <?= $header_color; ?>; color: white; text-align: left;">
                        <th style="padding: 12px; width: 50px;">No</th>
                        <th style="padding: 12px;">Kode</th>
                        <th style="padding: 12px;">Nama Mata Pelajaran</th>
                        <th style="padding: 12px;">Lecturer</th>
                        <th style="padding: 12px;">Waktu</th>
                        <th style="padding: 12px;">Schedule</th>
                        <th style="padding: 12px;">Room</th>
                        <th style="padding: 12px;">Max Abs</th>
                        <th style="padding: 12px; width: 150px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($list_m) > 0): 
                        $no = 1;
                        foreach ($list_m as $m): 
                    ?>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 12px;"><?= $no++; ?></td>
                        <td style="padding: 12px;"><b><?= htmlspecialchars($m['kode_mapel']); ?></b></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['nama_mapel']); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['nama_guru'] ?? 'Belum Diatur'); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['sks'] ?? '-'); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['jadwal'] ?? '-'); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['ruangan'] ?? '-'); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['max_absent'] ?? '3'); ?></td>
                        <td style="padding: 12px;">
                            <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                <button type="button" 
                                        onclick="bukaModalEdit(<?= $m['id']; ?>, '<?= $m['kelas_id']; ?>', '<?= addslashes(htmlspecialchars($m['kode_mapel'])); ?>', '<?= addslashes(htmlspecialchars($m['nama_mapel'])); ?>', '<?= $m['guru_id']; ?>', '<?= addslashes(htmlspecialchars($m['sks'] ?? '')); ?>', '<?= addslashes(htmlspecialchars($m['jadwal'] ?? '')); ?>', '<?= addslashes(htmlspecialchars($m['ruangan'] ?? '')); ?>', '<?= $m['max_absent'] ?? 3; ?>')" 
                                        style="background-color: #e67e22; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                    Edit
                                </button>
                                
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Hapus mata pelajaran ini dari Kelas <?= htmlspecialchars($nama_k); ?>?');">
                                    <input type="hidden" name="id" value="<?= $m['id']; ?>">
                                    <button type="submit" 
                                            name="hapus_mapel" 
                                            style="background-color: #e74c3c; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 16px; color: #64748b;">Belum ada data mata pelajaran untuk Kelas <?= htmlspecialchars($nama_k); ?>.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Tabel Khusus Data Lama yang Belum Punya Kelas (Jika Ada) -->
    <?php if (count($mapel_tanpa_kelas) > 0): ?>
    <div style="margin-bottom: 30px;">
        <h3 style="color: #b45309; margin-bottom: 10px;">
            Mata Pelajaran Belum Ditetapkan Kelasnya (Silakan klik Edit untuk memilih Kelas)
        </h3>
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden;">
                <thead>
                    <tr style="background-color: #92400e; color: white; text-align: left;">
                        <th style="padding: 12px; width: 50px;">No</th>
                        <th style="padding: 12px;">Kode</th>
                        <th style="padding: 12px;">Nama Mata Pelajaran</th>
                        <th style="padding: 12px;">Lecturer</th>
                        <th style="padding: 12px;">Waktu</th>
                        <th style="padding: 12px;">Schedule</th>
                        <th style="padding: 12px;">Room</th>
                        <th style="padding: 12px;">Max Abs</th>
                        <th style="padding: 12px; width: 150px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($mapel_tanpa_kelas as $m): ?>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 12px;"><?= $no++; ?></td>
                        <td style="padding: 12px;"><b><?= htmlspecialchars($m['kode_mapel']); ?></b></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['nama_mapel']); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['nama_guru'] ?? 'Belum Diatur'); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['sks'] ?? '-'); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['jadwal'] ?? '-'); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['ruangan'] ?? '-'); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['max_absent'] ?? '3'); ?></td>
                        <td style="padding: 12px;">
                            <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                <button type="button" 
                                        onclick="bukaModalEdit(<?= $m['id']; ?>, '', '<?= addslashes(htmlspecialchars($m['kode_mapel'])); ?>', '<?= addslashes(htmlspecialchars($m['nama_mapel'])); ?>', '<?= $m['guru_id']; ?>', '<?= addslashes(htmlspecialchars($m['sks'] ?? '')); ?>', '<?= addslashes(htmlspecialchars($m['jadwal'] ?? '')); ?>', '<?= addslashes(htmlspecialchars($m['ruangan'] ?? '')); ?>', '<?= $m['max_absent'] ?? 3; ?>')" 
                                        style="background-color: #e67e22; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                    Edit
                                </button>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Hapus mata pelajaran ini?');">
                                    <input type="hidden" name="id" value="<?= $m['id']; ?>">
                                    <button type="submit" name="hapus_mapel" style="background-color: #e74c3c; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Modal Edit Mata Pelajaran -->
<div id="modalEdit" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
    <div class="card" style="width: 100%; max-width: 550px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0; color: #1b365d;">Edit Mata Pelajaran</h3>
        <form method="POST" style="margin-top: 15px;">
            <input type="hidden" name="id" id="edit_id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label for="edit_kelas_id">Kelas</label>
                    <select name="kelas_id" id="edit_kelas_id" class="form-control" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                            <option value="<?= $k['id']; ?>"><?= htmlspecialchars($k['nama_kelas']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_kode_mapel">Kode Mapel</label>
                    <input type="text" name="kode_mapel" id="edit_kode_mapel" class="form-control" required autocomplete="off">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label for="edit_nama_mapel">Nama Mata Pelajaran</label>
                    <input type="text" name="nama_mapel" id="edit_nama_mapel" class="form-control" required autocomplete="off">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label for="edit_guru_id">Guru / Lecturer</label>
                    <select name="guru_id" id="edit_guru_id" class="form-control">
                        <option value="">-- Pilih Guru --</option>
                        <?php 
                        if ($gurus && $gurus->num_rows > 0) {
                            $gurus->data_seek(0);
                            while ($g = $gurus->fetch_assoc()): 
                        ?>
                            <option value="<?= $g['id']; ?>"><?= htmlspecialchars($g['nama_lengkap']); ?></option>
                        <?php 
                            endwhile; 
                        } 
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_waktu">Waktu</label>
                    <input type="text" name="waktu" id="edit_waktu" class="form-control" placeholder="Contoh: 09.00 - 10.20" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="edit_max_absent">Max Absent</label>
                    <input type="number" name="max_absent" id="edit_max_absent" class="form-control">
                </div>
                <div class="form-group">
                    <label for="edit_jadwal">Schedule</label>
                    <input type="text" name="jadwal" id="edit_jadwal" class="form-control">
                </div>
                <div class="form-group">
                    <label for="edit_ruangan">Room</label>
                    <input type="text" name="ruangan" id="edit_ruangan" class="form-control">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn" onclick="tutupModalEdit()" style="background-color: #7f8c8d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Batal</button>
                <button type="submit" name="edit_mapel" class="btn" style="background-color: #e67e22; color: white; border: none; padding: 8px 16px; border-radius: 4px; font-weight: 600; cursor: pointer;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalEdit(id, kelasId, kode, nama, guruId, waktu, jadwal, ruangan, maxAbsent) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_kelas_id').value = kelasId ? kelasId : '';
    document.getElementById('edit_kode_mapel').value = kode;
    document.getElementById('edit_nama_mapel').value = nama;
    document.getElementById('edit_guru_id').value = guruId ? guruId : '';
    document.getElementById('edit_waktu').value = waktu;
    document.getElementById('edit_jadwal').value = jadwal;
    document.getElementById('edit_ruangan').value = ruangan;
    document.getElementById('edit_max_absent').value = maxAbsent;
    
    document.getElementById('modalEdit').style.display = 'flex';
}

function tutupModalEdit() {
    document.getElementById('modalEdit').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>