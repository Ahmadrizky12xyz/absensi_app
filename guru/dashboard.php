<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "guru") {
    header("location: ../login.php");
    exit;
}

require_once "../config.php";

// Inisialisasi variabel error
$error_msg = "";

// Ambil data guru
$teacher_sql = "SELECT * FROM teachers WHERE user_id = ?";
$teacher_stmt = $mysqli->prepare($teacher_sql);
$teacher_stmt->bind_param("i", $_SESSION["id"]);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();
$teacher_stmt->close();

// Periksa apakah data guru ditemukan
if (!$teacher) {
    $error_msg = "Data guru tidak ditemukan untuk pengguna ini. Silakan hubungi administrator.";
    $hadir_bulan_ini = $terlambat_bulan_ini = $izin_bulan_ini = 0;
    $status_hari_ini = "Belum Absen";
} else {
    // Ambil statistik absensi bulan ini
    $bulan_ini = date('Y-m');
    $hadir_sql = "SELECT COUNT(*) as count FROM attendance WHERE teacher_id = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ? AND status_masuk = 'hadir'";
    $hadir_stmt = $mysqli->prepare($hadir_sql);
    $hadir_stmt->bind_param("is", $teacher['id'], $bulan_ini);
    $hadir_stmt->execute();
    $hadir_bulan_ini = $hadir_stmt->get_result()->fetch_assoc()['count'];
    $hadir_stmt->close();

    $terlambat_sql = "SELECT COUNT(*) as count FROM attendance WHERE teacher_id = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ? AND status_masuk = 'terlambat'";
    $terlambat_stmt = $mysqli->prepare($terlambat_sql);
    $terlambat_stmt->bind_param("is", $teacher['id'], $bulan_ini);
    $terlambat_stmt->execute();
    $terlambat_bulan_ini = $terlambat_stmt->get_result()->fetch_assoc()['count'];
    $terlambat_stmt->close();

    $izin_sql = "SELECT COUNT(*) as count FROM attendance WHERE teacher_id = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ? AND status_masuk = 'izin'";
    $izin_stmt = $mysqli->prepare($izin_sql);
    $izin_stmt->bind_param("is", $teacher['id'], $bulan_ini);
    $izin_stmt->execute();
    $izin_bulan_ini = $izin_stmt->get_result()->fetch_assoc()['count'];
    $izin_stmt->close();

    // Cek status hari ini
    $status_hari_ini = "Belum Absen";
    $absen_sql = "SELECT * FROM attendance WHERE teacher_id = ? AND tanggal = CURDATE()";
    $absen_stmt = $mysqli->prepare($absen_sql);
    $absen_stmt->bind_param("i", $teacher['id']);
    $absen_stmt->execute();
    $absen_hari_ini = $absen_stmt->get_result()->fetch_assoc();
    if ($absen_hari_ini) {
        $status_hari_ini = ucfirst($absen_hari_ini['status_masuk']);
    }
    $absen_stmt->close();
}

// Format tanggal dan waktu
$date = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$formatted_date = $date->format('d ') . getIndonesianMonth($date->format('n')) . $date->format(' Y');
$formatted_time = $date->format('H:i:s');

// Fungsi untuk mengubah nama bulan ke bahasa Indonesia
function getIndonesianMonth($month) {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $months[$month];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - TK Pelangi</title>
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
        .btn-primary, .btn-light {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            transition: transform 0.2s ease;
        }
        .btn-primary:hover, .btn-light:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: #007bff;
            border: none;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-light {
            background: #f8f9fa;
            border: 1px solid #ced4da;
        }
        .btn-light:hover {
            background: #e9ecef;
        }
        .stat-card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-card-success {
            background: #28a745;
        }
        .stat-card-warning {
            background: #ffc107;
        }
        .stat-card-info {
            background: #17a2b8;
        }
        .stat-card-primary {
            background: #007bff;
        }
        .stat-card .card-body {
            text-align: center;
            color: #fff;
            padding: 1.5rem;
        }
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .stat-card h3 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        .stat-card p {
            font-size: 0.9rem;
            margin: 0;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
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
                    <small class="sidebar-subtext">Panel Guru</small>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link" href="attendance.php"><i class="fas fa-camera me-2"></i>Absensi Wajah</a>
                    <a class="nav-link" href="history.php"><i class="fas fa-history me-2"></i>Riwayat Absensi</a>
                    <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profil</a>
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Selamat Datang, <?php echo htmlspecialchars(isset($teacher['nama']) ? $teacher['nama'] : 'Guru'); ?></h2>
                    <div class="text-muted date-time">
                        <i class="fas fa-calendar me-2"></i>
                        <?php echo htmlspecialchars($formatted_date); ?>
                        <i class="fas fa-clock me-2"></i>
                        <?php echo htmlspecialchars($formatted_time); ?>
                    </div>
                </div>
                
                <!-- Status Hari Ini -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card stat-card-primary">
                            <div class="card-body text-center">
                                <h4>Status Absensi Hari Ini</h4>
                                <h2 class="mb-3">
                                    <?php 
                                    $badge_class = "bg-warning";
                                    if ($status_hari_ini == "Hadir") $badge_class = "bg-success";
                                    elseif ($status_hari_ini == "Terlambat") $badge_class = "bg-warning";
                                    elseif ($status_hari_ini == "Izin") $badge_class = "bg-info";
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?> fs-5"><?php echo htmlspecialchars($status_hari_ini); ?></span>
                                </h2>
                                <?php if ($status_hari_ini == "Belum Absen"): ?>
                                <a href="attendance.php" class="btn btn-light btn-lg">
                                    <i class="fas fa-camera me-2"></i>Absen Sekarang
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card-success">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-3"></i>
                                <h3><?php echo isset($hadir_bulan_ini) ? $hadir_bulan_ini : 0; ?></h3>
                                <p class="mb-0">Hadir Bulan Ini</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card-warning">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-3"></i>
                                <h3><?php echo isset($terlambat_bulan_ini) ? $terlambat_bulan_ini : 0; ?></h3>
                                <p class="mb-0">Terlambat</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card-info">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-times fa-2x mb-3"></i>
                                <h3><?php echo isset($izin_bulan_ini) ? $izin_bulan_ini : 0; ?></h3>
                                <p class="mb-0">Izin</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Riwayat Absensi Terbaru</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $sql = "SELECT tanggal, waktu_masuk, waktu_pulang, status_masuk, status_pulang 
                                        FROM attendance 
                                        WHERE teacher_id = ? 
                                        ORDER BY tanggal DESC 
                                        LIMIT 5";
                                $stmt = $mysqli->prepare($sql);
                                $stmt->bind_param("i", $teacher['id']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                if ($result->num_rows > 0) {
                                    echo '<div class="table-responsive">';
                                    echo '<table class="table table-hover" id="recentActivityTable">';
                                    echo '<thead><tr><th>Tanggal</th><th>NIP</th><th>Nama Guru</th><th>Waktu Masuk</th><th>Waktu Pulang</th><th>Status Masuk</th><th>Status Pulang</th></tr></thead>';
                                    echo '<tbody>';
                                    
                                    while ($row = $result->fetch_assoc()) {
                                        $badge_masuk = isset($row['status_masuk']) && $row['status_masuk'] == 'hadir' ? 'bg-success' : 
                                                      (isset($row['status_masuk']) && $row['status_masuk'] == 'terlambat' ? 'bg-warning' : 
                                                      (isset($row['status_masuk']) && $row['status_masuk'] == 'izin' ? 'bg-info' : 
                                                      (isset($row['status_masuk']) && $row['status_masuk'] == 'alpha' ? 'bg-danger' : 'bg-secondary')));
                                        $badge_pulang = isset($row['status_pulang']) && $row['status_pulang'] == 'tepat waktu' ? 'bg-success' : 
                                                       (isset($row['status_pulang']) && $row['status_pulang'] == 'pulang cepat' ? 'bg-warning' : 
                                                       (isset($row['status_pulang']) && $row['status_pulang'] == 'izin' ? 'bg-info' : 'bg-warning'));
                                        echo '<tr>';
                                        echo '<td>' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>';
                                        echo '<td>' . (isset($teacher['nip']) ? htmlspecialchars($teacher['nip']) : '-') . '</td>';
                                        echo '<td>' . (isset($teacher['nama']) ? htmlspecialchars($teacher['nama']) : '-') . '</td>';
                                        echo '<td>' . (isset($row['waktu_masuk']) && $row['waktu_masuk'] ? htmlspecialchars($row['waktu_masuk']) : '-') . '</td>';
                                        echo '<td>' . (isset($row['waktu_pulang']) && $row['waktu_pulang'] ? htmlspecialchars($row['waktu_pulang']) : '-') . '</td>';
                                        echo '<td><span class="badge ' . $badge_masuk . '">' . (isset($row['status_masuk']) ? ucfirst(htmlspecialchars($row['status_masuk'])) : '-') . '</span></td>';
                                        echo '<td><span class="badge ' . $badge_pulang . '">' . (isset($row['status_pulang']) && $row['status_pulang'] ? ucfirst(htmlspecialchars($row['status_pulang'])) : 'Belum Pulang') . '</span></td>';
                                        echo '</tr>';
                                    }
                                    
                                    echo '</tbody></table>';
                                    echo '</div>';
                                } else {
                                    echo '<p class="text-muted">Belum ada riwayat absensi.</p>';
                                }
                                $stmt->close();
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>