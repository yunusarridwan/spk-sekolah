<?php
session_start();
require_once '../config.php';
require_once 'navbar.php'; // Ensure navbar.php is included

// Cek apakah user sudah login (implicitly admin)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); // Redirect ke login.php jika belum login
    exit();
}

// Hitung statistik untuk dashboard
$total_sekolah = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM sekolah"))['total'];
// Total kriteria sekarang fixed, tidak perlu query database
$total_kriteria = 5; // Fixed to 5 criteria
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin | SPK Pemilihan SMA Swasta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Define custom color variables for better maintainability */
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --info-color: #36b9cc;
            --dark-gray: #5a5c69;
            --light-gray: #f8f9fc;
        }

        body {
            background-color: var(--light-gray);
            font-family: 'Nunito', sans-serif; /* A modern, clean font */
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .container-fluid {
            flex: 1; /* Allow content to grow and push footer down */
        }

        .h3.text-gray-800 {
            color: var(--dark-gray);
            font-weight: 700; /* Bolder for titles */
        }

        /* Card styles with subtle shadow and transition */
        .card {
            border: none;
            border-radius: 0.75rem; /* More rounded corners */
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08); /* Stronger, softer shadow */
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px); /* Lift effect on hover */
            box-shadow: 0 0.8rem 2rem rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background-color: white; /* White header for a clean look */
            border-bottom: 1px solid #eaecf4;
            padding: 1rem 1.5rem;
            font-weight: 700;
            color: var(--dark-gray);
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
        }

        /* Dashboard statistic cards */
        .dashboard-stat-card .card-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
        }

        .dashboard-stat-card .text-xs {
            font-size: 0.75rem;
            font-weight: 800; /* Extra bold */
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .dashboard-stat-card .h5 {
            font-size: 1.75rem; /* Larger number */
            font-weight: 800; /* Extra bold */
            color: #4e73df; /* Default text color */
        }

        /* Color bars on the left of stat cards */
        .border-start-primary { border-left: 0.25rem solid var(--primary-color) !important; }
        .border-start-success { border-left: 0.25rem solid var(--secondary-color) !important; }
        .border-start-info { border-left: 0.25rem solid var(--info-color) !important; }

        /* Icon circles */
        .icon-circle {
            height: 3.5rem; /* Slightly larger icon circle */
            width: 3.5rem;
            min-width: 3.5rem; /* Ensure it doesn't shrink */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem; /* Larger icon */
            color: white;
        }

        .icon-circle.bg-primary { background-color: var(--primary-color) !important; }
        .icon-circle.bg-success { background-color: var(--secondary-color) !important; }
        .icon-circle.bg-info { background-color: var(--info-color) !important; }

        /* Feature cards */
        .feature-card .card-body {
            min-height: 150px; /* Ensure consistent height */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-start;
        }

        .feature-card h5 {
            font-weight: 700;
            color: var(--dark-gray);
        }

        /* Alert styling */
        .alert-info {
            background-color: #e3f2fd; /* Light blue */
            border-color: #90caf9; /* Medium blue */
            color: #2196f3; /* Darker blue */
            border-radius: 0.5rem;
            padding: 1rem 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <h1 class="h3 mb-4 text-gray-800"><i class="bi bi-speedometer2 me-2"></i>Dashboard Admin</h1>

        <?php if(isset($_SESSION['pesan'])) { ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $_SESSION['pesan']; unset($_SESSION['pesan']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card dashboard-stat-card border-start-primary h-100">
                    <div class="card-body">
                        <div>
                            <div class="text-xs text-primary text-uppercase">Total Sekolah</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_sekolah; ?></div>
                        </div>
                        <div class="col-auto">
                            <div class="icon-circle bg-primary">
                                <i class="bi bi-building text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card dashboard-stat-card border-start-success h-100">
                    <div class="card-body">
                        <div>
                            <div class="text-xs text-success text-uppercase">Total Kriteria</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_kriteria; ?></div>
                        </div>
                        <div class="col-auto">
                            <div class="icon-circle bg-success">
                                <i class="bi bi-list-check text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card dashboard-stat-card border-start-info h-100">
                    <div class="card-body">
                        <div>
                            <div class="text-xs text-info text-uppercase">Metode SPK</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">SAW</div>
                        </div>
                        <div class="col-auto">
                            <div class="icon-circle bg-info">
                                <i class="bi bi-calculator text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card feature-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-trophy me-2"></i>Informasi Ranking</h6>
                        <a href="hasil_ranking.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-list-ol me-1"></i> Lihat Hasil Ranking Lengkap
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Hasil ranking dihitung secara dinamis berdasarkan data terbaru. Klik tombol di atas untuk melihat peringkat sekolah.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="card feature-card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-lightbulb me-2"></i>Tentang Metode SAW</h6>
                    </div>
                    <div class="card-body">
                        <p>Metode Simple Additive Weighting (SAW) adalah metode penjumlahan terbobot dari rating kinerja pada setiap alternatif dari semua atribut.</p>
                        <p class="mb-3">Metode ini digunakan untuk memberikan rekomendasi sekolah terbaik berdasarkan kriteria yang telah ditentukan.</p>
                        <a href="https://en.wikipedia.org/wiki/Weighted_sum_model" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Pelajari Lebih Lanjut
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mb-4">
                <div class="card feature-card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-lightning-charge me-2"></i>Aksi Cepat Admin</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="d-flex align-items-center mb-2">
                                    <h5 class="mb-0"><i class="bi bi-plus-circle me-2 text-success"></i>Tambah Sekolah Baru</h5>
                                </div>
                                <p class="text-secondary">Masukkan data sekolah dan kriteria penilaian untuk sekolah baru.</p>
                                <a href="tambah_sekolah.php" class="btn btn-success"><i class="bi bi-plus me-1"></i> Tambah Sekolah</a>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <h5 class="mb-0"><i class="bi bi-pencil-square me-2 text-info"></i>Kelola Data Sekolah</h5>
                                </div>
                                <p class="text-secondary">Lihat, edit, atau hapus data sekolah yang sudah ada.</p>
                                <a href="daftar_sekolah.php" class="btn btn-info"><i class="bi bi-pencil me-1"></i> Kelola Sekolah</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
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

<script>
function confirmLogout(event) {
    event.preventDefault();

    const modalId = 'logoutModal';
    if (document.getElementById(modalId)) return;

    const confirmBox = document.createElement('div');
    confirmBox.className = 'modal fade';
    confirmBox.id = modalId;
    confirmBox.setAttribute('tabindex', '-1');
    confirmBox.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Keluar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin keluar dari aplikasi?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a href="../logout.php" class="btn btn-danger">Yakin</a>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(confirmBox);

    const modal = new bootstrap.Modal(confirmBox);
    modal.show();

    confirmBox.addEventListener('hidden.bs.modal', () => {
        confirmBox.remove();
    });
}
</script>

<!-- Tambahkan Bootstrap JS jika belum ada -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>