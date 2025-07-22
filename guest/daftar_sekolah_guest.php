<?php
// Tidak ada session_start() atau cek login di sini karena ini untuk tamu
require_once '../config.php'; // Pastikan path ini benar untuk mengakses config.php

// Ambil daftar sekolah
$search_query = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = mysqli_real_escape_string($koneksi, $_GET['search']);
    $sekolah_sql = "SELECT * FROM sekolah WHERE nama_sekolah LIKE '%$search_query%' OR alamat LIKE '%$search_query%' ORDER BY nama_sekolah ASC";
} else {
    $sekolah_sql = "SELECT * FROM sekolah ORDER BY nama_sekolah ASC";
}
$sekolah = mysqli_query($koneksi, $sekolah_sql);

// Define fixed criteria details for display (matching spk.sql and other PHP files)
$fixed_kriteria_details = [
    1 => ['nama_kriteria' => 'Akreditasi', 'tipe' => 'benefit', 'input_type' => 'select', 'options' => ['A' => 4, 'B' => 3, 'C' => 2]],
    2 => ['nama_kriteria' => 'Biaya SPP', 'tipe' => 'cost', 'input_type' => 'number'],
    3 => ['nama_kriteria' => 'Fasilitas', 'tipe' => 'benefit', 'input_type' => 'number'],
    4 => ['nama_kriteria' => 'Jarak Sekolah dengan Jalan Raya', 'tipe' => 'cost', 'input_type' => 'number'],
    5 => ['nama_kriteria' => 'Program Unggulan', 'tipe' => 'benefit', 'input_type' => 'number'],
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Sekolah | Tamu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --text-primary: #5a5c69;
            --text-secondary: #858796;
            --border-color: #e3e6f0;
        }

        body {
            background-color: var(--secondary-color);
            color: var(--text-primary);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            color: var(--primary-color);
            font-weight: 700;
            padding: 1rem 1.25rem;
            border-top-left-radius: 10px !important;
            border-top-right-radius: 10px !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow-x: auto;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            font-weight: 600;
            background-color: var(--secondary-color);
            border-top: none;
            vertical-align: middle;
            padding: 1rem 0.75rem;
            white-space: nowrap;
        }

        .table tbody td {
            vertical-align: middle;
            padding: 0.75rem;
            white-space: nowrap;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }

        .badge-akreditasi {
            padding: 0.5em 0.7em;
            border-radius: 0.35rem;
            font-weight: 600;
            font-size: 0.75rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table thead th, .table tbody td {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .card-header h2 {
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'guest_navbar.php'; // Include the new guest navbar ?>

    <div class="container-fluid py-4">
        <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-school me-2"></i>Daftar Sekolah</h1>

        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0"><i class="fas fa-list-ul me-2"></i>Data Sekolah</h2>
                <div class="d-flex flex-wrap align-items-center">
                    <form class="d-flex" role="search" method="GET">
                        <input class="form-control form-control-sm me-1" type="search" placeholder="Cari sekolah..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-outline-secondary btn-sm" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if (!empty($search_query)): ?>
                            <a href="daftar_sekolah_guest.php" class="btn btn-outline-danger btn-sm ms-1" title="Clear Search">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Sekolah</th>
                                <th>Alamat</th>
                                <th class="text-center">Akreditasi</th>
                                <th class="text-center">Total Guru</th>
                                <th class="text-center">Total Murid Aktif</th>
                                <th class="text-center">Biaya SPP</th>
                                <th class="text-center">Fasilitas</th>
                                <th class="text-center">Jarak Jalan Raya</th>
                                <th class="text-center">Program Unggulan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($sekolah) > 0) {
                                $no = 1;
                                while ($row = mysqli_fetch_assoc($sekolah)) {
                                    $akreditasi_label = $row['akreditasi'];
                                    $badge_color = '';
                                    if($akreditasi_label == 'A') $badge_color = 'success';
                                    else if($akreditasi_label == 'B') $badge_color = 'primary';
                                    else if($akreditasi_label == 'C') $badge_color = 'warning';
                                    else $badge_color = 'secondary';
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_sekolah']); ?></td>
                                    <td><?php echo htmlspecialchars($row['alamat']); ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-akreditasi bg-<?php echo $badge_color; ?>">
                                            <?php echo htmlspecialchars($akreditasi_label); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['total_guru']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars(number_format($row['total_murid_aktif'], 2)); ?></td>
                                    <td>Rp <?php echo htmlspecialchars(number_format($row['biaya_spp'], 0, ',', '.')); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars(number_format($row['fasilitas'])); ?></td>
                                    <td class="text-center">
                                        <?php
                                        $jarak_km = $row['jarak_jalan_raya'];
                                        if ($jarak_km < 1) {
                                            $jarak_meter = $jarak_km * 1000;
                                            echo htmlspecialchars(number_format($jarak_meter, 0)) . " Meter";
                                        } else {
                                            echo htmlspecialchars(number_format($jarak_km, 3)) . " KM";
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars(number_format($row['program_unggulan'], 2)); ?></td>
                                </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='10' class='text-center'>Tidak ada data sekolah</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if (mysqli_num_rows($sekolah) == 0 && !empty($search_query)) { ?>
                    <div class="alert alert-info text-center mt-3" role="alert">
                        Tidak ditemukan sekolah dengan kata kunci "<?php echo htmlspecialchars($search_query); ?>".
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
