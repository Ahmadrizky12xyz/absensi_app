<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: ../login.php");
    exit;
}

require_once "../config.php";

$success_msg = $error_msg = "";
$teacher_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

$teacher_sql = "SELECT * FROM teachers WHERE id = ?";
$teacher_stmt = $mysqli->prepare($teacher_sql);
$teacher_stmt->bind_param("i", $teacher_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();
$teacher_stmt->close();

if (!$teacher) {
    $error_msg = "Guru tidak ditemukan!";
    header("location: teachers.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['face_data'])) {
    $face_data = trim($_POST['face_data']);
    $face_descriptor = trim($_POST['face_descriptor']);
    
    if (empty($face_data) || empty($face_descriptor)) {
        $error_msg = "Data wajah atau deskriptor tidak valid!";
        error_log("Invalid face_data or face_descriptor for teacher ID {$teacher_id}");
    } else {
        $upload_dir = "Uploads/teacher_photos/";
        $filename = $upload_dir . "teacher_{$teacher_id}.jpg";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $face_data));
        if (file_put_contents($filename, $image_data) === false) {
            $error_msg = "Gagal menyimpan gambar!";
            error_log("Failed to save image for teacher ID {$teacher_id}");
        } else {
            $update_sql = "UPDATE teachers SET foto_wajah = ?, face_encoding = ? WHERE id = ?";
            $update_stmt = $mysqli->prepare($update_sql);
            if ($update_stmt) {
                $update_stmt->bind_param("ssi", $filename, $face_descriptor, $teacher_id);
                if ($update_stmt->execute()) {
                    $success_msg = "Wajah berhasil didaftarkan!";
                    $teacher_sql = "SELECT * FROM teachers WHERE id = ?";
                    $teacher_stmt = $mysqli->prepare($teacher_sql);
                    $teacher_stmt->bind_param("i", $teacher_id);
                    $teacher_stmt->execute();
                    $teacher_result = $teacher_stmt->get_result();
                    $teacher = $teacher_result->fetch_assoc();
                    $teacher_stmt->close();
                } else {
                    $error_msg = "Error menyimpan data wajah: " . $update_stmt->error;
                    error_log("Error saving face data for teacher ID {$teacher_id}: " . $update_stmt->error);
                }
                $update_stmt->close();
            } else {
                $error_msg = "Error menyiapkan query: " . $mysqli->error;
                error_log("Query preparation failed: " . $mysqli->error);
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
    <title>Pendaftaran Wajah - TK Pelangi</title>
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
        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
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
        .btn-primary, .btn-success, .btn-info, .btn-warning, .btn-danger, .btn-secondary {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            transition: transform 0.2s ease;
        }
        .btn-primary:hover, .btn-success:hover, .btn-info:hover, .btn-warning:hover, .btn-danger:hover, .btn-secondary:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: #007bff;
            border: none;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .teacher-photo {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ddd;
        }
        .table {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
        }
        .table th, .table td {
            padding: 0.75rem;
            vertical-align: middle;
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
                    <small class="sidebar-subtext">Panel Admin</small>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link active" href="teachers.php"><i class="fas fa-users me-2"></i>Manajemen Guru</a>
                    <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Absensi</a>
                    <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Laporan</a>
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
            </div>
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Pendaftaran Wajah</h2>
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-muted date-time">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo htmlspecialchars($formatted_date); ?>
                            <i class="fas fa-clock me-2"></i>
                            <?php echo htmlspecialchars($formatted_time); ?>
                        </div>
                        <a href="teachers.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Kembali</a>
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
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Kamera Pendaftaran Wajah</h5>
                            </div>
                            <div class="card-body">
                                <div class="camera-container mb-3">
                                    <video id="video" autoplay></video>
                                    <div class="camera-overlay"></div>
                                </div>
                                <div class="text-center">
                                    <button id="startCamera" class="btn btn-success me-2"><i class="fas fa-video me-2"></i>Mulai Kamera</button>
                                    <button id="captureBtn" class="btn btn-primary me-2" disabled><i class="fas fa-camera me-2"></i>Ambil Foto</button>
                                    <button id="stopCamera" class="btn btn-danger" disabled><i class="fas fa-stop me-2"></i>Hentikan Kamera</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Data Guru</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <?php if (!empty($teacher['foto_wajah']) && file_exists($teacher['foto_wajah'])): ?>
                                        <img src="<?php echo htmlspecialchars($teacher['foto_wajah']); ?>" alt="Foto Guru" class="teacher-photo">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle fa-5x text-muted"></i>
                                    <?php endif; ?>
                                </div>
                                <table class="table table-borderless">
                                    <tr><td><strong>Nama:</strong></td><td><?php echo htmlspecialchars(isset($teacher['nama']) ? $teacher['nama'] : ''); ?></td></tr>
                                    <tr><td><strong>NIP:</strong></td><td><?php echo htmlspecialchars(isset($teacher['nip']) ? $teacher['nip'] : ''); ?></td></tr>
                                    <tr><td><strong>Email:</strong></td><td><?php echo htmlspecialchars(isset($teacher['email']) ? $teacher['email'] : ''); ?></td></tr>
                                    <tr><td><strong>Status:</strong></td><td>
                                        <?php if (!empty($teacher['face_encoding'])): ?>
                                            <span class="badge bg-success">Wajah Terdaftar</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Belum Terdaftar</span>
                                        <?php endif; ?>
                                    </td></tr>
                                </table>
                            </div>
                        </div>
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">Hasil Capture</h5>
                            </div>
                            <div class="card-body text-center">
                                <canvas id="canvas" width="200" height="200" style="border: 1px solid #ddd; border-radius: 10px; display: none;"></canvas>
                                <div id="noCapture" class="text-muted">
                                    <i class="fas fa-camera fa-3x mb-2"></i>
                                    <p>Belum ada foto yang diambil</p>
                                </div>
                                <form method="POST" id="faceForm" style="display: none;">
                                    <input type="hidden" name="face_data" id="faceData">
                                    <input type="hidden" name="face_descriptor" id="faceDescriptor">
                                    <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-2"></i>Simpan Wajah</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let video = document.getElementById('video');
        let canvas = document.getElementById('canvas');
        let ctx = canvas.getContext('2d');
        let stream = null;

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
                document.getElementById('captureBtn').disabled = false;
                document.getElementById('stopCamera').disabled = false;
                await loadFaceApi();
            } catch (err) {
                alert('Error mengakses kamera: ' + err.message);
                console.error('Camera access error:', err);
            }
        });

        document.getElementById('captureBtn').addEventListener('click', async function() {
            if (!stream) {
                alert('Harap mulai kamera terlebih dahulu!');
                return;
            }
            ctx.drawImage(video, 0, 0, 200, 200);
            canvas.style.display = 'block';
            document.getElementById('noCapture').style.display = 'none';
            const imageData = canvas.toDataURL('image/jpeg', 0.8);
            document.getElementById('faceData').value = imageData;

            const detections = await faceapi.detectSingleFace(canvas).withFaceLandmarks().withFaceDescriptor();
            if (!detections) {
                alert('Wajah tidak terdeteksi! Silakan coba lagi.');
                canvas.style.display = 'none';
                document.getElementById('noCapture').style.display = 'block';
                document.getElementById('faceData').value = '';
                return;
            }
            document.getElementById('faceDescriptor').value = JSON.stringify(detections.descriptor);
            document.getElementById('faceForm').style.display = 'block';
        });

        document.getElementById('stopCamera').addEventListener('click', function() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                video.srcObject = null;
                document.getElementById('startCamera').disabled = false;
                this.disabled = true;
                document.getElementById('captureBtn').disabled = true;
            }
        });
    </script>
</body>
</html>