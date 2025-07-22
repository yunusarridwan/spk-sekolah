<?php

// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login (implicitly admin)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); // Redirect ke login.php jika belum login
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPK Pemilihan SMA Swasta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* This can be moved to a separate CSS file for better management */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.1); /* Subtle shadow for navbar */
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-mortarboard-fill me-2"></i>SPK Sekolah</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-house-door-fill me-1"></i>Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="daftar_sekolah.php"><i class="bi bi-list-ul me-1"></i>Sekolah</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="hasil_ranking.php"><i class="bi bi-trophy-fill me-1"></i>Hasil Ranking</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="export_data.php"><i class="bi bi-download me-1"></i>Export Data</a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-light disabled">
                    <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                </button>


                <a href="../logout.php" class="btn btn-danger" onclick="confirmLogout(event)">
    <i class="bi bi-box-arrow-right me-1"></i> Logout
</a>


            </div>
        </div>
    </div>
</nav>