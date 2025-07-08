<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "guru") {
    header("location: ../login.php");
    exit;
}

require_once "../config.php";

// Get teacher data
$teacher_sql = "SELECT t.*, u.username FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.user_id = ?";
$teacher_stmt = $mysqli->prepare($teacher_sql);
$teacher_stmt->bind_param("i", $_SESSION["id"]);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher = $teacher_result->fetch_assoc();
$teacher_stmt->close();

// Check if teacher data exists
if (!$teacher) {
    $error_msg = "Data guru tidak ditemukan!";
    error_log("Teacher not found for user_id: {$_SESSION['id']}");
    header("location: ../login.php");
    exit;
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $telepon = trim($_POST['telepon']);
    $alamat = trim($_POST['alamat']);
    
    // Validate required fields
    if (empty($email) || empty($telepon) || empty($alamat)) {
        $error_msg = "Semua kolom wajib diisi!";
    } else {
        // Handle photo upload
        $photo_path = isset($teacher['photo']) ? $teacher['photo'] : '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            $file_type = mime_content_type($_FILES['photo']['tmp_name']);
            $file_size = $_FILES['photo']['size'];
            $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $new_file_name = 'teacher_' . $_SESSION["id"] . '_' . time() . '.' . $file_ext;
            $upload_dir = '../Uploads/photos/';
            $upload_path = $upload_dir . $new_file_name;

            // Ensure upload directory exists
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (!in_array($file_type, $allowed_types)) {
                $error_msg = "Jenis file tidak diizinkan! Gunakan JPG, PNG, atau GIF.";
            } elseif ($file_size > $max_size) {
                $error_msg = "Ukuran file terlalu besar! Maksimum 2MB.";
            } elseif (!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $error_msg = "Gagal mengunggah foto!";
            } else {
                $photo_path = 'Uploads/photos/' . $new_file_name;
                // Delete old photo if exists
                if (!empty($teacher['photo']) && file_exists('../' . $teacher['photo'])) {
                    unlink('../' . $teacher['photo']);
                }
            }
        }

        if (!isset($error_msg)) {
            $update_sql = "UPDATE teachers SET email = ?, telepon = ?, alamat = ?, photo = ? WHERE user_id = ?";
            $update_stmt = $mysqli->prepare($update_sql);
            $update_stmt->bind_param("ssssi", $email, $telepon, $alamat, $photo_path, $_SESSION["id"]);
            
            if ($update_stmt->execute()) {
                $success_msg = "Profil berhasil diperbarui!";
                // Refresh data
                $teacher_stmt->execute();
                $teacher_result = $teacher_stmt->get_result();
                $teacher = $teacher_result->fetch_assoc();
            } else {
                $error_msg = "Error: " . $update_stmt->error;
                error_log("Profile update failed: " . $update_stmt->error);
            }
            $update_stmt->close();
        }
    }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_msg = "Semua kolom password wajib diisi!";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "Password baru dan konfirmasi password tidak cocok!";
    } else {
        // Verify current password
        $user_sql = "SELECT password FROM users WHERE id = ?";
        $user_stmt = $mysqli->prepare($user_sql);
        $user_stmt->bind_param("i", $_SESSION["id"]);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        $user_stmt->close();
        
        if (password_verify($current_password, $user_data['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_pass_stmt = $mysqli->prepare($update_pass_sql);
            $update_pass_stmt->bind_param("si", $hashed_password, $_SESSION["id"]);
            
            if ($update_pass_stmt->execute()) {
                $success_msg = "Password berhasil diubah!";
            } else {
                $error_msg = "Error: " . $update_pass_stmt->error;
                error_log("Password change failed: " . $update_pass_stmt->error);
            }
            $update_pass_stmt->close();
        } else {
            $error_msg = "Password saat ini salah!";
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
    <title>Profil Saya - TK Pelangi</title>
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
        .profile-header {
            background: #007bff;
            color: #fff;
            border-radius: 12px 12px 0 0;
            text-align: center;
            padding: 2rem;
        }
        .profile-photo {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #fff;
            margin-bottom: 1rem;
        }
        .btn-primary {
            background: #007bff;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        .btn-warning {
            background: #ffc107;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }
        .form-control, .form-control[readonly] {
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }
        .photo-upload {
            position: relative;
            display: inline-block;
        }
        .photo-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .photo-upload-label {
            background: #007bff;
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-size: 0.9rem;
        }
        .photo-upload-label:hover {
            background: #0056b3;
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
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link" href="attendance.php"><i class="fas fa-camera me-2"></i>Absensi Wajah</a>
                    <a class="nav-link" href="history.php"><i class="fas fa-history me-2"></i>Riwayat Absensi</a>
                    <a class="nav-link active" href="profile.php"><i class="fas fa-user me-2"></i>Profil</a>
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Profil Saya</h2>
                    <div class="text-muted date-time">
                        <i class="fas fa-calendar me-2"></i>
                        <?php echo htmlspecialchars($formatted_date); ?>
                        <i class="fas fa-clock me-2"></i>
                        <?php echo htmlspecialchars($formatted_time); ?>
                    </div>
                </div>
                
                <?php if (isset($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Profile Information -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="profile-header p-4 text-center">
                                <?php if (!empty($teacher['photo']) && file_exists('../' . $teacher['photo'])): ?>
                                    <img src="/absensi_app/<?php echo htmlspecialchars($teacher['photo']); ?>" class="profile-photo" alt="Foto Profil">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-5x mb-3"></i>
                                <?php endif; ?>
                                <h3><?php echo htmlspecialchars(isset($teacher['nama']) ? $teacher['nama'] : ''); ?></h3>
                                <p class="mb-0">NIP: <?php echo htmlspecialchars(isset($teacher['nip']) ? $teacher['nip'] : ''); ?></p>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="update_profile" value="1">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Username</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars(isset($teacher['username']) ? $teacher['username'] : ''); ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">NIP</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars(isset($teacher['nip']) ? $teacher['nip'] : ''); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Nama Lengkap</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars(isset($teacher['nama']) ? $teacher['nama'] : ''); ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars(isset($teacher['email']) ? $teacher['email'] : ''); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Telepon</label>
                                                <input type="text" class="form-control" name="telepon" value="<?php echo htmlspecialchars(isset($teacher['telepon']) ? $teacher['telepon'] : ''); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Status Wajah</label>
                                                <div class="form-control">
                                                    <?php if (!empty($teacher['face_encoding'])): ?>
                                                        <span class="badge bg-success">Terdaftar</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Belum Terdaftar</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Alamat</label>
                                        <textarea class="form-control" name="alamat" rows="3" required><?php echo htmlspecialchars(isset($teacher['alamat']) ? $teacher['alamat'] : ''); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Foto Profil</label>
                                        <div class="photo-upload">
                                            <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/gif">
                                            <label for="photo" class="photo-upload-label"><i class="fas fa-upload me-2"></i>Upload Foto</label>
                                        </div>
                                        <small class="form-text text-muted">Maksimum 2MB, format JPG, PNG, atau GIF.</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-lock me-2"></i>Ubah Password
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="change_password" value="1">
                                    <div class="mb-3">
                                        <label class="form-label">Password Saat Ini</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password Baru</label>
                                        <input type="password" class="form-control" name="new_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Konfirmasi Password Baru</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                    </div>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-key me-2"></i>Ubah Password
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Informasi
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Jika wajah Anda belum terdaftar, silakan hubungi admin untuk mendaftarkan wajah.
                                </p>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    Pastikan password Anda aman dan tidak mudah ditebak.
                                </p>
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