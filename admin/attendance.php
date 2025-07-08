<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config.php";

function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

$success_msg = $error_msg = "";

// Handle absensi manual
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['manual_attendance'])) {
    $teacher_id = sanitize_input($_POST['teacher_id']);
    $tanggal = sanitize_input($_POST['tanggal']);
    $waktu_masuk = !empty($_POST['waktu_masuk']) ? sanitize_input($_POST['waktu_masuk']) : null;
    $status_masuk = !empty($_POST['status_masuk']) ? sanitize_input($_POST['status_masuk']) : null;
    $waktu_pulang = !empty($_POST['waktu_pulang']) ? sanitize_input($_POST['waktu_pulang']) : null;
    $status_pulang = !empty($_POST['status_pulang']) ? sanitize_input($_POST['status_pulang']) : null;

    if (empty($teacher_id) || empty($tanggal)) {
        $error_msg = "Guru dan tanggal harus diisi!";
        error_log("Manual attendance failed: Teacher ID or date empty");
    } elseif ($waktu_pulang && $waktu_masuk && strtotime($waktu_pulang) <= strtotime($waktu_masuk)) {
        $error_msg = "Waktu pulang harus lebih besar dari waktu masuk!";
        error_log("Manual attendance failed: Invalid time range");
    } else {
        $check_sql = "SELECT id, waktu_masuk FROM attendance WHERE teacher_id = ? AND tanggal = ?";
        $check_stmt = $mysqli->prepare($check_sql);
        $check_stmt->bind_param("is", $teacher_id, $tanggal);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $row = $check_result->fetch_assoc();
            $update_sql = "UPDATE attendance SET waktu_masuk = ?, status_masuk = ?, waktu_pulang = ?, status_pulang = ? WHERE teacher_id = ? AND tanggal = ?";
            $update_stmt = $mysqli->prepare($update_sql);
            $update_stmt->bind_param("ssssis", $waktu_masuk, $status_masuk, $waktu_pulang, $status_pulang, $teacher_id, $tanggal);

            if ($update_stmt->execute()) {
                $success_msg = "Absensi berhasil diperbarui!";
                error_log("Manual attendance updated for teacher ID $teacher_id on $tanggal");
            } else {
                $error_msg = "Error: " . $update_stmt->error;
                error_log("Manual attendance update failed: " . $update_stmt->error);
            }
            $update_stmt->close();
        } else {
            if (!$waktu_masuk || !$status_masuk) {
                $error_msg = "Waktu masuk dan status masuk harus diisi untuk absensi baru!";
                error_log("Manual attendance failed: Missing waktu_masuk or status_masuk");
            } else {
                $insert_sql = "INSERT INTO attendance (teacher_id, tanggal, waktu_masuk, status_masuk, waktu_pulang, status_pulang) VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $mysqli->prepare($insert_sql);
                $insert_stmt->bind_param("isssss", $teacher_id, $tanggal, $waktu_masuk, $status_masuk, $waktu_pulang, $status_pulang);

                if ($insert_stmt->execute()) {
                    $success_msg = "Absensi berhasil ditambahkan!";
                    error_log("Manual attendance added for teacher ID $teacher_id on $tanggal");
                } else {
                    $error_msg = "Error: " . $insert_stmt->error;
                    error_log("Manual attendance insert failed: " . $insert_stmt->error);
                }
                $insert_stmt->close();
            }
        }
        $check_stmt->close();
    }
}

// Handle absensi wajah
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['face_form'])) {
    $face_descriptor = sanitize_input($_POST['face_data']);
    $is_checkout = isset($_POST['is_checkout']) && $_POST['is_checkout'] == '1';
    $recognized_teacher_id = isset($_POST['recognized_teacher_id']) ? sanitize_input($_POST['recognized_teacher_id']) : '';

    error_log("Face form submitted: face_data_length=" . strlen($face_descriptor) . ", is_checkout=$is_checkout, teacher_id=$recognized_teacher_id");

    if (empty($face_descriptor) || empty($recognized_teacher_id)) {
        $error_msg = "Data wajah atau ID guru tidak valid!";
        error_log("Face recognition failed: Invalid face_descriptor or teacher_id");
    } else {
        $teachers_sql = "SELECT id, nama, face_encoding FROM teachers WHERE id = ? AND face_encoding IS NOT NULL";
        $teachers_stmt = $mysqli->prepare($teachers_sql);
        $teachers_stmt->bind_param("i", $recognized_teacher_id);
        $teachers_stmt->execute();
        $teachers_result = $teachers_stmt->get_result();
        $recognized_teacher = $teachers_result->fetch_assoc();
        $teachers_stmt->close();

        if ($recognized_teacher) {
            $teacher_id = $recognized_teacher['id'];
            $teacher_name = $recognized_teacher['nama'];
            $tanggal = date('Y-m-d');
            $current_time = date('H:i:s');

            error_log("Recognized teacher: ID=$teacher_id, Name=$teacher_name");

            $check_sql = "SELECT id, waktu_masuk, waktu_pulang FROM attendance WHERE teacher_id = ? AND tanggal = ?";
            $check_stmt = $mysqli->prepare($check_sql);
            $check_stmt->bind_param("is", $teacher_id, $tanggal);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $attendance = $check_result->fetch_assoc();
                if ($is_checkout) {
                    if ($attendance['waktu_pulang']) {
                        $error_msg = "Guru sudah absen pulang hari ini!";
                        error_log("Checkout failed: Already checked out for teacher ID $teacher_id");
                    } elseif (!$attendance['waktu_masuk']) {
                        $error_msg = "Absensi masuk belum dilakukan!";
                        error_log("Checkout failed: No check-in for teacher ID $teacher_id");
                    } else {
                        $status_pulang = ($current_time < '15:00:00') ? 'pulang cepat' : 'tepat waktu';
                        $update_sql = "UPDATE attendance SET waktu_pulang = ?, status_pulang = ? WHERE id = ?";
                        $update_stmt = $mysqli->prepare($update_sql);
                        $update_stmt->bind_param("ssi", $current_time, $status_pulang, $attendance['id']);

                        if ($update_stmt->execute()) {
                            $success_msg = "Absensi pulang berhasil! Selamat, " . htmlspecialchars($teacher_name);
                            error_log("Checkout successful for teacher ID $teacher_id");
                        } else {
                            $error_msg = "Error: " . $update_stmt->error;
                            error_log("Checkout failed: " . $update_stmt->error);
                        }
                        $update_stmt->close();
                    }
                } else {
                    $error_msg = "Guru sudah absen masuk hari ini!";
                    error_log("Check-in failed: Already checked in for teacher ID $teacher_id");
                }
            } else if (!$is_checkout) {
                $status_masuk = ($current_time > '08:00:00') ? 'terlambat' : 'hadir';
                $insert_sql = "INSERT INTO attendance (teacher_id, tanggal, waktu_masuk, status_masuk) VALUES (?, ?, ?, ?)";
                $insert_stmt = $mysqli->prepare($insert_sql);
                $insert_stmt->bind_param("isss", $teacher_id, $tanggal, $current_time, $status_masuk);

                if ($insert_stmt->execute()) {
                    $success_msg = "Absensi masuk berhasil! Selamat datang, " . htmlspecialchars($teacher_name);
                    error_log("Check-in successful for teacher ID $teacher_id");
                } else {
                    $error_msg = "Error: " . $insert_stmt->error;
                    error_log("Check-in failed: " . $insert_stmt->error);
                }
                $insert_stmt->close();
            } else {
                $error_msg = "Belum ada absensi masuk untuk hari ini!";
                error_log("Checkout failed: No check-in record for teacher ID $teacher_id");
            }
            $check_stmt->close();
        } else {
            $error_msg = "Wajah tidak dikenali. Silakan hubungi admin.";
            error_log("Face recognition failed: No matching teacher found for ID $recognized_teacher_id");
        }
    }
}

// Ambil data absensi hari ini
$today_sql = "SELECT t.nama, a.waktu_masuk, a.status_masuk, a.waktu_pulang, a.status_pulang 
              FROM attendance a 
              JOIN teachers t ON a.teacher_id = t.id 
              WHERE a.tanggal = CURDATE() 
              ORDER BY a.waktu_masuk DESC";
$today_result = $mysqli->query($today_sql);

// Ambil daftar guru
$teachers_sql = "SELECT id, nama FROM teachers ORDER BY nama";
$teachers_result = $mysqli->query($teachers_sql);

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
    <title>Absensi - TK Pelangi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
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
        .btn-primary, .btn-success, .btn-danger {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            transition: transform 0.2s ease;
        }
        .btn-primary:hover, .btn-success:hover, .btn-danger:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: #007bff;
            border: none;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .camera-container {
            position: relative;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        #video {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
        .camera-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            border: 3px solid #fff;
            border-radius: 50%;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
        }
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            align-items: center;
            margin-top: 1rem;
        }
        .button-group .btn {
            flex: 1 1 auto;
            min-width: 120px;
            max-width: 160px;
            text-align: center;
            font-size: 0.9rem;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ced4da;
            box-shadow: none;
        }
        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
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
        @media (max-width: 576px) {
            .button-group .btn {
                min-width: 100%;
                margin-bottom: 10px;
            }
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
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link" href="teachers.php"><i class="fas fa-users me-2"></i>Manajemen Guru</a>
                    <a class="nav-link active" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Absensi</a>
                    <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Laporan</a>
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Sistem Absensi</h2>
                    <div class="text-muted date-time">
                        <i class="fas fa-calendar me-2"></i>
                        <?php echo htmlspecialchars($formatted_date); ?>
                        <i class="fas fa-clock me-2"></i>
                        <?php echo htmlspecialchars($formatted_time); ?>
                    </div>
                </div>

                <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Absensi Pengenalan Wajah -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-camera me-2"></i>Absensi Pengenalan Wajah</h5>
                            </div>
                            <div class="card-body">
                                <div class="camera-container mb-3">
                                    <video id="video" autoplay></video>
                                    <div class="camera-overlay"></div>
                                </div>
                                <div class="button-group">
                                    <button id="startCamera" class="btn btn-success"><i class="fas fa-video me-2"></i>Mulai Kamera</button>
                                    <button id="captureCheckIn" class="btn btn-primary" disabled><i class="fas fa-camera me-2"></i>Absen Masuk</button>
                                    <button id="captureCheckOut" class="btn btn-primary" disabled><i class="fas fa-camera me-2"></i>Absen Pulang</button>
                                    <button id="stopCamera" class="btn btn-danger" disabled><i class="fas fa-stop me-2"></i>Hentikan Kamera</button>
                                </div>
                                <form method="POST" id="faceForm" style="display: none;">
                                    <input type="hidden" name="face_form" value="1">
                                    <input type="hidden" name="face_data" id="faceData">
                                    <input type="hidden" name="is_checkout" id="isCheckout" value="0">
                                    <input type="hidden" name="recognized_teacher_id" id="recognizedTeacherId">
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Absensi Manual -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Absensi Manual</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="manual_attendance" value="1">
                                    <div class="mb-3">
                                        <label class="form-label">Pilih Guru <span class="text-danger">*</span></label>
                                        <select class="form-select" name="teacher_id" required>
                                            <option value="">-- Pilih Guru --</option>
                                            <?php 
                                            $teachers_result->data_seek(0);
                                            while ($teacher = $teachers_result->fetch_assoc()): 
                                            ?>
                                            <option value="<?php echo htmlspecialchars($teacher['id']); ?>"><?php echo htmlspecialchars($teacher['nama']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Waktu Masuk</label>
                                        <input type="time" class="form-control" name="waktu_masuk" value="<?php echo date('H:i'); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status Masuk</label>
                                        <select class="form-select" name="status_masuk">
                                            <option value="">-- Pilih Status --</option>
                                            <option value="hadir">Hadir</option>
                                            <option value="terlambat">Terlambat</option>
                                            <option value="izin">Izin</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Waktu Pulang</label>
                                        <input type="time" class="form-control" name="waktu_pulang">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status Pulang</label>
                                        <select class="form-select" name="status_pulang">
                                            <option value="">-- Pilih Status --</option>
                                            <option value="tepat waktu">Tepat Waktu</option>
                                            <option value="pulang cepat">Pulang Cepat</option>
                                            <option value="izin">Izin</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Absensi</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Absensi Hari Ini -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Absensi Hari Ini</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama Guru</th>
                                        <th>Waktu Masuk</th>
                                        <th>Status Masuk</th>
                                        <th>Waktu Pulang</th>
                                        <th>Status Pulang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($today_result->num_rows > 0): ?>
                                        <?php while ($attendance = $today_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(isset($attendance['nama']) ? $attendance['nama'] : '-'); ?></td>
                                            <td><?php echo htmlspecialchars(isset($attendance['waktu_masuk']) ? $attendance['waktu_masuk'] : '-'); ?></td>
                                            <td>
                                                <?php
                                                $badge_class = 'bg-success';
                                                if (isset($attendance['status_masuk']) && $attendance['status_masuk'] == 'terlambat') $badge_class = 'bg-warning';
                                                elseif (isset($attendance['status_masuk']) && $attendance['status_masuk'] == 'izin') $badge_class = 'bg-info';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars(isset($attendance['status_masuk']) ? ucfirst($attendance['status_masuk']) : '-'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(isset($attendance['waktu_pulang']) ? $attendance['waktu_pulang'] : '-'); ?></td>
                                            <td>
                                                <?php
                                                $badge_class = 'bg-success';
                                                if (isset($attendance['status_pulang']) && $attendance['status_pulang'] == 'pulang cepat') $badge_class = 'bg-warning';
                                                elseif (isset($attendance['status_pulang']) && $attendance['status_pulang'] == 'izin') $badge_class = 'bg-info';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars(isset($attendance['status_pulang']) ? ucfirst($attendance['status_pulang']) : '-'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">Belum ada absensi hari ini</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let video = document.getElementById('video');
        let stream = null;

        // Fungsi untuk memuat model face-api.js
        async function loadFaceApi() {
            try {
                console.log('Memuat model face-api.js...');
                await faceapi.nets.ssdMobilenetv1.loadFromUri('/absensi_app/public/models');
                await faceapi.nets.faceLandmark68Net.loadFromUri('/absensi_app/public/models');
                await faceapi.nets.faceRecognitionNet.loadFromUri('/absensi_app/public/models');
                console.log('Model face-api.js berhasil dimuat');
            } catch (err) {
                console.error('Gagal memuat model face-api.js:', err);
                alert('Gagal memuat model pengenalan wajah: ' + err.message);
                throw err;
            }
        }

        // Fungsi untuk membandingkan descriptor wajah
        async function compareFaces(capturedDescriptor, storedDescriptor) {
            try {
                const captured = new Float32Array(JSON.parse(capturedDescriptor));
                const stored = new Float32Array(JSON.parse(storedDescriptor));
                const distance = faceapi.euclideanDistance(captured, stored);
                console.log('Jarak wajah:', distance);
                return distance < 0.6; // Threshold untuk kecocokan wajah
            } catch (err) {
                console.error('Error membandingkan wajah:', err);
                return false;
            }
        }

        // Mulai kamera
        document.getElementById('startCamera').addEventListener('click', async function() {
            try {
                console.log('Memulai kamera...');
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                this.disabled = true;
                document.getElementById('captureCheckIn').disabled = false;
                document.getElementById('captureCheckOut').disabled = false;
                document.getElementById('stopCamera').disabled = false;
                await loadFaceApi();
                console.log('Kamera berhasil dimulai');
            } catch (err) {
                console.error('Kesalahan akses kamera:', err);
                alert('Error saat mengakses kamera: ' + err.message);
            }
        });

        // Absen masuk
        document.getElementById('captureCheckIn').addEventListener('click', async function() {
            try {
                if (!stream) {
                    console.log('Kamera belum dimulai');
                    alert('Harap mulai kamera terlebih dahulu!');
                    return;
                }
                console.log('Mengambil gambar dari video...');
                const canvas = document.createElement('canvas');
                canvas.width = 200;
                canvas.height = 200;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, 200, 200);
                console.log('Mendeteksi wajah...');
                const detections = await faceapi.detectSingleFace(canvas).withFaceLandmarks().withFaceDescriptor();
                if (!detections) {
                    console.log('Wajah tidak terdeteksi');
                    alert('Wajah tidak terdeteksi! Silakan coba lagi.');
                    return;
                }
                const capturedDescriptor = JSON.stringify(detections.descriptor);
                console.log('Descriptor wajah:', capturedDescriptor);

                console.log('Mengambil data guru...');
                const response = await fetch('get_teachers.php', {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' }
                });
                if (!response.ok) {
                    throw new Error('Gagal mengambil data guru: ' + response.statusText);
                }
                const teachers = await response.json();
                console.log('Data guru:', teachers);

                let recognizedTeacherId = null;
                for (const teacher of teachers) {
                    if (teacher.face_encoding) {
                        console.log('Membandingkan dengan guru ID:', teacher.id);
                        if (await compareFaces(capturedDescriptor, teacher.face_encoding)) {
                            recognizedTeacherId = teacher.id;
                            console.log('Wajah dikenali, ID:', recognizedTeacherId);
                            break;
                        }
                    }
                }

                if (!recognizedTeacherId) {
                    console.log('Wajah tidak dikenali');
                    alert('Wajah tidak dikenali! Silakan hubungi admin.');
                    return;
                }

                console.log('Mengirim form dengan teacher ID:', recognizedTeacherId);
                document.getElementById('faceData').value = capturedDescriptor;
                document.getElementById('isCheckout').value = '0';
                document.getElementById('recognizedTeacherId').value = recognizedTeacherId;
                document.getElementById('faceForm').submit();
            } catch (err) {
                console.error('Error saat absen masuk:', err);
                alert('Error saat absen masuk: ' + err.message);
            }
        });

        // Absen pulang
        document.getElementById('captureCheckOut').addEventListener('click', async function() {
            try {
                if (!stream) {
                    console.log('Kamera belum dimulai');
                    alert('Harap mulai kamera terlebih dahulu!');
                    return;
                }
                console.log('Mengambil gambar dari video...');
                const canvas = document.createElement('canvas');
                canvas.width = 200;
                canvas.height = 200;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, 200, 200);
                console.log('Mendeteksi wajah...');
                const detections = await faceapi.detectSingleFace(canvas).withFaceLandmarks().withFaceDescriptor();
                if (!detections) {
                    console.log('Wajah tidak terdeteksi');
                    alert('Wajah tidak terdeteksi! Silakan coba lagi.');
                    return;
                }
                const capturedDescriptor = JSON.stringify(detections.descriptor);
                console.log('Descriptor wajah:', capturedDescriptor);

                console.log('Mengambil data guru...');
                const response = await fetch('get_teachers.php', {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' }
                });
                if (!response.ok) {
                    throw new Error('Gagal mengambil data guru: ' + response.statusText);
                }
                const teachers = await response.json();
                console.log('Data guru:', teachers);

                let recognizedTeacherId = null;
                for (const teacher of teachers) {
                    if (teacher.face_encoding) {
                        console.log('Membandingkan dengan guru ID:', teacher.id);
                        if (await compareFaces(capturedDescriptor, teacher.face_encoding)) {
                            recognizedTeacherId = teacher.id;
                            console.log('Wajah dikenali, ID:', recognizedTeacherId);
                            break;
                        }
                    }
                }

                if (!recognizedTeacherId) {
                    console.log('Wajah tidak dikenali');
                    alert('Wajah tidak dikenali! Silakan hubungi admin.');
                    return;
                }

                console.log('Mengirim form dengan teacher ID:', recognizedTeacherId);
                document.getElementById('faceData').value = capturedDescriptor;
                document.getElementById('isCheckout').value = '1';
                document.getElementById('recognizedTeacherId').value = recognizedTeacherId;
                document.getElementById('faceForm').submit();
            } catch (err) {
                console.error('Error saat absen pulang:', err);
                alert('Error saat absen pulang: ' + err.message);
            }
        });

        // Hentikan kamera
        document.getElementById('stopCamera').addEventListener('click', function() {
            if (stream) {
                console.log('Menghentikan kamera...');
                stream.getTracks().forEach(track => track.stop());
                video.srcObject = null;
                document.getElementById('startCamera').disabled = false;
                this.disabled = true;
                document.getElementById('captureCheckIn').disabled = true;
                document.getElementById('captureCheckOut').disabled = true;
                console.log('Kamera dihentikan');
            }
        });
    </script>
</body>
</html>