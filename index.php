<?php
session_start();
require_once 'config.php';

// Jika sudah login sebagai admin, langsung arahkan ke dashboard admin
if (isset($_SESSION['user_id'])) {
    header("Location: admin/dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Datang | SPK Pemilihan SMA Swasta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
        }

        body {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            height: 100vh;
            display: flex; /* Reverted to original flex display */
            align-items: center;
            justify-content: center;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: white;
            text-align: center;
        }

        /* Navbar styles removed as per request to revert to original design */

        .welcome-container {
            max-width: 600px;
            padding: 40px;
            border-radius: 1rem;
            background-color: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0.5rem 1rem 0 rgba(0, 0, 0, 0.2);
            /* margin-top adjustment removed */
        }

        .welcome-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #f6c23e; /* Warning color for a welcoming feel */
        }

        .btn-custom {
            padding: 15px 30px;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 0.5rem;
            margin: 10px;
            transition: all 0.3s ease;
        }

        .btn-admin {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .btn-admin:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-guest {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: white;
        }

        .btn-guest:hover {
            background-color: #13855c;
            border-color: #13855c;
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .footer-text {
            margin-top: 30px;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <div class="welcome-icon">
            <i class="fas fa-school"></i>
        </div>
        <h1 class="mb-3">Selamat Datang di SPK Pemilihan SMA Swasta</h1>
        <p class="lead mb-5">Sistem Pendukung Keputusan untuk membantu Anda menemukan SMA Swasta terbaik.</p>

        <div class="d-flex justify-content-center flex-wrap">
            <a href="login.php" class="btn btn-custom btn-admin">
                <i class="fas fa-user-shield me-2"></i> Login Admin
            </a>
            <a href="guest/hasil_ranking.php" class="btn btn-custom btn-guest">
                <i class="fas fa-chart-bar me-2"></i> Masuk Sebagai Tamu
            </a>
        </div>

        <div class="footer-text">
            <span>Copyright &copy; SPK Pemilihan SMA Swasta <?php echo date('Y'); ?></span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
