<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "guru") {
    header("Location: ../login.php");
    exit;
}

require_once "../config.php";

function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

$success_msg = $error_msg = "";

// Ambil data guru
$teacher_sql = "SELECT * FROM teachers WHERE user_id = ?";
$teacher_stmt = $mysqli->prepare($teacher_sql);
$teacher_stmt->bind_param("i", $_SESSION["id"]);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();
$teacher_stmt->close();

if (!$teacher) {
    $error_msg = "Data guru tidak ditemukan!";
    error_log("Teacher not found for user_id: {$_SESSION['id']}");
    header("Location: ../login.php");
    exit;
}

// Cek absensi hari ini
$today_sql = "SELECT * FROM attendance WHERE teacher_id = ? AND tanggal = CURDATE()";
$today_stmt = $mysqli->prepare($today_sql);
$today_stmt->bind_param("i", $teacher['id']);
$today_stmt->execute();
$today_attendance = $today_stmt->get_result()->fetch_assoc();
$today_stmt->close();

// Proses absensi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['face_form'])) {
    $face_descriptor = sanitize_input($_POST['face_descriptor']);
    $is_checkout = isset($_POST['is_checkout']) && $_POST['is_checkout'] == '1';

    if (empty($face_descriptor)) {
        $error_msg = "Wajah tidak dikenali! Silakan coba lagi.";
        error_log("Face recognition failed: Invalid face_descriptor for teacher ID {$teacher['id']}");
    } elseif (!$teacher['face_encoding']) {
        $error_msg = "Wajah Anda belum terdaftar. Silakan hubungi admin.";
        error_log("Face recognition failed: No face_encoding for teacher ID {$teacher['id']}");
    } else {
        $tanggal = date('Y-m-d');
        $current_time = date('H:i:s');
        $current_timestamp = strtotime($current_time);
        $jam_masuk = isset($teacher['jam_masuk']) ? $teacher['jam_masuk'] : '08:00:00';
        $jam_pulang = isset($teacher['jam_pulang']) ? $teacher['jam_pulang'] : '15:00:00';
        $allowed_window = 30 * 60;
        $jam_masuk_timestamp = strtotime($jam_masuk);
        $jam_pulang_timestamp = strtotime($jam_pulang);

        if ($is_checkout) {
            if (!$today_attendance) {
                $error_msg = "Anda belum absen masuk hari ini!";
                error_log("Checkout failed: No check-in record for teacher ID {$teacher['id']}");
            } elseif (isset($today_attendance['waktu_pulang']) && $today_attendance['waktu_pulang']) {
                $error_msg = "Anda sudah absen pulang hari ini!";
                error_log("Checkout failed: Already checked out for teacher ID {$teacher['id']}");
            } elseif ($current_timestamp < $jam_masuk_timestamp) {
                $error_msg = "Belum waktunya untuk absen pulang! Absen pulang dapat dilakukan setelah jam masuk Anda ({$jam_masuk}).";
                error_log("Checkout failed: Attempted before jam_masuk for teacher ID {$teacher['id']}");
            } elseif ($current_timestamp < ($jam_pulang_timestamp - $allowed_window)) {
                $error_msg = "Terlalu cepat untuk absen pulang! Absen pulang dapat dilakukan mulai " . date('H:i:s', $jam_pulang_timestamp - $allowed_window) . ".";
                error_log("Checkout failed: Too early for teacher ID {$teacher['id']}");
            } else {
                $status_pulang = ($current_timestamp <= ($jam_pulang_timestamp + $allowed_window)) ? 'tepat waktu' : 'terlambat';
                $update_sql = "UPDATE attendance SET waktu_pulang = ?, status_pulang = ? WHERE teacher_id = ? AND tanggal = CURDATE()";
                $update_stmt = $mysqli->prepare($update_sql);
                if (!$update_stmt) {
                    $error_msg = "Error menyiapkan query: " . $mysqli->error;
                    error_log("Checkout query preparation failed: " . $mysqli->error);
                } else {
                    $update_stmt->bind_param("ssi", $current_time, $status_pulang, $teacher['id']);
                    if ($update_stmt->execute()) {
                        $success_msg = "Absensi pulang berhasil! Status: " . ucfirst($status_pulang);
                        error_log("Checkout successful for teacher ID {$teacher['id']}: $status_pulang");
                        header("Location: attendance.php");
                        exit;
                    } else {
                        $error_msg = "Error menjalankan query: " . $update_stmt->error;
                        error_log("Checkout query execution failed: " . $update_stmt->error);
                    }
                    $update_stmt->close();
                }
            }
        } else {
            if ($today_attendance) {
                $error_msg = "Anda sudah absen masuk hari ini!";
                error_log("Check-in failed: Already checked in for teacher ID {$teacher['id']}");
            } elseif ($current_timestamp > ($jam_masuk_timestamp + $allowed_window)) {
                $error_msg = "Terlambat untuk absen masuk! Absen masuk harus dilakukan sebelum " . date('H:i:s', $jam_masuk_timestamp + $allowed_window) . ".";
                error_log("Check-in failed: Too late for teacher ID {$teacher['id']}");
            } else {
                $status_masuk = ($current_timestamp <= $jam_masuk_timestamp) ? 'hadir' : 'terlambat';
                $insert_sql = "INSERT INTO attendance (teacher_id, tanggal, waktu_masuk, status_masuk) VALUES (?, ?, ?, ?)";
                $insert_stmt = $mysqli->prepare($insert_sql);
                if (!$insert_stmt) {
                    $error_msg = "Error menyiapkan query: " . $mysqli->error;
                    error_log("Check-in query preparation failed: " . $mysqli->error);
                } else {
                    $insert_stmt->bind_param("isss", $teacher['id'], $tanggal, $current_time, $status_masuk);
                    if ($insert_stmt->execute()) {
                        $success_msg = "Absensi masuk berhasil! Status: " . ucfirst($status_masuk);
                        error_log("Check-in successful for teacher ID {$teacher['id']}: $status_masuk");
                        header("Location: attendance.php");
                        exit;
                    } else {
                        $error_msg = "Error menjalankan query: " . $insert_stmt->error;
                        error_log("Check-in query execution failed: " . $insert_stmt->error);
                    }
                    $insert_stmt->close();
                }
            }
        }
    }
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
    <title>Absensi Wajah - TK Pelangi</title>
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
        .stat-card-primary {
            background: #007bff;
            color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .stat-card-primary .card-body {
            text-align: center;
            padding: 1.5rem;
        }
        .camera-container {
            position: relative;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 1.5rem;
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
        .btn-capture {
            background: #007bff;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-capture:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        .btn-capture:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            align-items: center;
            margin-top: 1rem;
        }
        .button-group .btn {
            flex: 1 1 auto;
            min-width: 140px;
            max-width: 180px;
            text-align: center;
            font-size: 0.9rem;
        }
        @media (max-width: 576px) {
            .button-group .btn {
                min-width: 100%;
                margin-bottom: 12px;
            }
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .teacher-photo {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ddd;
        }
        .date-time {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
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
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link active" href="attendance.php"><i class="fas fa-camera me-2"></i>Absensi Wajah</a>
                    <a class="nav-link" href="history.php"><i class="fas fa-history me-2"></i>Riwayat Absensi</a>
                    <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profil</a>
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
            </div>
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Absensi Wajah</h2>
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
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card stat-card-primary">
                            <div class="card-body text-center">
                                <h4>Status Absensi Hari Ini</h4>
                                <?php if ($today_attendance): ?>
                                    <h2 class="mb-3">
                                        <?php 
                                        $badge_class = "bg-success";
                                        if (isset($today_attendance['status_masuk']) && $today_attendance['status_masuk'] == "terlambat") $badge_class = "bg-warning";
                                        elseif (isset($today_attendance['status_masuk']) && $today_attendance['status_masuk'] == "izin") $badge_class = "bg-info";
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?> fs-5">
                                            <?php echo ucfirst(isset($today_attendance['status_masuk']) ? htmlspecialchars($today_attendance['status_masuk']) : '-'); ?>
                                        </span>
                                    </h2>
                                    <p>Waktu Masuk: <?php echo htmlspecialchars(isset($today_attendance['waktu_masuk']) && $today_attendance['waktu_masuk'] ? $today_attendance['waktu_masuk'] : '-'); ?></p>
                                    <?php if (isset($today_attendance['waktu_pulang']) && $today_attendance['waktu_pulang']): ?>
                                        <h2 class="mb-3">
                                            <?php 
                                            $badge_class = "bg-success";
                                            if (isset($today_attendance['status_pulang']) && $today_attendance['status_pulang'] == "pulang cepat") $badge_class = "bg-warning";
                                            elseif (isset($today_attendance['status_pulang']) && $today_attendance['status_pulang'] == "terlambat") $badge_class = "bg-warning";
                                            elseif (isset($today_attendance['status_pulang']) && $today_attendance['status_pulang'] == "izin") $badge_class = "bg-info";
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?> fs-5">
                                                <?php echo ucfirst(isset($today_attendance['status_pulang']) ? htmlspecialchars($today_attendance['status_pulang']) : '-'); ?>
                                            </span>
                                        </h2>
                                        <p>Waktu Pulang: <?php echo htmlspecialchars($today_attendance['waktu_pulang']); ?></p>
                                        <p class="text-success">✓ Anda sudah absen masuk dan pulang hari ini</p>
                                    <?php else: ?>
                                        <p class="text-success">✓ Anda sudah absen masuk hari ini</p>
                                        <p>Silakan lakukan absensi pulang sesuai jadwal Anda (<?php echo htmlspecialchars(isset($teacher['jam_pulang']) ? $teacher['jam_pulang'] : '-'); ?>)</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <h2 class="mb-3">
                                        <span class="badge bg-warning fs-5">Belum Absen</span>
                                    </h2>
                                    <p>Silakan lakukan absensi masuk sesuai jadwal Anda (<?php echo htmlspecialchars(isset($teacher['jam_masuk']) ? $teacher['jam_masuk'] : '-'); ?>)</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (!$teacher['face_encoding']): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Wajah Anda belum terdaftar. Silakan hubungi admin untuk mendaftarkan wajah.
                </div>
                <?php elseif (!$today_attendance || ($today_attendance && !isset($today_attendance['waktu_pulang']) || !$today_attendance['waktu_pulang'])): ?>
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-camera me-2"></i>Kamera Absensi</h5>
                            </div>
                            <div class="card-body">
                                <div class="camera-container mb-3">
                                    <video id="video" autoplay></video>
                                    <div class="camera-overlay"></div>
                                </div>
                                <div class="button-group">
                                    <button id="startCamera" class="btn btn-success"><i class="fas fa-video me-2"></i>Mulai Kamera</button>
                                    <button id="captureCheckIn" class="btn btn-capture" <?php echo $today_attendance ? 'disabled' : ''; ?>><i class="fas fa-camera me-2"></i>Absen Masuk</button>
                                    <button id="captureCheckOut" class="btn btn-capture" <?php echo !$today_attendance ? 'disabled' : ''; ?>><i class="fas fa-camera me-2"></i>Absen Pulang</button>
                                    <button id="stopCamera" class="btn btn-danger" disabled><i class="fas fa-stop me-2"></i>Hentikan Kamera</button>
                                </div>
                                <form method="POST" id="faceForm" style="display: none;">
                                    <input type="hidden" name="face_form" value="1">
                                    <input type="hidden" name="face_descriptor" id="faceDescriptor">
                                    <input type="hidden" name="is_checkout" id="isCheckout" value="0">
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Informasi</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <?php if (!empty($teacher['foto_wajah']) && file_exists($teacher['foto_wajah'])): ?>
                                        <img src="<?php echo htmlspecialchars($teacher['foto_wajah']); ?>" alt="Foto Guru" class="teacher-photo">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle fa-5x text-primary"></i>
                                    <?php endif; ?>
                                </div>
                                <table class="table table-borderless">
                                    <tr><td><strong>Nama:</strong></td><td><?php echo htmlspecialchars(isset($teacher['nama']) ? $teacher['nama'] : ''); ?></td></tr>
                                    <tr><td><strong>NIP:</strong></td><td><?php echo htmlspecialchars(isset($teacher['nip']) ? $teacher['nip'] : ''); ?></td></tr>
                                    <tr><td><strong>Jam Masuk:</strong></td><td><?php echo htmlspecialchars(isset($teacher['jam_masuk']) ? $teacher['jam_masuk'] : ''); ?></td></tr>
                                    <tr><td><strong>Jam Pulang:</strong></td><td><?php echo htmlspecialchars(isset($teacher['jam_pulang']) ? $teacher['jam_pulang'] : ''); ?></td></tr>
                                    <tr><td><strong>Status Wajah:</strong></td><td>
                                        <?php if (!empty($teacher['face_encoding'])): ?>
                                            <span class="badge bg-success">Terdaftar</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Belum Terdaftar</span>
                                        <?php endif; ?>
                                    </td></tr>
                                </table>
                            </div>
                        </div>
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">Petunjuk</h5>
                            </div>
                            <div class="card-body">
                                <ol class="mb-0">
                                    <li>Klik "Mulai Kamera"</li>
                                    <li>Posisikan wajah di dalam lingkaran</li>
                                    <li>Pastikan pencahayaan cukup</li>
                                    <li>Klik "Absen Masuk" atau "Absen Pulang" sesuai jadwal Anda</li>
                                    <li>Absen masuk dapat dilakukan hingga 30 menit setelah jam masuk</li>
                                    <li>Absen pulang dapat dilakukan mulai 30 menit sebelum jam pulang</li>
                                    <li>Pastikan wajah Anda cocok dengan yang terdaftar</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let video = document.getElementById('video');
        let stream = null;

        <?php if ($teacher['face_encoding'] && (!$today_attendance || ($today_attendance && !isset($today_attendance['waktu_pulang']) || !$today_attendance['waktu_pulang']))): ?>
        async function loadFaceApi() {
            try {
                await faceapi.nets.ssdMobilenetv1.loadFromUri('/absensi_app/public/models');
                await faceapi.nets.faceLandmark68Net.loadFromUri('/absensi_app/public/models');
                await faceapi.nets.faceRecognitionNet.loadFromUri('/absensi_app/public/models');
                console.log('Face-api.js models loaded');
            } catch (err) {
                alert('Gagal memuat model face-api.js: ' + err.message);
                console.error('Error loading face-api.js models:', err);
            }
        }

        document.getElementById('startCamera').addEventListener('click', async function() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                this.disabled = true;
                document.getElementById('captureCheckIn').disabled = <?php echo $today_attendance ? 'true' : 'false'; ?>;
                document.getElementById('captureCheckOut').disabled = <?php echo !$today_attendance ? 'true' : 'false'; ?>;
                document.getElementById('stopCamera').disabled = false;
                await loadFaceApi();
            } catch (err) {
                alert('Error saat mengakses kamera: ' + err.message);
                console.error('Kesalahan akses kamera:', err);
            }
        });

        async function compareFaces(capturedDescriptor, storedDescriptor) {
            try {
                const captured = new Float32Array(JSON.parse(capturedDescriptor));
                const stored = new Float32Array(JSON.parse(storedDescriptor));
                const distance = faceapi.euclideanDistance(captured, stored);
                console.log('Face distance:', distance);
                return distance < 0.6;
            } catch (err) {
                console.error('Error comparing faces:', err);
                return false;
            }
        }

        document.getElementById('captureCheckIn').addEventListener('click', async function() {
            if (!stream) {
                alert('Harap mulai kamera terlebih dahulu!');
                return;
            }
            const canvas = document.createElement('canvas');
            canvas.width = 200;
            canvas.height = 200;
            canvas.getContext('2d').drawImage(video, 0, 0, 200, 200);
            const detections = await faceapi.detectSingleFace(canvas).withFaceLandmarks().withFaceDescriptor();
            if (!detections) {
                alert('Wajah tidak terdeteksi! Silakan coba lagi.');
                return;
            }
            const capturedDescriptor = JSON.stringify(detections.descriptor);
            const storedDescriptor = '<?php echo addslashes(isset($teacher['face_encoding']) ? $teacher['face_encoding'] : ''); ?>';
            if (!await compareFaces(capturedDescriptor, storedDescriptor)) {
                alert('Wajah tidak cocok! Absensi ditolak.');
                return;
            }
            document.getElementById('faceDescriptor').value = capturedDescriptor;
            document.getElementById('isCheckout').value = '0';
            document.getElementById('faceForm').submit();
        });

        document.getElementById('captureCheckOut').addEventListener('click', async function() {
            if (!stream) {
                alert('Harap mulai kamera terlebih dahulu!');
                return;
            }
            const canvas = document.createElement('canvas');
            canvas.width = 200;
            canvas.height = 200;
            canvas.getContext('2d').drawImage(video, 0, 0, 200, 200);
            const detections = await faceapi.detectSingleFace(canvas).withFaceLandmarks().withFaceDescriptor();
            if (!detections) {
                alert('Wajah tidak terdeteksi! Silakan coba lagi.');
                return;
            }
            const capturedDescriptor = JSON.stringify(detections.descriptor);
            const storedDescriptor = '<?php echo addslashes(isset($teacher['face_encoding']) ? $teacher['face_encoding'] : ''); ?>';
            if (!await compareFaces(capturedDescriptor, storedDescriptor)) {
                alert('Wajah tidak cocok! Absensi ditolak.');
                return;
            }
            document.getElementById('faceDescriptor').value = capturedDescriptor;
            document.getElementById('isCheckout').value = '1';
            document.getElementById('faceForm').submit();
        });

        document.getElementById('stopCamera').addEventListener('click', function() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                video.srcObject = null;
                document.getElementById('startCamera').disabled = false;
                this.disabled = true;
                document.getElementById('captureCheckIn').disabled = <?php echo $today_attendance ? 'true' : 'false'; ?>;
                document.getElementById('captureCheckOut').disabled = <?php echo !$today_attendance ? 'true' : 'false'; ?>;
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>