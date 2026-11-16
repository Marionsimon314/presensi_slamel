<?php
session_start();
require_once '../config/database.php';

// Memastikan hanya Siswa yang dapat mengakses
check_access(['siswa']);

$user_id = $_SESSION['user_id'];

// Ambil data siswa dan kelasnya dari database
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

$siswa_id   = $siswa['id'];
$kelas_id   = $siswa['kelas_id'];
$nama_kelas = $siswa['nama_kelas'] ?? 'Kelas';

// Cek tabel mata_pelajaran jika ada
$mapel_list = [];
$check_mapel = $conn->query("SHOW TABLES LIKE 'mata_pelajaran'");

if ($check_mapel && $check_mapel->num_rows > 0) {
    $res_m = $conn->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel ASC");
    while ($m = $res_m->fetch_assoc()) {
        $mapel_list[] = $m;
    }
}

// Jika tidak ada data mata pelajaran spesifik, gunakan fallback per Kelas
if (empty($mapel_list)) {
    $mapel_list[] = [
        'id'         => 1,
        'kode_mapel' => 'KLS-' . strtoupper($nama_kelas),
        'nama_mapel' => 'Presensi Harian ' . $nama_kelas
    ];
}

$mapel_stat = [];

foreach ($mapel_list as $mapel) {
    // Ambil seluruh catatan presensi milik siswa
    $stmt_p = $conn->prepare("
        SELECT tanggal, status, keterangan 
        FROM presensi 
        WHERE siswa_id = ? 
        ORDER BY tanggal DESC
    ");
    $stmt_p->bind_param("i", $siswa_id);
    $stmt_p->execute();
    $presensi_rows = $stmt_p->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_p->close();

    $total_meeting = count($presensi_rows);
    $present       = 0;
    $late          = 0;
    $excused       = 0; // Sakit / Izin
    $unexcused     = 0; // Alpa
    $logs          = [];

    foreach ($presensi_rows as $row) {
        $st = strtolower($row['status']);
        if ($st === 'hadir') {
            $present++;
        } elseif ($st === 'terlambat' || $st === 'late') {
            $late++;
            $logs[] = $row;
        } elseif ($st === 'sakit' || $st === 'izin') {
            $excused++;
            $logs[] = $row;
        } elseif ($st === 'alpa' || $st === 'absen') {
            $unexcused++;
            $logs[] = $row;
        }
    }

    // Kalkulasi aturan "3x Late = 1 Alpa"
    $effective_unexcused = $unexcused + floor($late / 3);
    $total_absence = $excused + $effective_unexcused;

    $mapel_stat[] = [
        'id'            => $mapel['id'],
        'nama_mapel'    => $mapel['nama_mapel'],
        'kode_mapel'    => $mapel['kode_mapel'] ?? 'MP-' . $mapel['id'],
        'total_meeting' => $total_meeting,
        'present'       => $present,
        'late'          => $late,
        'excused'       => $excused,
        'unexcused'     => $unexcused,
        'total_absence' => $total_absence,
        'logs'          => $logs
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Statistics - Presensi SLA Mebali</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .layout-container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 30px; }

        .tab-header { display: flex; justify-content: center; margin-bottom: 25px; border-bottom: 1px solid #cbd5e1; }
        .tab-btn { padding: 10px 30px; font-weight: 600; color: #0284c7; text-decoration: none; border-bottom: 2px solid #0284c7; }

        .stat-card {
            background: #ffffff;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin: 0 auto 30px auto;
            max-width: 750px;
            overflow: hidden;
        }
        .stat-card-header {
            background: #64748b;
            color: #ffffff;
            text-align: center;
            padding: 12px 15px;
            font-weight: 600;
            font-size: 1.05rem;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 25px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.95rem;
        }
        .stat-row.bold {
            font-weight: bold;
            color: #1e293b;
        }
        .stat-label { color: #334155; }
        .stat-value { font-weight: 600; color: #0f172a; }

        .stat-card-footer {
            padding: 15px;
            background: #ffffff;
            text-align: center;
        }
        .btn-detail {
            display: inline-block;
            width: 100%;
            padding: 10px 0;
            background-color: #06b6d4;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-detail:hover { background-color: #0891b2; }

        /* Modal Log Detail */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 999; 
            left: 0; top: 0; 
            width: 100%; height: 100%; 
            background-color: rgba(0,0,0,0.5); 
            justify-content: center; 
            align-items: center;
        }
        .modal-content {
            background: white;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .modal-header {
            background: #64748b;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        .log-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .log-info { display: flex; flex-direction: column; }
        .log-title { font-weight: 600; color: #1e293b; font-size: 0.95rem; }
        .log-date { font-size: 0.85rem; color: #64748b; }
        .badge-status { font-weight: 600; font-size: 0.9rem; }
        .status-absen { color: #dc2626; }
        .status-late { color: #d97706; }
        .status-excused { color: #2563eb; }
    </style>
</head>
<body>
<div class="layout-container">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="tab-header">
            <a href="#" class="tab-btn">Class Statistics</a>
        </div>

        <?php foreach ($mapel_stat as $stat): ?>
            <div class="stat-card">
                <div class="stat-card-header">
                    [<?= htmlspecialchars($stat['kode_mapel']); ?>] <?= htmlspecialchars($stat['nama_mapel']); ?>
                </div>
                <div class="stat-card-body">
                    <div class="stat-row bold">
                        <span class="stat-label">Total Meeting</span>
                        <span class="stat-value"><?= $stat['total_meeting']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Present</span>
                        <span class="stat-value"><?= $stat['present']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Late</span>
                        <span class="stat-value"><?= $stat['late']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Excused Absence</span>
                        <span class="stat-value"><?= $stat['excused']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Unexcused Absence</span>
                        <span class="stat-value"><?= $stat['unexcused']; ?></span>
                    </div>
                    <div class="stat-row bold" style="background-color: #f8fafc; border-top: 1px solid #e2e8f0;">
                        <span class="stat-label">Total Absence Class</span>
                        <span class="stat-value"><?= $stat['total_absence']; ?></span>
                    </div>
                </div>
                <div class="stat-card-footer">
                    <button class="btn-detail" onclick='showLogModal(<?= json_encode($stat['nama_mapel']); ?>, <?= json_encode($stat['logs']); ?>)'>
                        View Detail Attendance Log
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </main>
</div>

<!-- Modal Popup Log Detail -->
<div class="modal" id="logModal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="modalMapelTitle" style="margin: 0;">Detail Log Attendance</h4>
            <span style="cursor: pointer; font-size: 1.2rem; font-weight: bold;" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="modalLogList"></div>
    </div>
</div>

<script>
    function showLogModal(mapelName, logs) {
        document.getElementById('modalMapelTitle').textContent = mapelName;
        const container = document.getElementById('modalLogList');
        container.innerHTML = '';

        if (!logs || logs.length === 0) {
            container.innerHTML = '<div style="text-align:center; color:#64748b; padding:15px;">Tidak ada catatan ketidakhadiran/keterlambatan.</div>';
        } else {
            logs.forEach(item => {
                let statusClass = 'status-absen';
                let statusText = 'Absen';

                const st = item.status.toLowerCase();
                if (st === 'terlambat' || st === 'late') {
                    statusClass = 'status-late';
                    statusText = 'Late';
                } else if (st === 'sakit' || st === 'izin') {
                    statusClass = 'status-excused';
                    statusText = ucfirst(st);
                }

                const dateFormatted = new Date(item.tanggal).toLocaleDateString('id-ID', {
                    day: '2-digit', month: 'long', year: 'numeric'
                });

                const logItem = `
                    <div class="log-item">
                        <div class="log-info">
                            <span class="log-title">${item.keterangan ? item.keterangan : 'Presensi Kelas'}</span>
                            <span class="log-date">${dateFormatted}</span>
                        </div>
                        <span class="badge-status ${statusClass}">${statusText}</span>
                    </div>
                `;
                container.innerHTML += logItem;
            });
        }

        document.getElementById('logModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('logModal').style.display = 'none';
    }

    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    window.onclick = function(event) {
        const modal = document.getElementById('logModal');
        if (event.target === modal) {
            closeModal();
        }
    }
</script>
</body>
</html>