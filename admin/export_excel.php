<?php
session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: ../login.php");
    exit;
}

require_once "../config.php";

// Filter parameters
$month = $_GET['month'] ?? date('Y-m');
$teacher_id = $_GET['teacher_id'] ?? '';

// Build query based on filters
$where_conditions = [];
$params = [];
$types = "";

if($month){
    $where_conditions[] = "DATE_FORMAT(a.tanggal, '%Y-%m') = ?";
    $params[] = $month;
    $types .= "s";
}

if($teacher_id){
    $where_conditions[] = "a.teacher_id = ?";
    $params[] = $teacher_id;
    $types .= "i";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get attendance data
$attendance_sql = "SELECT a.*, t.nama, t.nip 
                   FROM attendance a 
                   JOIN teachers t ON a.teacher_id = t.id 
                   $where_clause 
                   ORDER BY a.tanggal DESC, t.nama";

if(!empty($params)){
    $attendance_stmt = $mysqli->prepare($attendance_sql);
    $attendance_stmt->bind_param($types, ...$params);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();
} else {
    $attendance_result = $mysqli->query($attendance_sql);
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="Laporan_Absensi_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Start output
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
echo '<body>';

echo '<table border="1">';
echo '<tr>';
echo '<th colspan="7" style="text-align: center; font-size: 16px; font-weight: bold;">LAPORAN ABSENSI TK PELANGI</th>';
echo '</tr>';
echo '<tr>';
echo '<th colspan="7" style="text-align: center;">Periode: ' . date('F Y', strtotime($month . '-01')) . '</th>';
echo '</tr>';
echo '<tr><td colspan="7"></td></tr>'; // Empty row

echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
echo '<th>Tanggal</th>';
echo '<th>NIP</th>';
echo '<th>Nama Guru</th>';
echo '<th>Waktu Masuk</th>';
echo '<th>Waktu Pulang</th>';
echo '<th>Status Masuk</th>';
echo '<th>Status Pulang</th>';
echo '</tr>';

if($attendance_result->num_rows > 0){
    while($row = $attendance_result->fetch_assoc()){
        echo '<tr>';
        echo '<td>' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
        echo '<td>' . $row['nip'] . '</td>';
        echo '<td>' . $row['nama'] . '</td>';
        echo '<td>' . ($row['waktu_masuk'] ? $row['waktu_masuk'] : '-') . '</td>';
        echo '<td>' . ($row['waktu_pulang'] ? $row['waktu_pulang'] : '-') . '</td>';
        echo '<td>' . ucfirst($row['status_masuk']) . '</td>';
        echo '<td>' . ($row['status_pulang'] == 'pulang' ? 'Pulang' : 'Belum Pulang') . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="7" style="text-align: center;">Tidak ada data absensi</td></tr>';
}

echo '</table>';
echo '</body>';
echo '</html>';
?>

