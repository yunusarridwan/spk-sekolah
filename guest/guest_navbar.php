<?php
// guest_navbar.php
// Tidak ada session_start() atau cek login di sini karena ini untuk tamu

// Pastikan session sudah dimulai jika belum (untuk logout)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
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

<nav class="navbar navbar-expand-lg navbar-dark bg-info"> <!-- Menggunakan bg-info agar berbeda dari admin -->
    <div class="container">
        <a class="navbar-brand" href="hasil_ranking.php"><i class="bi bi-mortarboard-fill me-2"></i>SPK Sekolah (Tamu)</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#guestNavbar" aria-controls="guestNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="guestNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="hasil_ranking.php"><i class="bi bi-trophy-fill me-1"></i>Hasil Ranking</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="daftar_sekolah_guest.php"><i class="bi bi-list-ul me-1"></i>Daftar Sekolah</a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <a href="#" class="btn btn-danger" onclick="confirmKeluar(event)">
    <i class="bi bi-box-arrow-right me-1"></i> Keluar
</a>
<!-- Modal Konfirmasi Keluar -->

<div class="modal fade" id="confirmKeluarModal" tabindex="-1" aria-labelledby="confirmKeluarLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
    <div class="modal-header bg-info text-white" style="box-shadow: 0 2px 4px rgba(0,0,0,.1);">
        <h5 class="modal-title" id="confirmKeluarLabel">Konfirmasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        Apakah Anda yakin ingin keluar dari halaman ini?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <a href="../index.php" class="btn btn-danger">Yakin</a>
      </div>
    </div>
  </div>
</div>
</nav>
<script>
    function confirmKeluar(event) {
        event.preventDefault();
        var myModal = new bootstrap.Modal(document.getElementById('confirmKeluarModal'));
        myModal.show();
    }
</script>

<!-- Jika belum ada Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

