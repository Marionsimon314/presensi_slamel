<?php
session_start();
require_once '../config/database.php';

// Memastikan hanya Siswa yang dapat mengakses
check_access(['siswa']);

$user_id = $_SESSION['user_id'];

// Ambil data siswa dan kelas tempat siswa terdaftar
$stmt_siswa = $conn->prepare("
    SELECT s.id, s.nis, s.kelas_id, k.nama_kelas 
    FROM siswa s 
    LEFT JOIN kelas k ON s.kelas_id = k.id 
    WHERE s.user_id = ?
");
$stmt_siswa->bind_param("i", $user_id);
$stmt_siswa->execute();
$siswa = $stmt_siswa->get_result()->fetch_assoc();
$stmt_siswa->close();

if (!$siswa) {
    die("Data siswa tidak ditemukan.");
}

$kelas_id   = $siswa['kelas_id'];
$nama_kelas = $siswa['nama_kelas'] ?? 'Kelas';

$mapel_list = [];

// Cek keberadaan tabel mapel, mata_pelajaran, atau matapelajaran di database
$check_tb0 = $conn->query("SHOW TABLES LIKE 'mapel'");
$check_tb1 = $conn->query("SHOW TABLES LIKE 'matapelajaran'");
$check_tb2 = $conn->query("SHOW TABLES LIKE 'mata_pelajaran'");

$target_table = '';
if ($check_tb0 && $check_tb0->num_rows > 0) {
    $target_table = 'mapel';
} elseif ($check_tb1 && $check_tb1->num_rows > 0) {
    $target_table = 'matapelajaran';
} elseif ($check_tb2 && $check_tb2->num_rows > 0) {
    $target_table = 'mata_pelajaran';
}

if (!empty($target_table)) {
    // Ambil seluruh mata pelajaran beserta nama gurunya
    $query_mapel = "
        SELECT 
            m.*, 
            COALESCE(u.nama_lengkap, 'Belum Diatur') AS nama_guru 
        FROM {$target_table} m 
        LEFT JOIN users u ON m.guru_id = u.id 
        ORDER BY m.nama_mapel ASC
    ";
    $res_mapel = $conn->query($query_mapel);
    if ($res_mapel && $res_mapel->num_rows > 0) {
        while ($row = $res_mapel->fetch_assoc()) {
            $mapel_list[] = $row;
        }
    }
}

// Fallback jika belum ada data mata pelajaran yang diinput Admin
if (empty($mapel_list)) {
    $mapel_list[] = [
        'id'          => 1,
        'kode_mapel'  => 'KLS-' . strtoupper($nama_kelas),
        'nama_mapel'  => 'Presensi Harian ' . $nama_kelas,
        'nama_guru'   => 'Wali Kelas ' . $nama_kelas,
        'sks'         => '2 Jam',
        'jadwal'      => 'Senin - Jumat, 07.30 - 14.00',
        'ruangan'     => 'Ruang Kelas ' . $nama_kelas,
        'max_absent'  => '4'
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classes - Presensi SLA Mebali</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
        .layout-container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 30px; }

        /* Card Informasi Kelas gaya UNKLAB */
        .class-card {
            background: #ffffff;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin: 0 auto 30px auto;
            max-width: 750px;
            overflow: hidden;
        }
        .class-card-header {
            background: #64748b;
            color: #ffffff;
            text-align: center;
            padding: 14px 15px;
            font-weight: 600;
            font-size: 1.05rem;
        }
        .class-card-body {
            padding: 10px 0;
        }
        .class-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 25px;
            border-bottom: 1px solid #f8fafc;
            font-size: 0.95rem;
        }
        .class-row:last-child {
            border-bottom: none;
        }
        .class-label { 
            font-weight: 600; 
            color: #1e293b; 
        }
        .class-value { 
            color: #334155; 
            text-align: right;
        }
    </style>
</head>
<body>
<div class="layout-container">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <?php foreach ($mapel_list as $mapel): ?>
            <div class="class-card">
                <div class="class-card-header">
                    <?= htmlspecialchars($mapel['nama_mapel']); ?>
                </div>
                <div class="class-card-body">
                    <div class="class-row">
                        <span class="class-label">Code</span>
                        <span class="class-value"><?= htmlspecialchars($mapel['kode_mapel'] ?? ('MP-' . $mapel['id'])); ?></span>
                    </div>
                    <div class="class-row">
                        <span class="class-label">Lecturer</span>
                        <span class="class-value"><?= htmlspecialchars($mapel['nama_guru']); ?></span>
                    </div>
                    <div class="class-row">
                        <span class="class-label">Jam</span>
                        <span class="class-value"><?= htmlspecialchars($mapel['sks'] ?? '2 Jam'); ?></span>
                    </div>
                    <div class="class-row">
                        <span class="class-label">Schedule</span>
                        <span class="class-value"><?= htmlspecialchars($mapel['jadwal'] ?? 'Senin - Jumat'); ?></span>
                    </div>
                    <div class="class-row">
                        <span class="class-label">Room</span>
                        <span class="class-value"><?= htmlspecialchars($mapel['ruangan'] ?? ('Ruang Kelas ' . $nama_kelas)); ?></span>
                    </div>
                    <div class="class-row">
                        <span class="class-label">Max_Absent</span>
                        <span class="class-value"><?= htmlspecialchars($mapel['max_absent'] ?? '4'); ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </main>
</div>
</body>
</html>