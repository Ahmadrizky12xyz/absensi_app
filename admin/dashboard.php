<?php
session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: ../login.php");
    exit;
}

require_once "../config.php";

// Ambil statistik
$total_guru = $mysqli->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
$hadir_hari_ini = $mysqli->query("SELECT COUNT(*) as count FROM attendance WHERE tanggal = CURDATE() AND status_masuk = 'hadir'")->fetch_assoc()['count'];
$terlambat_hari_ini = $mysqli->query("SELECT COUNT(*) as count FROM attendance WHERE tanggal = CURDATE() AND status_masuk = 'terlambat'")->fetch_assoc()['count'];
$alpha_hari_ini = $total_guru - $hadir_hari_ini - $terlambat_hari_ini;

// Fungsi untuk mengubah nama bulan ke bahasa Indonesia
function getIndonesianMonth($month) {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $months[$month];
}

// Format tanggal dan waktu
$date = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$formatted_date = $date->format('d ') . getIndonesianMonth($date->format('n')) . $date->format(' Y');
$formatted_time = $date->format('H:i:s');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - TK Pelangi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f1f3f5;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #333;
        }
        .sidebar {
            background: #1a2a44;
            min-height: 100vh;
            color: #fff;
            padding: 1.5rem;
        }
        .sidebar .nav-link {
            color: #e0e6ed;
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            margin: 0.3rem 0;
            transition: background 0.3s ease, transform 0.2s ease;
            font-size: 1rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #2a3b5a;
            color: #fff;
            transform: translateX(5px);
        }
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }
        .main-content {
            padding: 2.5rem;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: box-shadow 0.3s ease, transform 0.3s ease;
            background: #fff;
        }
        .card:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
            transform: translateY(-3px);
        }
        .stat-card {
            border-left: 5px solid #007bff;
        }
        .stat-card-success {
            border-left: 5px solid #28a745;
        }
        .stat-card-warning {
            border-left: 5px solid #ffc107;
        }
        .stat-card-danger {
            border-left: 5px solid #dc3545;
        }
        .stat-card .card-body {
            padding: 1.75rem;
        }
        .stat-card i {
            color: #6c757d;
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }
        .stat-card h3 {
            color: #2c3e50;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .stat-card p {
            color: #6c757d;
            font-size: 1rem;
            margin: 0;
        }
        .table {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .table th, .table td {
            padding: 1rem;
            vertical-align: middle;
        }
        .table thead {
            background: #f8f9fa;
        }
        .btn-primary, .btn-success, .btn-info {
            border-radius: 8px;
            padding: 0.75rem 1.25rem;
            font-size: 1rem;
            transition: transform 0.2s ease;
        }
        .btn-primary:hover, .btn-success:hover, .btn-info:hover {
            transform: translateY(-2px);
        }
        .sidebar-brand {
            color: #fff;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        .sidebar-subtext {
            color: #b2bec3;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .date-time {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
        }
        .logo {
            width: 70px;
            height: 70px;
            margin: 0 auto 1rem;
            background: linear-gradient(45deg, #FFD700, #FFA500);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #fff;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        h2 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            padding: 1rem 1.5rem;
        }
        .card-header h5 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="text-center mb-4">
                    <div class="logo">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h4 class="sidebar-brand">TK Pelangi</h4>
                    <small class="sidebar-subtext">Panel Admin</small>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="teachers.php">
                        <i class="fas fa-users me-2"></i>Manajemen Guru
                    </a>
                    <a class="nav-link" href="attendance.php">
                        <i class="fas fa-calendar-check me-2"></i>Absensi
                    </a>
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i>Laporan
                    </a>
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Dashboard Admin</h2>
                    <div class="text-muted date-time">
                        <i class="fas fa-calendar me-2"></i>
                        <?php echo $formatted_date; ?>
                        <i class="fas fa-clock me-2"></i>
                        <?php echo $formatted_time; ?>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-3"></i>
                                <h3><?php echo $total_guru; ?></h3>
                                <p>Total Guru</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card-success">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-3"></i>
                                <h3><?php echo $hadir_hari_ini; ?></h3>
                                <p>Hadir Hari Ini</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card-warning">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-3"></i>
                                <h3><?php echo $terlambat_hari_ini; ?></h3>
                                <p>Terlambat</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card-danger">
                            <div class="card-body text-center">
                                <i class="fas fa-times-circle fa-2x mb-3"></i>
                                <h3><?php echo $alpha_hari_ini; ?></h3>
                                <p>Alpha</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Absensi Hari Ini</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $sql = "SELECT t.nama, a.waktu_masuk, a.status_masuk 
                                        FROM attendance a 
                                        JOIN teachers t ON a.teacher_id = t.id 
                                        WHERE a.tanggal = CURDATE() 
                                        ORDER BY a.waktu_masuk DESC";
                                $result = $mysqli->query($sql);
                                
                                if($result->num_rows > 0) {
                                    echo '<div class="table-responsive">';
                                    echo '<table class="table table-hover">';
                                    echo '<thead><tr><th>Nama Guru</th><th>Waktu Masuk</th><th>Status</th></tr></thead>';
                                    echo '<tbody>';
                                    
                                    while($row = $result->fetch_assoc()) {
                                        $badge_class = $row['status_masuk'] == 'hadir' ? 'bg-success' : 'bg-warning';
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['waktu_masuk']) . '</td>';
                                        echo '<td><span class="badge ' . $badge_class . '">' . ucfirst(htmlspecialchars($row['status_masuk'])) . '</span></td>';
                                        echo '</tr>';
                                    }
                                    
                                    echo '</tbody></table>';
                                    echo '</div>';
                                } else {
                                    echo '<p class="text-muted">Belum ada absensi hari ini.</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Menu Cepat</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="teachers.php" class="btn btn-primary">
                                        <i class="fas fa-user-plus me-2"></i>Tambah Guru
                                    </a>
                                    <a href="attendance.php" class="btn btn-success">
                                        <i class="fas fa-camera me-2"></i>Absensi Wajah
                                    </a>
                                    <a href="reports.php" class="btn btn-info">
                                        <i class="fas fa-download me-2"></i>Ekspor Laporan
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>