<?php
// Tidak ada session_start() atau cek login di sini karena ini untuk tamu
require_once '../config.php'; // Pastikan path ini benar untuk mengakses config.php

// PENYESUAIAN: Query SQL diganti menggunakan LEFT JOIN untuk menggabungkan
// data dari tabel 'sekolah' dan 'kriteria' sesuai dengan ERD.
$base_sql = "SELECT
                s.id_sekolah, s.nama_sekolah, s.alamat, s.total_guru, s.total_murid_aktif,
                k.akreditasi, k.biaya_spp, k.total_fasilitas, k.jarak_jalan_raya, k.nilai_program_unggulan
             FROM
                sekolah s
             LEFT JOIN
                kriteria k ON s.id_sekolah = k.id_sekolah";

$search_query_val = "";
$where_clause = "";

// Logika untuk fitur pencarian
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = mysqli_real_escape_string($koneksi, $_GET['search']);
    // Pencarian berdasarkan nama sekolah atau alamat dari tabel 'sekolah' (aliased as 's')
    $where_clause = " WHERE s.nama_sekolah LIKE '%$search_term%' OR s.alamat LIKE '%$search_term%'";
    $search_query_val = $search_term;
}

$final_sql = $base_sql . $where_clause . " ORDER BY s.nama_sekolah ASC";
$sekolah_result = mysqli_query($koneksi, $final_sql);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Sekolah | SPK Pemilihan SMA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --text-primary: #5a5c69;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table thead th {
            font-weight: 600;
            background-color: var(--secondary-color);
            white-space: nowrap;
        }

        .table tbody td {
            vertical-align: middle;
            white-space: nowrap;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }

        .badge-akreditasi {
            padding: 0.5em 0.7em;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php require_once 'guest_navbar.php'; // Navbar untuk tamu ?>

    <div class="container-fluid py-4">
        <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-school me-2"></i>Daftar Sekolah</h1>

        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0"><i class="fas fa-list-ul me-2"></i>Data Sekolah dan Kriteria</h2>
                <div class="d-flex flex-wrap align-items-center">
                    <form class="d-flex" role="search" method="GET" action="daftar_sekolah_guest.php">
                        <input class="form-control form-control-sm me-1" type="search" placeholder="Cari sekolah..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query_val); ?>">
                        <button class="btn btn-outline-primary btn-sm" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if (!empty($search_query_val)): ?>
                            <a href="daftar_sekolah_guest.php" class="btn btn-outline-danger btn-sm ms-1" title="Hapus Pencarian">
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
                                <th class="text-center">Total Murid</th>
                                <th class="text-center">Biaya SPP</th>
                                <th class="text-center">Total Fasilitas</th>
                                <th class="text-center">Jarak dari Jalan Raya</th>
                                <th class="text-center">Nilai Program Unggulan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($sekolah_result) > 0) {
                                $no = 1;
                                while ($row = mysqli_fetch_assoc($sekolah_result)) {
                                    $akreditasi_label = $row['akreditasi'] ?? 'N/A';
                                    $badge_color = 'secondary';
                                    if($akreditasi_label == 'A') $badge_color = 'success';
                                    else if($akreditasi_label == 'B') $badge_color = 'primary';
                                    else if($akreditasi_label == 'C') $badge_color = 'warning';
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_sekolah']); ?></td>
                                    <td><?php echo htmlspecialchars($row['alamat']); ?></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill badge-akreditasi bg-<?php echo $badge_color; ?>">
                                            <?php echo htmlspecialchars($akreditasi_label); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['total_guru']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars(number_format($row['total_murid_aktif'])); ?></td>
                                    <td class="text-center">Rp <?php echo htmlspecialchars(number_format($row['biaya_spp'] ?? 0, 0, ',', '.')); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars(number_format($row['total_fasilitas'] ?? 0)); ?></td>
                                    <td class="text-center">
                                        <?php
                                        $jarak_km = $row['jarak_jalan_raya'] ?? 0;
                                        if ($jarak_km < 1 && $jarak_km > 0) {
                                            echo htmlspecialchars(number_format($jarak_km * 1000, 0)) . " Meter";
                                        } else {
                                            echo htmlspecialchars(number_format($jarak_km, 2)) . " KM";
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars(number_format($row['nilai_program_unggulan'] ?? 0, 2)); ?></td>
                                </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='10' class='text-center'>Tidak ada data sekolah yang cocok.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>