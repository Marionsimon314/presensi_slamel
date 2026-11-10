<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_presensi_slamel"; // Nama database sesuai di phpMyAdmin

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

function check_access($allowed_roles = []) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: ../index.php");
        exit();
    }
}

function log_audit($conn, $user_id, $presensi_id, $tindakan, $rincian) {
    $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, presensi_id, tindakan, rincian_perubahan) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $presensi_id, $tindakan, $rincian);
    $stmt->execute();
    $stmt->close();
}

/**
 * Aturan Bisnis: 3x Terlambat = 1 Alpa
 * Menghitung akumulasi presensi siswa untuk status presensi yang sudah final (is_draft = 0)
 */
function hitungAkumulasiPresensiSiswa($siswa_id, $kelas_id, $mapel_id, $conn) {
    $siswa_id = intval($siswa_id);
    $kelas_id = intval($kelas_id);
    $mapel_id = intval($mapel_id);

    // Query menghitung presensi berdasarkan status yang sudah dikunci/final (is_draft = 0)
    $query = "SELECT status, COUNT(*) as total 
              FROM presensi 
              WHERE siswa_id = ? AND kelas_id = ? AND mapel_id = ? AND is_draft = 0 
              GROUP BY status";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $siswa_id, $kelas_id, $mapel_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rekap = [
        'Hadir'     => 0,
        'Terlambat' => 0,
        'Izin'      => 0,
        'Sakit'     => 0,
        'Alpa'      => 0
    ];

    while ($row = $result->fetch_assoc()) {
        $rekap[$row['status']] = intval($row['total']);
    }
    $stmt->close();

    // Perhitungan Logika Bisnis (3x Terlambat = +1 Alpa)
    $alpa_tambahan           = floor($rekap['Terlambat'] / 3);
    $rekap['Total_Alpa']     = $rekap['Alpa'] + $alpa_tambahan;
    $rekap['Sisa_Terlambat'] = $rekap['Terlambat'] % 3;

    return $rekap;
}
?>