<?php
require_once '../config/database.php';
check_access(['orang_tua']);

$user_id = $_SESSION['user_id'];

// Ambil data anak dari tabel orang_tua dengan Prepared Statement aman
$stmt = $conn->prepare("SELECT ot.siswa_id, u.nama_lengkap as nama_anak, s.nis, k.nama_kelas 
                        FROM orang_tua ot 
                        JOIN siswa s ON ot.siswa_id = s.id 
                        JOIN users u ON s.user_id = u.id 
                        JOIN kelas k ON s.kelas_id = k.id 
                        WHERE ot.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ortu = $stmt->get_result()->fetch_assoc();
$stmt->close();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="main-content">
    <div class="topbar">
        <h2>Dashboard Orang Tua</h2>
        <?php if ($ortu): ?>
            <span>Pemantauan Anak: <b><?= htmlspecialchars($ortu['nama_anak']); ?></b> (<?= htmlspecialchars($ortu['nama_kelas']); ?>)</span>
        <?php endif; ?>
    </div>

    <?php if (!$ortu): ?>
        <div class="alert alert-danger" style="margin-top: 20px;">
            <b>Perhatian:</b> Akun Orang Tua Anda belum dihubungkan dengan data Siswa/Anak oleh Admin. Silakan hubungi Pihak Sekolah (TU).
        </div>
    <?php else: 
        $siswa_id = $ortu['siswa_id'];

        // Rekap Presensi
        $h = $conn->query("SELECT COUNT(*) as t FROM presensi WHERE siswa_id=$siswa_id AND status='Hadir' AND is_draft=0")->fetch_assoc()['t'];
        $t = $conn->query("SELECT COUNT(*) as t FROM presensi WHERE siswa_id=$siswa_id AND status='Terlambat' AND is_draft=0")->fetch_assoc()['t'];
        $i = $conn->query("SELECT COUNT(*) as t FROM presensi WHERE siswa_id=$siswa_id AND status='Izin' AND is_draft=0")->fetch_assoc()['t'];
        $s = $conn->query("SELECT COUNT(*) as t FROM presensi WHERE siswa_id=$siswa_id AND status='Sakit' AND is_draft=0")->fetch_assoc()['t'];
        $a_murni = $conn->query("SELECT COUNT(*) as t FROM presensi WHERE siswa_id=$siswa_id AND status='Alpa' AND is_draft=0")->fetch_assoc()['t'];

        // Aturan Bisnis: 3x Terlambat = +1 Alpa
        $alpa_tambahan = floor($t / 3);
        $total_alpa    = $a_murni + $alpa_tambahan;
    ?>

    <div class="cards-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 20px;">
        <div class="card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <div class="title" style="color: #64748b; font-size: 0.9rem;">Hadir</div>
            <div class="value" style="font-size: 1.8rem; font-weight: bold; color: #16a34a;"><?= $h; ?></div>
        </div>
        <div class="card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <div class="title" style="color: #64748b; font-size: 0.9rem;">Terlambat</div>
            <div class="value" style="font-size: 1.8rem; font-weight: bold; color: #d97706;"><?= $t; ?></div>
        </div>
        <div class="card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <div class="title" style="color: #64748b; font-size: 0.9rem;">Izin</div>
            <div class="value" style="font-size: 1.8rem; font-weight: bold; color: #2563eb;"><?= $i; ?></div>
        </div>
        <div class="card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <div class="title" style="color: #64748b; font-size: 0.9rem;">Sakit</div>
            <div class="value" style="font-size: 1.8rem; font-weight: bold; color: #0284c7;"><?= $s; ?></div>
        </div>
        <div class="card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <div class="title" style="color: #64748b; font-size: 0.9rem;">Total Alpa (Aturan 3x Late)</div>
            <div class="value" style="font-size: 1.8rem; font-weight: bold; color: #dc2626;"><?= $total_alpa; ?></div>
            <?php if ($t >= 3): ?>
                <small style="color: #dc2626; font-size: 11px;">(<?= $a_murni; ?> Alpa + <?= $alpa_tambahan; ?> dari <?= $t; ?>x Late)</small>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>