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

// Get summary statistics
$summary_sql = "SELECT 
                    COUNT(*) as total_records,
                    SUM(CASE WHEN a.status_masuk = 'hadir' THEN 1 ELSE 0 END) as total_hadir,
                    SUM(CASE WHEN a.status_masuk = 'terlambat' THEN 1 ELSE 0 END) as total_terlambat,
                    SUM(CASE WHEN a.status_masuk = 'izin' THEN 1 ELSE 0 END) as total_izin,
                    SUM(CASE WHEN a.status_masuk = 'alpha' THEN 1 ELSE 0 END) as total_alpha
                FROM attendance a 
                JOIN teachers t ON a.teacher_id = t.id 
                $where_clause";

if(!empty($params)){
    $summary_stmt = $mysqli->prepare($summary_sql);
    $summary_stmt->bind_param($types, ...$params);
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();
} else {
    $summary_result = $mysqli->query($summary_sql);
}

$summary = $summary_result->fetch_assoc();

// Create HTML content for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header h2 { margin: 5px 0; font-size: 14px; }
        .summary { margin-bottom: 20px; }
        .summary table { width: 100%; border-collapse: collapse; }
        .summary th, .summary td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        .summary th { background-color: #f0f0f0; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .data-table th { background-color: #f0f0f0; font-weight: bold; }
        .status-hadir { color: green; }
        .status-terlambat { color: orange; }
        .status-izin { color: blue; }
        .status-alpha { color: red; }
    </style>
</head>
<body>
    <div class="header">
        <h1>LAPORAN ABSENSI</h1>
        <h2>TK PELANGI</h2>
        <p>Periode: ' . date('F Y', strtotime($month . '-01')) . '</p>
        <p>Tanggal Cetak: ' . date('d F Y') . '</p>
    </div>
    
    <div class="summary">
        <h3>Ringkasan</h3>
        <table>
            <tr>
                <th>Total Record</th>
                <th>Hadir</th>
                <th>Terlambat</th>
                <th>Izin</th>
                <th>Alpha</th>
            </tr>
            <tr>
                <td>' . $summary['total_records'] . '</td>
                <td>' . $summary['total_hadir'] . '</td>
                <td>' . $summary['total_terlambat'] . '</td>
                <td>' . $summary['total_izin'] . '</td>
                <td>' . $summary['total_alpha'] . '</td>
            </tr>
        </table>
    </div>
    
    <h3>Detail Absensi</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>NIP</th>
                <th>Nama Guru</th>
                <th>Waktu Masuk</th>
                <th>Waktu Pulang</th>
                <th>Status Masuk</th>
                <th>Status Pulang</th>
            </tr>
        </thead>
        <tbody>';

if($attendance_result->num_rows > 0){
    while($row = $attendance_result->fetch_assoc()){
        $status_class = 'status-' . $row['status_masuk'];
        $html .= '<tr>
                    <td>' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>
                    <td>' . $row['nip'] . '</td>
                    <td>' . $row['nama'] . '</td>
                    <td>' . ($row['waktu_masuk'] ? $row['waktu_masuk'] : '-') . '</td>
                    <td>' . ($row['waktu_pulang'] ? $row['waktu_pulang'] : '-') . '</td>
                    <td class="' . $status_class . '">' . ucfirst($row['status_masuk']) . '</td>
                    <td>' . ($row['status_pulang'] == 'pulang' ? 'Pulang' : 'Belum Pulang') . '</td>
                  </tr>';
    }
} else {
    $html .= '<tr><td colspan="7" style="text-align: center;">Tidak ada data absensi</td></tr>';
}

$html .= '
        </tbody>
    </table>
</body>
</html>';

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment;filename="Laporan_Absensi_' . date('Y-m-d') . '.pdf"');
header('Cache-Control: max-age=0');

// For this demo, we'll output HTML that can be converted to PDF
// In a real implementation, you would use a library like TCPDF or mPDF
echo $html;
?>

