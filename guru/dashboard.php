<?php
require_once '../config/database.php';
check_access(['guru']);

$user_id = $_SESSION['user_id'];
$guru = $conn->query("SELECT id FROM guru WHERE user_id = $user_id")->fetch_assoc();
$guru_id = $guru['id'];

// 1. Ambil daftar mata pelajaran yang ditugaskan Admin kepada guru ini (mapel.guru_id = users.id)
$stmt_mapel = $conn->prepare("
    SELECT m.*, k.nama_kelas 
    FROM mapel m 
    LEFT JOIN kelas k ON m.kelas_id = k.id 
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
        k.nama_kelas ASC,
        m.nama_mapel ASC
");
$stmt_mapel->bind_param("i", $user_id);
$stmt_mapel->execute();
$daftar_mengajar = $stmt_mapel->get_result();

// 2. Draf presensi yang belum difinalisasi
$drafts = $conn->query("SELECT p.tanggal, p.kelas_id, p.mapel_id, k.nama_kelas, m.nama_mapel, COUNT(p.id) as total_siswa 
                        FROM presensi p
                        JOIN kelas k ON p.kelas_id = k.id
                        JOIN mapel m ON p.mapel_id = m.id
                        WHERE p.guru_id = $guru_id AND p.is_draft = 1
                        GROUP BY p.tanggal, p.kelas_id, p.mapel_id");

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main-content">
    <div class="topbar">
        <h2>Dashboard Guru</h2>
        <span>Selamat datang, <b><?= htmlspecialchars($_SESSION['nama_lengkap']); ?></b></span>
    </div>

    <!-- Bagian 1: Mata Pelajaran yang Diampu Guru -->
    <div class="card" style="margin-bottom: 25px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05);">
        <h3 style="color: #1b365d; margin-bottom: 5px;">Jadwal & Mata Pelajaran yang Anda Ampu</h3>
        <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 15px;">Daftar mata pelajaran di bawah ini diatur oleh Administrator sebagai kelas yang akan Anda absenkan.</p>

        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #1b365d; color: white; text-align: left;">
                        <th style="padding: 12px; width: 50px;">No</th>
                        <th style="padding: 12px;">Kelas</th>
                        <th style="padding: 12px;">Kode</th>
                        <th style="padding: 12px;">Mata Pelajaran</th>
                        <th style="padding: 12px;">Hari (Schedule)</th>
                        <th style="padding: 12px;">Waktu</th>
                        <th style="padding: 12px;">Ruangan</th>
                        <th style="padding: 12px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($daftar_mengajar && $daftar_mengajar->num_rows > 0): 
                        $no = 1;
                        while ($m = $daftar_mengajar->fetch_assoc()): 
                    ?>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 12px;"><?= $no++; ?></td>
                        <td style="padding: 12px;">
                            <span style="background: #2563eb; color: white; padding: 3px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                                <?= htmlspecialchars($m['nama_kelas'] ?? 'Semua Kelas'); ?>
                            </span>
                        </td>
                        <td style="padding: 12px;"><b><?= htmlspecialchars($m['kode_mapel']); ?></b></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['nama_mapel']); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['jadwal'] ?? '-'); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['sks'] ?? '-'); ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($m['ruangan'] ?? '-'); ?></td>
                        <td style="padding: 12px; text-align: center;">
                            <?php if (!empty($m['kelas_id'])): ?>
                                <a href="input_presensi.php?kelas_id=<?= $m['kelas_id']; ?>&mapel_id=<?= $m['id']; ?>" 
                                   class="btn btn-primary btn-sm" 
                                   style="background-color: #16a34a; border: none; padding: 6px 14px; color: white; border-radius: 4px; text-decoration: none; font-weight: 600;">
                                    Catat Presensi
                                </a>
                            <?php else: ?>
                                <a href="input_presensi.php?mapel_id=<?= $m['id']; ?>" class="btn btn-primary btn-sm">Catat Presensi</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px; color: #64748b;">
                            Belum ada mata pelajaran yang ditugaskan kepada Anda oleh Administrator.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bagian 2: Draf Presensi -->
    <div class="card" style="padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05);">
        <h3 style="color: #1b365d; margin-bottom: 15px;">Draf Presensi Belum Final</h3>
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f1f5f9; color: #1e293b; text-align: left;">
                        <th style="padding: 12px;">Tanggal</th>
                        <th style="padding: 12px;">Kelas</th>
                        <th style="padding: 12px;">Mata Pelajaran</th>
                        <th style="padding: 12px;">Jumlah Siswa</th>
                        <th style="padding: 12px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($drafts && $drafts->num_rows > 0): ?>
                        <?php while ($d = $drafts->fetch_assoc()): ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 12px;"><?= $d['tanggal']; ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($d['nama_kelas']); ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($d['nama_mapel']); ?></td>
                            <td style="padding: 12px;"><?= $d['total_siswa']; ?> Siswa</td>
                            <td style="padding: 12px;">
                                <a href="input_presensi.php?kelas_id=<?= $d['kelas_id']; ?>&mapel_id=<?= $d['mapel_id']; ?>" class="btn btn-warning btn-sm">Lanjutkan Draf</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 15px; color: #64748b;">Tidak ada draf presensi menggantung.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>