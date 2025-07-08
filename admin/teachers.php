<?php
session_start();

// Set timezone ke WIB
date_default_timezone_set('Asia/Jakarta');

// Periksa apakah pengguna sudah login dan memiliki peran admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: ../login.php");
    exit;
}

require_once "../config.php";

// Inisialisasi pesan
$success_msg = $error_msg = "";

// Tangani pengiriman form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $nip = trim($_POST['nip']);
            $nama = trim($_POST['nama']);
            $email = trim($_POST['email']);
            $telepon = trim($_POST['telepon']);
            $alamat = trim($_POST['alamat']);
            $jam_masuk = trim($_POST['jam_masuk']);
            $jam_pulang = trim($_POST['jam_pulang']);

            // Validasi input kosong
            if (empty($username) || empty($password) || empty($nama) || empty($jam_masuk) || empty($jam_pulang)) {
                $error_msg = "Harap isi semua kolom yang diwajibkan!";
            } 
            // Validasi format email jika diisi
            elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_msg = "Format email tidak valid!";
            } 
            // Validasi jam_pulang > jam_masuk
            elseif (strtotime($jam_pulang) <= strtotime($jam_masuk)) {
                $error_msg = "Jam pulang harus lebih besar dari jam masuk!";
            } else {
                // Periksa apakah username sudah ada
                $check_username_sql = "SELECT id FROM users WHERE username = ?";
                $check_username_stmt = $mysqli->prepare($check_username_sql);
                $check_username_stmt->bind_param("s", $username);
                $check_username_stmt->execute();
                $check_username_result = $check_username_stmt->get_result();

                if ($check_username_result->num_rows > 0) {
                    $error_msg = "Username sudah digunakan! Silakan pilih username lain.";
                } else {
                    // Periksa apakah nip sudah ada
                    $check_nip_sql = "SELECT id FROM teachers WHERE nip = ?";
                    $check_nip_stmt = $mysqli->prepare($check_nip_sql);
                    $check_nip_stmt->bind_param("s", $nip);
                    $check_nip_stmt->execute();
                    $check_nip_result = $check_nip_stmt->get_result();

                    if ($check_nip_result->num_rows > 0) {
                        $error_msg = "NIP sudah digunakan! Silakan masukkan NIP lain.";
                    } else {
                        // Hash password
                        $password_hashed = password_hash($password, PASSWORD_DEFAULT);

                        // Insert user
                        $user_sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'guru')";
                        $user_stmt = $mysqli->prepare($user_sql);
                        $user_stmt->bind_param("ss", $username, $password_hashed);

                        if ($user_stmt->execute()) {
                            $user_id = $mysqli->insert_id;

                            // Insert teacher
                            $teacher_sql = "INSERT INTO teachers (user_id, nip, nama, email, telepon, alamat, jam_masuk, jam_pulang) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                            $teacher_stmt = $mysqli->prepare($teacher_sql);
                            $teacher_stmt->bind_param("isssssss", $user_id, $nip, $nama, $email, $telepon, $alamat, $jam_masuk, $jam_pulang);

                            if ($teacher_stmt->execute()) {
                                $success_msg = "Guru berhasil ditambahkan!";
                            } else {
                                $error_msg = "Error menambahkan guru: " . $teacher_stmt->error;
                                // Rollback: hapus user jika insert teacher gagal
                                $delete_user_sql = "DELETE FROM users WHERE id = ?";
                                $delete_user_stmt = $mysqli->prepare($delete_user_sql);
                                $delete_user_stmt->bind_param("i", $user_id);
                                $delete_user_stmt->execute();
                                $delete_user_stmt->close();
                            }
                            $teacher_stmt->close();
                        } else {
                            $error_msg = "Error menambahkan user: " . $user_stmt->error;
                        }
                        $user_stmt->close();
                    }
                    $check_nip_stmt->close();
                }
                $check_username_stmt->close();
            }
        } elseif ($_POST['action'] == 'edit') {
            $teacher_id = $_POST['teacher_id'];
            $username = trim($_POST['username']);
            $nip = trim($_POST['nip']);
            $nama = trim($_POST['nama']);
            $email = trim($_POST['email']);
            $telepon = trim($_POST['telepon']);
            $alamat = trim($_POST['alamat']);
            $jam_masuk = trim($_POST['jam_masuk']);
            $jam_pulang = trim($_POST['jam_pulang']);
            $password = !empty(trim($_POST['password'])) ? password_hash(trim($_POST['password']), PASSWORD_DEFAULT) : null;

            // Validasi input kosong
            if (empty($username) || empty($nama) || empty($jam_masuk) || empty($jam_pulang)) {
                $error_msg = "Harap isi semua kolom yang diwajibkan!";
            } 
            // Validasi format email jika diisi
            elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_msg = "Format email tidak valid!";
            } 
            // Validasi jam_pulang > jam_masuk
            elseif (strtotime($jam_pulang) <= strtotime($jam_masuk)) {
                $error_msg = "Jam pulang harus lebih besar dari jam masuk!";
            } else {
                // Dapatkan user_id dan username saat ini
                $get_user_sql = "SELECT user_id, username FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ?";
                $get_user_stmt = $mysqli->prepare($get_user_sql);
                $get_user_stmt->bind_param("i", $teacher_id);
                $get_user_stmt->execute();
                $user_result = $get_user_stmt->get_result();
                $user_data = $user_result->fetch_assoc();
                $get_user_stmt->close();

                if ($user_data) {
                    // Periksa apakah username diubah dan sudah ada
                    if ($username !== $user_data['username']) {
                        $check_username_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
                        $check_username_stmt = $mysqli->prepare($check_username_sql);
                        $check_username_stmt->bind_param("si", $username, $user_data['user_id']);
                        $check_username_stmt->execute();
                        $check_username_result = $check_username_stmt->get_result();

                        if ($check_username_result->num_rows > 0) {
                            $error_msg = "Username sudah digunakan! Silakan pilih username lain.";
                            $check_username_stmt->close();
                        } else {
                            $check_username_stmt->close();
                            // Periksa apakah nip diubah dan sudah ada
                            $check_nip_sql = "SELECT id FROM teachers WHERE nip = ? AND id != ?";
                            $check_nip_stmt = $mysqli->prepare($check_nip_sql);
                            $check_nip_stmt->bind_param("si", $nip, $teacher_id);
                            $check_nip_stmt->execute();
                            $check_nip_result = $check_nip_stmt->get_result();

                            if ($check_nip_result->num_rows > 0) {
                                $error_msg = "NIP sudah digunakan! Silakan masukkan NIP lain.";
                                $check_nip_stmt->close();
                            } else {
                                $check_nip_stmt->close();
                                // Update user
                                if ($password) {
                                    $user_sql = "UPDATE users SET username = ?, password = ? WHERE id = ?";
                                    $user_stmt = $mysqli->prepare($user_sql);
                                    $user_stmt->bind_param("ssi", $username, $password, $user_data['user_id']);
                                } else {
                                    $user_sql = "UPDATE users SET username = ? WHERE id = ?";
                                    $user_stmt = $mysqli->prepare($user_sql);
                                    $user_stmt->bind_param("si", $username, $user_data['user_id']);
                                }

                                if ($user_stmt->execute()) {
                                    // Update teacher
                                    $teacher_sql = "UPDATE teachers SET nip = ?, nama = ?, email = ?, telepon = ?, alamat = ?, jam_masuk = ?, jam_pulang = ? WHERE id = ?";
                                    $teacher_stmt = $mysqli->prepare($teacher_sql);
                                    $teacher_stmt->bind_param("sssssssi", $nip, $nama, $email, $telepon, $alamat, $jam_masuk, $jam_pulang, $teacher_id);

                                    if ($teacher_stmt->execute()) {
                                        $success_msg = "Data guru berhasil diperbarui!";
                                    } else {
                                        $error_msg = "Error memperbarui guru: " . $teacher_stmt->error;
                                    }
                                    $teacher_stmt->close();
                                } else {
                                    $error_msg = "Error memperbarui user: " . $user_stmt->error;
                                }
                                $user_stmt->close();
                            }
                        }
                    } else {
                        // Periksa apakah nip diubah dan sudah ada
                        $check_nip_sql = "SELECT id FROM teachers WHERE nip = ? AND id != ?";
                        $check_nip_stmt = $mysqli->prepare($check_nip_sql);
                        $check_nip_stmt->bind_param("si", $nip, $teacher_id);
                        $check_nip_stmt->execute();
                        $check_nip_result = $check_nip_stmt->get_result();

                        if ($check_nip_result->num_rows > 0) {
                            $error_msg = "NIP sudah digunakan! Silakan masukkan NIP lain.";
                            $check_nip_stmt->close();
                        } else {
                            $check_nip_stmt->close();
                            // Update teacher
                            $teacher_sql = "UPDATE teachers SET nip = ?, nama = ?, email = ?, telepon = ?, alamat = ?, jam_masuk = ?, jam_pulang = ? WHERE id = ?";
                            $teacher_stmt = $mysqli->prepare($teacher_sql);
                            $teacher_stmt->bind_param("sssssssi", $nip, $nama, $email, $telepon, $alamat, $jam_masuk, $jam_pulang, $teacher_id);

                            if ($teacher_stmt->execute()) {
                                // Update password jika diisi
                                if ($password) {
                                    $user_sql = "UPDATE users SET password = ? WHERE id = ?";
                                    $user_stmt = $mysqli->prepare($user_sql);
                                    $user_stmt->bind_param("si", $password, $user_data['user_id']);
                                    if ($user_stmt->execute()) {
                                        $success_msg = "Data guru berhasil diperbarui!";
                                    } else {
                                        $error_msg = "Error memperbarui password: " . $user_stmt->error;
                                    }
                                    $user_stmt->close();
                                } else {
                                    $success_msg = "Data guru berhasil diperbarui!";
                                }
                            } else {
                                $error_msg = "Error memperbarui guru: " . $teacher_stmt->error;
                            }
                            $teacher_stmt->close();
                        }
                    }
                } else {
                    $error_msg = "Guru tidak ditemukan!";
                }
            }
        } elseif ($_POST['action'] == 'delete') {
            $teacher_id = $_POST['teacher_id'];

            // Dapatkan user_id terlebih dahulu
            $get_user_sql = "SELECT user_id FROM teachers WHERE id = ?";
            $get_user_stmt = $mysqli->prepare($get_user_sql);
            $get_user_stmt->bind_param("i", $teacher_id);
            $get_user_stmt->execute();
            $user_result = $get_user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            $get_user_stmt->close();

            if ($user_data) {
                // Hapus user (akan menghapus teacher juga karena foreign key)
                $delete_sql = "DELETE FROM users WHERE id = ?";
                $delete_stmt = $mysqli->prepare($delete_sql);
                $delete_stmt->bind_param("i", $user_data['user_id']);

                if ($delete_stmt->execute()) {
                    $success_msg = "Guru berhasil dihapus!";
                } else {
                    $error_msg = "Error menghapus guru: " . $delete_stmt->error;
                }
                $delete_stmt->close();
            } else {
                $error_msg = "Guru tidak ditemukan!";
            }
        }
    }
}

// Ambil semua data guru
$teachers_sql = "SELECT t.*, u.username FROM teachers t JOIN users u ON t.user_id = u.id ORDER BY t.nama";
$teachers_result = $mysqli->query($teachers_sql);

// Ambil data guru untuk modal edit
$edit_teacher = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $edit_sql = "SELECT t.*, u.username FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ?";
    $edit_stmt = $mysqli->prepare($edit_sql);
    if ($edit_stmt) {
        $edit_stmt->bind_param("i", $edit_id);
        $edit_stmt->execute();
        $edit_result = $edit_stmt->get_result();
        if ($edit_result->num_rows > 0) {
            $edit_teacher = $edit_result->fetch_assoc();
        } else {
            $error_msg = "Data guru tidak ditemukan untuk ID: $edit_id";
        }
        $edit_stmt->close();
    } else {
        $error_msg = "Error menyiapkan query edit: " . $mysqli->error;
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
    <title>Manajemen Guru - TK Pelangi</title>
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
        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .modal-content {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .modal-header, .modal-footer {
            border: none;
            padding: 1.5rem;
        }
        .modal-title {
            font-size: 1.25rem;
            color: #2c3e50;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }
        .form-control, .form-control:focus {
            border-radius: 8px;
            border: 1px solid #ced4da;
            box-shadow: none;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }
        .modal-body {
            padding: 1.5rem;
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
                    <h2>Manajemen Guru</h2>
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

                <!-- Tabel Guru -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Daftar Guru</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>NIP</th>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>Telepon</th>
                                        <th>Username</th>
                                        <th>Jam Masuk</th>
                                        <th>Jam Pulang</th>
                                        <th>Status Wajah</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($teachers_result->num_rows > 0): ?>
                                        <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(isset($teacher['nip']) ? $teacher['nip'] : ''); ?></td>
                                            <td><?php echo htmlspecialchars(isset($teacher['nama']) ? $teacher['nama'] : ''); ?></td>
                                            <td><?php echo htmlspecialchars(isset($teacher['email']) ? $teacher['email'] : ''); ?></td>
                                            <td><?php echo htmlspecialchars(isset($teacher['telepon']) ? $teacher['telepon'] : ''); ?></td>
                                            <td><?php echo htmlspecialchars(isset($teacher['username']) ? $teacher['username'] : ''); ?></td>
                                            <td><?php echo htmlspecialchars(isset($teacher['jam_masuk']) ? $teacher['jam_masuk'] : ''); ?></td>
                                            <td><?php echo htmlspecialchars(isset($teacher['jam_pulang']) ? $teacher['jam_pulang'] : ''); ?></td>
                                            <td>
                                                <?php if (!empty($teacher['face_encoding'])): ?>
                                                    <span class="badge bg-success">Terdaftar</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Belum Terdaftar</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="face_register.php?id=<?php echo htmlspecialchars($teacher['id']); ?>" class="btn btn-sm btn-info me-1" title="Daftarkan Wajah">
                                                    <i class="fas fa-camera"></i>
                                                </a>
                                                <a href="teachers.php?edit_id=<?php echo htmlspecialchars($teacher['id']); ?>" class="btn btn-sm btn-warning me-1" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-sm btn-danger" onclick="deleteTeacher(<?php echo htmlspecialchars($teacher['id']); ?>)" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">Belum ada data guru</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                        <i class="fas fa-plus me-2"></i>Tambah Guru
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Guru -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTeacherModalLabel">Tambah Guru Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">NIP</label>
                                    <input type="text" class="form-control" name="nip">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Telepon</label>
                                    <input type="text" class="form-control" name="telepon">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jam Masuk <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="jam_masuk" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jam Pulang <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="jam_pulang" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="alamat" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Guru -->
    <div class="modal fade" id="editTeacherModal" tabindex="-1" aria-labelledby="editTeacherModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTeacherModalLabel">Edit Data Guru: <?php echo isset($edit_teacher['nama']) ? htmlspecialchars($edit_teacher['nama']) : 'Tidak Diketahui'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="teacher_id" id="editTeacherId" value="<?php echo isset($edit_teacher['id']) ? htmlspecialchars($edit_teacher['id']) : ''; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="username" value="<?php echo isset($edit_teacher['username']) ? htmlspecialchars($edit_teacher['username']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password (Kosongkan jika tidak ingin mengubah)</label>
                                    <input type="password" class="form-control" name="password">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">NIP</label>
                                    <input type="text" class="form-control" name="nip" value="<?php echo isset($edit_teacher['nip']) ? htmlspecialchars($edit_teacher['nip']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama" value="<?php echo isset($edit_teacher['nama']) ? htmlspecialchars($edit_teacher['nama']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo isset($edit_teacher['email']) ? htmlspecialchars($edit_teacher['email']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Telepon</label>
                                    <input type="text" class="form-control" name="telepon" value="<?php echo isset($edit_teacher['telepon']) ? htmlspecialchars($edit_teacher['telepon']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jam Masuk <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="jam_masuk" value="<?php echo isset($edit_teacher['jam_masuk']) ? htmlspecialchars($edit_teacher['jam_masuk']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jam Pulang <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="jam_pulang" value="<?php echo isset($edit_teacher['jam_pulang']) ? htmlspecialchars($edit_teacher['jam_pulang']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="alamat" rows="3"><?php echo isset($edit_teacher['alamat']) ? htmlspecialchars($edit_teacher['alamat']) : ''; ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Form Hapus (Tersembunyi) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="teacher_id" id="deleteTeacherId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteTeacher(teacherId) {
            if (confirm('Apakah Anda yakin ingin menghapus guru ini?')) {
                document.getElementById('deleteTeacherId').value = teacherId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Buka modal edit secara otomatis jika edit_id ada dan data tersedia
        <?php if (isset($edit_teacher) && !empty($edit_teacher)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
            editModal.show();
        });
        <?php endif; ?>
    </script>
</body>
</html>