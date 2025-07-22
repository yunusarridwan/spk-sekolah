<?php
session_start();
require_once 'config.php';

// Redirect jika sudah login sebagai admin
if (isset($_SESSION['user_id'])) {
    header("Location: admin/dashboard.php?login=success");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = $_POST['password']; // Ambil password mentah

    $query = "SELECT id, username, password FROM users WHERE username = '$username'";
    $result = mysqli_query($koneksi, $query);

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        // Verifikasi password menggunakan password_verify()
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: admin/dashboard.php?login=success");
            exit();
        } else {
            $error = "Username atau password salah!";
        }
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin | SPK Pemilihan SMA Swasta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Define custom color variables for better maintainability */
        :root {
            --primary-color: #4e73df;
            --primary-dark: #2e59d9;
            --accent-color: #f6c23e; /* Warna cerah untuk penekanan */
            --gradient-start: #4e73df;
            --gradient-end: #224abe;
            --text-dark: #5a5c69;
            --text-muted: #6e707e;
            --bg-light: #f8f9fc;
        }

        body {
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Nunito', sans-serif; /* Using a more generic sans-serif for broader compatibility */
            color: white; /* Default text color for the background area */
            overflow: hidden; /* Prevent scrollbar if content slightly exceeds viewport */
        }

        .card {
            border: none;
            border-radius: 1.5rem; /* More pronounced rounded corners */
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.2); /* Stronger, softer shadow */
            overflow: hidden; /* Ensures border-radius applies to children */
            background-color: white; /* Explicitly white background */
        }

        .card-body {
            padding: 0; /* Remove default padding as p-5 is used on inner divs */
        }

        /* Left section (info part) */
        .login-info-section {
            background-color: var(--bg-light);
            padding: 3rem; /* More padding */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            height: 100%; /* Ensure it fills height */
        }

        .login-info-section .login-icon {
            font-size: 4.5rem; /* Larger icon */
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            animation: bounceIn 1s ease-out; /* Simple entry animation */
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.1); opacity: 0.8; }
            70% { transform: scale(0.9); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }

        .login-info-section .brand-name {
            font-size: 1.8rem; /* Slightly larger brand name */
            font-weight: 800; /* Extra bold */
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .login-info-section p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .login-info-section .features i {
            color: var(--primary-color);
            margin-right: 0.5rem;
            font-size: 1.1rem;
        }
        .login-info-section .features span {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Right section (form part) */
        .login-form-section {
            padding: 3rem; /* More padding */
        }

        .login-form-section .h4 {
            color: var(--text-dark);
            font-weight: 700;
            margin-bottom: 2rem; /* More space below heading */
        }

        /* Remove form-floating specific label styling if not using form-floating */
        /* .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label,
        .form-floating > .form-select:focus ~ label,
        .form-floating > .form-select:not(:placeholder-shown) ~ label {
            color: var(--primary-color);
        } */

        .form-control {
            border-radius: 0.75rem; /* Match card radius */
            padding: 1rem 1.25rem;
            height: auto; /* Allow padding to define height */
            font-size: 1rem;
            border: 1px solid #ced4da;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 0.75rem; /* Match input fields */
            padding: 0.75rem 1.25rem;
            font-size: 1.1rem;
            font-weight: 700;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px); /* Subtle lift on hover */
        }

        .password-toggle {
            position: absolute;
            right: 1.25rem; /* Align with input padding */
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 10;
            color: var(--text-muted);
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .alert-danger {
            background-color: #f8d7da; /* Light red */
            border-color: #f5c6cb; /* Medium red */
            color: #721c24; /* Dark red */
            border-radius: 0.75rem;
            font-size: 0.9rem;
        }

        hr {
            margin-top: 2rem;
            margin-bottom: 2rem;
            border-top: 1px solid #eaecf4;
        }

        .footer-text-outside {
            margin-top: 2rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .col-lg-6.d-none.d-lg-block {
                display: none !important; /* Hide info section on smaller screens */
            }
            .col-lg-6 {
                width: 100%; /* Full width for login form */
            }
            .card {
                max-width: 450px; /* Constrain card width on small screens */
            }
            .login-form-section, .login-info-section {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['login']) && $_GET['login'] === 'success') : ?>
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            const alertBox = document.createElement('div');
            alertBox.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
            alertBox.style.zIndex = '9999';
            alertBox.style.minWidth = '300px';
            alertBox.innerHTML = `
                <strong>Login Berhasil!</strong> Selamat datang kembali 👋
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.body.appendChild(alertBox);

            // Auto-close setelah 3 detik
            setTimeout(() => {
                const alert = bootstrap.Alert.getOrCreateInstance(alertBox);
                alert.close();
            }, 3000);
        });
    </script>
<?php endif; ?>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-12 col-md-9">
                <div class="card o-hidden border-0 shadow-lg my-5">
                    <div class="card-body p-0">
                        <div class="row">
                            <div class="col-lg-6 d-none d-lg-block">
                                <div class="login-info-section">
                                    <div class="login-icon mb-4">
                                        <i class="bi bi-building-check"></i>
                                    </div>
                                    <h1 class="brand-name">SPK Pemilihan SMA Swasta</h1>
                                    <p class="mb-4">Sistem Pendukung Keputusan untuk membantu Anda memilih SMA Swasta terbaik sesuai dengan kriteria yang diinginkan.</p>
                                    <div class="mt-3 features">
                                        <p><i class="bi bi-award"></i> <span>Objektif dalam Penilaian</span></p>
                                        <p><i class="bi bi-graph-up-arrow"></i> <span>Hasil yang Terukur</span></p>
                                        <p><i class="bi bi-lightning-charge"></i> <span>Proses Cepat dan Efisien</span></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="login-form-section">
                                    <div class="text-center">
                                        <h1 class="h4 text-gray-900 mb-4">Selamat Datang Kembali!</h1>
                                    </div>

                                    <?php if(!empty($error)) { ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php } ?>

                                    <form class="user" method="POST">
                                        <div class="mb-4"> <label for="username" class="form-label visually-hidden">Username</label> <div class="input-group">
                                                <span class="input-group-text rounded-start-pill border-end-0" style="background-color: var(--primary-color); color: white; border-color: var(--primary-color);">
                                                    <i class="bi bi-person"></i>
                                                </span>
                                                <input type="text" name="username" class="form-control rounded-end-pill" id="username" placeholder="Masukkan Username Anda" required>
                                            </div>
                                        </div>

                                        <div class="mb-4 password-container">
                                            <label for="password" class="form-label visually-hidden">Password</label> <div class="input-group">
                                                <span class="input-group-text rounded-start-pill border-end-0" style="background-color: var(--primary-color); color: white; border-color: var(--primary-color);">
                                                    <i class="bi bi-lock"></i>
                                                </span>
                                                <input type="password" name="password" class="form-control rounded-end-pill" id="password" placeholder="Masukkan Password Anda" required>
                                                <span class="password-toggle" onclick="togglePassword()">
                                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                                </span>
                                            </div>
                                        </div>

                                  

                                        <button type="submit" class="btn btn-primary btn-user btn-block w-100 py-3">
                                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                                        </button>
                                    </form>
                                    <hr>
                                    <div class="text-center">
                                        <a href="index.php" class="text-primary text-decoration-none small">
                                            <i class="bi bi-arrow-left-circle me-1"></i> Kembali ke Halaman Utama (Publik)
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center footer-text-outside">
                    <small>
                        Copyright &copy; SPK Pemilihan SMA Swasta <?php echo date('Y'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password'); // Perbarui ID ke 'password'
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>