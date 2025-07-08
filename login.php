<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if ($_SESSION["role"] == "admin") {
        header("location: admin/dashboard.php");
    } else {
        header("location: guru/dashboard.php");
    }
    exit;
}

require_once "config.php";

$username = $password = "";
$username_err = $password_err = $login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["username"]))) {
        $username_err = "Silakan masukkan nama pengguna.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    if (empty(trim($_POST["password"]))) {
        $password_err = "Silakan masukkan kata sandi.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    if (empty($username_err) && empty($password_err)) {
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
        
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            
            $param_username = $username;
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $row = $result->fetch_array(MYSQLI_ASSOC);
                    
                    $id = $row["id"];
                    $username = $row["username"];
                    $hashed_password = $row["password"];
                    $role = $row["role"];
                    
                    if (password_verify($password, $hashed_password)) {
                        session_start();
                        
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $id;
                        $_SESSION["username"] = $username;
                        $_SESSION["role"] = $role;
                        
                        if ($role == "admin") {
                            header("location: admin/dashboard.php");
                        } else {
                            header("location: guru/dashboard.php");
                        }
                    } else {
                        $login_err = "Nama pengguna atau kata sandi tidak valid.";
                    }
                } else {
                    $login_err = "Nama pengguna atau kata sandi tidak valid.";
                }
            } else {
                $login_err = "Terjadi kesalahan. Silakan coba lagi.";
            }

            $stmt->close();
        }
    }
    
    $mysqli->close();
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
    <title>Login - TK Pelangi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f1f3f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: #fff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            text-align: center;
            max-width: 400px;
            width: 90%;
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }

        .login-container:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
            transform: translateY(-3px);
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

        h1 {
            color: #2c3e50;
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .subtext {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }

        .date-time {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .password-container {
            position: relative;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }

        .password-container input[type="password"] {
            padding-right: 60px;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 0.85rem;
            cursor: pointer;
            color: #007bff;
            padding: 0 5px;
            transition: color 0.3s ease;
        }

        .toggle-password:hover {
            color: #0056b3;
        }

        button[type="submit"] {
            width: 100%;
            padding: 0.75rem;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        button[type="submit"]:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .error-message {
            color: #dc3545;
            background: #f8d7da;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }

        .invalid-feedback {
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <h1>TK Pelangi</h1>
        <div class="subtext">Sistem Absensi</div>
        <div class="date-time">
            <i class="fas fa-calendar me-2"></i>
            <?php echo htmlspecialchars($formatted_date); ?>
            <i class="fas fa-clock me-2"></i>
            <?php echo htmlspecialchars($formatted_time); ?>
        </div>
        
        <?php 
        if (!empty($login_err)) {
            echo '<div class="error-message">' . htmlspecialchars($login_err) . '</div>';
        }        
        ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label class="form-label">Nama Pengguna <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" placeholder="Masukkan nama pengguna" required>
                <?php if (!empty($username_err)): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($username_err); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label class="form-label">Kata Sandi <span class="text-danger">*</span></label>
                <div class="password-container">
                    <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Masukkan kata sandi" required>
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <span id="toggle-icon">Tampilkan</span>
                    </button>
                </div>
                <?php if (!empty($password_err)): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($password_err); ?></div>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-sign-in-alt me-2"></i>Masuk</button>
        </form>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.textContent = 'Sembunyikan';
            } else {
                passwordInput.type = 'password';
                toggleIcon.textContent = 'Tampilkan';
            }
        }
    </script>
</body>
</html>