<?php
session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "guru"){
    header("location: ../login.php");
    exit;
}

require_once "../config.php";

// Get teacher data
$teacher_sql = "SELECT * FROM teachers WHERE user_id = ?";
$teacher_stmt = $mysqli->prepare($teacher_sql);
$teacher_stmt->bind_param("i", $_SESSION["id"]);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();

// Filter parameters
$month = $_GET['month'] ?? date('Y-m');

// Get attendance history
$history_sql = "SELECT * FROM attendance 
                WHERE teacher_id = ? 
                AND DATE_FORMAT(tanggal, '%Y-%m') = ? 
                ORDER BY tanggal DESC";
$history_stmt = $mysqli->prepare($history_sql);
$history_stmt->bind_param("is", $teacher['id'], $month);
$history_stmt->execute();
$history_result = $history_stmt->get_result();

// Get monthly statistics
$stats_sql = "SELECT 
                COUNT(*) as total_hari,
                SUM(CASE WHEN status_masuk = 'hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN status_masuk = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
                SUM(CASE WHEN status_masuk = 'izin' THEN 1 ELSE 0 END) as izin
              FROM attendance 
              WHERE teacher_id = ? 
              AND DATE_FORMAT(tanggal, '%Y-%m') = ?";
$stats_stmt = $mysqli->prepare($stats_sql);
$stats_stmt->bind_param("is", $teacher['id'], $month);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="Absensi_' . $teacher['nama'] . '_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Start output
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
echo '<body>';

echo '<table border="1">';
echo '<tr>';
echo '<th colspan="6" style="text-align: center; font-size: 16px; font-weight: bold;">LAPORAN ABSENSI PERSONAL</th>';
echo '</tr>';
echo '<tr>';
echo '<th colspan="6" style="text-align: center;">TK PELANGI</th>';
echo '</tr>';
echo '<tr>';
echo '<th colspan="6" style="text-align: center;">Nama: ' . $teacher['nama'] . ' | NIP: ' . $teacher['nip'] . '</th>';
echo '</tr>';
echo '<tr>';
echo '<th colspan="6" style="text-align: center;">Periode: ' . date('F Y', strtotime($month . '-01')) . '</th>';
echo '</tr>';
echo '<tr><td colspan="6"></td></tr>'; // Empty row

// Summary
echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
echo '<th>Total Hari</th>';
echo '<th>Hadir</th>';
echo '<th>Terlambat</th>';
echo '<th>Izin</th>';
echo '<th colspan="2">Persentase Kehadiran</th>';
echo '</tr>';
echo '<tr>';
echo '<td>' . $stats['total_hari'] . '</td>';
echo '<td>' . $stats['hadir'] . '</td>';
echo '<td>' . $stats['terlambat'] . '</td>';
echo '<td>' . $stats['izin'] . '</td>';
$persentase = $stats['total_hari'] > 0 ? round(($stats['hadir'] + $stats['terlambat']) / $stats['total_hari'] * 100, 2) : 0;
echo '<td colspan="2">' . $persentase . '%</td>';
echo '</tr>';
echo '<tr><td colspan="6"></td></tr>'; // Empty row

echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
echo '<th>Tanggal</th>';
echo '<th>Hari</th>';
echo '<th>Waktu Masuk</th>';
echo '<th>Waktu Pulang</th>';
echo '<th>Status Masuk</th>';
echo '<th>Status Pulang</th>';
echo '</tr>';

if($history_result->num_rows > 0){
    while($row = $history_result->fetch_assoc()){
        echo '<tr>';
        echo '<td>' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
        echo '<td>' . date('l', strtotime($row['tanggal'])) . '</td>';
        echo '<td>' . ($row['waktu_masuk'] ? $row['waktu_masuk'] : '-') . '</td>';
        echo '<td>' . ($row['waktu_pulang'] ? $row['waktu_pulang'] : '-') . '</td>';
        echo '<td>' . ucfirst($row['status_masuk']) . '</td>';
        echo '<td>' . ($row['status_pulang'] == 'pulang' ? 'Pulang' : 'Belum Pulang') . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="6" style="text-align: center;">Tidak ada data absensi untuk bulan ini</td></tr>';
}

echo '</table>';
echo '</body>';
echo '</html>';
?>

