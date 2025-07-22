<?php
session_start();
require_once '../config.php';

// Include PHPOffice/PhpSpreadsheet Autoloader
require_once '../vendor/autoload.php'; // Adjust path if necessary

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv; // If you still want CSV export via PhpSpreadsheet

// Cek apakah user sudah login (implicitly admin)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Define fixed criteria and their properties (weights and types) for SAW calculation
$fixed_kriteria_saw = [
    1 => ['nama_kriteria' => 'Akreditasi', 'bobot' => 0.25, 'tipe' => 'benefit', 'options' => ['A' => 4, 'B' => 3, 'C' => 2]],
    2 => ['nama_kriteria' => 'Biaya SPP', 'bobot' => 0.30, 'tipe' => 'cost'],
    3 => ['nama_kriteria' => 'Fasilitas', 'bobot' => 0.15, 'tipe' => 'benefit'],
    4 => ['nama_kriteria' => 'Jarak Sekolah dengan Jalan Raya', 'bobot' => 0.10, 'tipe' => 'cost'],
    5 => ['nama_kriteria' => 'Program Unggulan', 'bobot' => 0.20, 'tipe' => 'benefit'],
];

/**
 * Fungsi untuk export data menggunakan PHPOffice/PhpSpreadsheet
 *
 * @param mysqli $koneksi Koneksi database MySQLi.
 * @param string $filename Nama file yang akan diunduh.
 * @param string $data_type Tipe data yang akan diekspor (sekolah, kriteria, penilaian, ranking, semua).
 * @param array $fixed_kriteria_saw Array kriteria SAW tetap.
 * @param string $output_format Format output (e.g., 'Xlsx', 'Csv').
 */
function exportData($koneksi, $filename, $data_type, $fixed_kriteria_saw, $output_format = 'Xlsx') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [];
    $data_rows = [];

    if ($data_type == 'sekolah') {
        $sekolah_query = mysqli_query($koneksi, "SELECT * FROM sekolah ORDER BY nama_sekolah ASC");
        $headers = ['ID Sekolah', 'Nama Sekolah', 'Alamat', 'Akreditasi', 'Total Guru', 'Total Murid Aktif', 'Biaya SPP', 'Fasilitas', 'Jarak Jalan Raya (KM)', 'Program Unggulan'];
        while ($row = mysqli_fetch_assoc($sekolah_query)) {
            $data_rows[] = [
                $row['id_sekolah'],
                $row['nama_sekolah'],
                $row['alamat'],
                $row['akreditasi'],
                $row['total_guru'],
                $row['total_murid_aktif'],
                (float)$row['biaya_spp'], // Hapus number_format untuk biaya SPP
                (float)$row['fasilitas'],
                (float)$row['jarak_jalan_raya'], // Hapus number_format untuk jarak jalan raya
                (float)$row['program_unggulan']
            ];
        }
    } elseif ($data_type == 'kriteria') {
        $kriteria_query = mysqli_query($koneksi, "SELECT * FROM kriteria ORDER BY id_kriteria ASC");
        $headers = ['ID Kriteria', 'Nama Kriteria'];
        while ($row = mysqli_fetch_assoc($kriteria_query)) {
            $data_rows[] = [$row['id_kriteria'], $row['nama_kriteria']];
        }
    } elseif ($data_type == 'penilaian') {
    $query = "SELECT s.nama_sekolah, k.nama_kriteria, p.nilai
              FROM penilaian p
              JOIN sekolah s ON p.id_sekolah = s.id_sekolah
              JOIN kriteria k ON p.id_kriteria = k.id_kriteria
              ORDER BY s.nama_sekolah ASC, k.nama_kriteria ASC";
    $result = mysqli_query($koneksi, $query);
    $headers = ['Nama Sekolah', 'Nama Kriteria', 'Nilai'];
    while ($row = mysqli_fetch_assoc($result)) {
        // Hapus number_format dan gunakan float casting langsung
        $nilai_formatted = is_numeric($row['nilai']) ? (float)$row['nilai'] : $row['nilai'];
        $data_rows[] = [$row['nama_sekolah'], $row['nama_kriteria'], $nilai_formatted];
    }
    } elseif ($data_type == 'ranking') {
        $sekolah_data = [];
        $sekolah_query = mysqli_query($koneksi, "SELECT * FROM sekolah");
        while ($row = mysqli_fetch_assoc($sekolah_query)) {
            $sekolah_data[$row['id_sekolah']] = $row;
        }

        $penilaian_data = [];
        $penilaian_query = mysqli_query($koneksi, "SELECT id_sekolah, id_kriteria, nilai FROM penilaian");
        while ($row = mysqli_fetch_assoc($penilaian_query)) {
            $penilaian_data[$row['id_sekolah']][$row['id_kriteria']] = $row['nilai'];
        }

        $ranking_results = [];
        if (!empty($sekolah_data) && !empty($penilaian_data)) {
            $normalized_scores = [];
            $max_min_values = [];

            foreach ($fixed_kriteria_saw as $id_kriteria => $kriteria_info) {
                $tipe = $kriteria_info['tipe'];
                if ($tipe == 'benefit') {
                    $max_min_values[$id_kriteria] = -INF;
                } else {
                    $max_min_values[$id_kriteria] = INF;
                }
                foreach ($sekolah_data as $id_sekolah => $s_data) {
                    if (isset($penilaian_data[$id_sekolah][$id_kriteria])) {
                        $nilai = $penilaian_data[$id_sekolah][$id_kriteria];
                        if ($tipe == 'benefit') {
                            $max_min_values[$id_kriteria] = max($max_min_values[$id_kriteria], $nilai);
                        } else {
                            $max_min_values[$id_kriteria] = min($max_min_values[$id_kriteria], $nilai);
                        }
                    }
                }
            }

            foreach ($sekolah_data as $id_sekolah => $s_data) {
                $normalized_scores[$id_sekolah] = [];
                foreach ($fixed_kriteria_saw as $id_kriteria => $kriteria_info) {
                    if (isset($penilaian_data[$id_sekolah][$id_kriteria])) {
                        $nilai = $penilaian_data[$id_sekolah][$id_kriteria];
                        $tipe = $kriteria_info['tipe'];
                        $max_min_val = $max_min_values[$id_kriteria];

                        if ($max_min_val == 0 && $nilai == 0) {
                            $normalized_scores[$id_sekolah][$id_kriteria] = 0;
                        } elseif ($tipe == 'benefit') {
                            $normalized_scores[$id_sekolah][$id_kriteria] = ($max_min_val == 0) ? 0 : ($nilai / $max_min_val);
                        } else {
                            $normalized_scores[$id_sekolah][$id_kriteria] = ($nilai == 0) ? 0 : ($max_min_val / $nilai);
                        }
                    } else {
                        $normalized_scores[$id_sekolah][$id_kriteria] = 0;
                    }
                }
            }

            foreach ($sekolah_data as $id_sekolah => $s_data) {
                $total_skor = 0;
                foreach ($fixed_kriteria_saw as $id_kriteria => $kriteria_info) {
                    $bobot = $kriteria_info['bobot'];
                    $normalized_val = $normalized_scores[$id_sekolah][$id_kriteria];
                    $total_skor += ($normalized_val * $bobot);
                }
                $ranking_results[$id_sekolah] = [
                    'total_skor' => $total_skor,
                    'sekolah_data' => $s_data
                ];
            }

            uasort($ranking_results, function($a, $b) {
                return $b['total_skor'] <=> $a['total_skor'];
            });

            $rank = 1;
            foreach ($ranking_results as $id_sekolah => $data) {
                $ranking_results[$id_sekolah]['peringkat'] = $rank++;
            }
        }

        $headers = ['Peringkat', 'Nama Sekolah', 'Akreditasi', 'Total Skor', 'Total Guru', 'Total Murid Aktif', 'Biaya SPP', 'Fasilitas', 'Jarak Jalan Raya (KM)', 'Program Unggulan'];
        foreach ($ranking_results as $result) {
            $row = $result['sekolah_data'];
            $data_rows[] = [
                $result['peringkat'],
                $row['nama_sekolah'],
                $row['akreditasi'],
                round($result['total_skor'], 4), // Gunakan round() daripada number_format()
                $row['total_guru'],
                $row['total_murid_aktif'],
                (float)$row['biaya_spp'], // Hapus number_format
                (float)$row['fasilitas'],
                (float)$row['jarak_jalan_raya'], // Hapus number_format
                (float)$row['program_unggulan']
            ];
        }
    } elseif ($data_type == 'semua') {
        $sekolah_query = mysqli_query($koneksi, "SELECT * FROM sekolah ORDER BY nama_sekolah ASC");
        $sekolah_data_all = [];
        while ($row = mysqli_fetch_assoc($sekolah_query)) {
            $sekolah_data_all[$row['id_sekolah']] = $row;
        }

        $penilaian_query_all = mysqli_query($koneksi, "SELECT id_sekolah, id_kriteria, nilai FROM penilaian");
        $penilaian_data_all = [];
        while ($row = mysqli_fetch_assoc($penilaian_query_all)) {
            $penilaian_data_all[$row['id_sekolah']][$row['id_kriteria']] = $row['nilai'];
        }

        $ranking_results_all = [];
        if (!empty($sekolah_data_all) && !empty($penilaian_data_all)) {
            $normalized_scores_all = [];
            $max_min_values_all = [];

            foreach ($fixed_kriteria_saw as $id_kriteria => $kriteria_info) {
                $tipe = $kriteria_info['tipe'];
                if ($tipe == 'benefit') {
                    $max_min_values_all[$id_kriteria] = -INF;
                } else {
                    $max_min_values_all[$id_kriteria] = INF;
                }
                foreach ($sekolah_data_all as $id_sekolah => $s_data) {
                    if (isset($penilaian_data_all[$id_sekolah][$id_kriteria])) {
                        $nilai = $penilaian_data_all[$id_sekolah][$id_kriteria];
                        if ($tipe == 'benefit') {
                            $max_min_values_all[$id_kriteria] = max($max_min_values_all[$id_kriteria], $nilai);
                        } else {
                            $max_min_values_all[$id_kriteria] = min($max_min_values_all[$id_kriteria], $nilai);
                        }
                    }
                }
            }

            foreach ($sekolah_data_all as $id_sekolah => $s_data) {
                $normalized_scores_all[$id_sekolah] = [];
                foreach ($fixed_kriteria_saw as $id_kriteria => $kriteria_info) {
                    if (isset($penilaian_data_all[$id_sekolah][$id_kriteria])) {
                        $nilai = $penilaian_data_all[$id_sekolah][$id_kriteria];
                        $tipe = $kriteria_info['tipe'];
                        $max_min_val = $max_min_values_all[$id_kriteria];

                        if ($max_min_val == 0 && $nilai == 0) {
                            $normalized_scores_all[$id_sekolah][$id_kriteria] = 0;
                        } elseif ($tipe == 'benefit') {
                            $normalized_scores_all[$id_sekolah][$id_kriteria] = ($max_min_val == 0) ? 0 : ($nilai / $max_min_val);
                        } else {
                            $normalized_scores_all[$id_sekolah][$id_kriteria] = ($nilai == 0) ? 0 : ($max_min_val / $nilai);
                        }
                    } else {
                        $normalized_scores_all[$id_sekolah][$id_kriteria] = 0;
                    }
                }
            }

            foreach ($sekolah_data_all as $id_sekolah => $s_data) {
                $total_skor = 0;
                foreach ($fixed_kriteria_saw as $id_kriteria => $kriteria_info) {
                    $bobot = $kriteria_info['bobot'];
                    $normalized_val = $normalized_scores_all[$id_sekolah][$id_kriteria];
                    $total_skor += ($normalized_val * $bobot);
                }
                $ranking_results_all[$id_sekolah] = [
                    'total_skor' => $total_skor,
                    'sekolah_data' => $s_data
                ];
            }

            uasort($ranking_results_all, function($a, $b) {
                return $b['total_skor'] <=> $a['total_skor'];
            });

            $rank_all = 1;
            foreach ($ranking_results_all as $id_sekolah => $data) {
                $ranking_results_all[$id_sekolah]['peringkat'] = $rank_all++;
            }
        }

$headers = ['ID Sekolah', 'Nama Sekolah', 'Alamat', 'Akreditasi', 'Total Guru', 'Total Murid Aktif', 'Biaya SPP', 'Fasilitas', 'Jarak Jalan Raya (KM)', 'Program Unggulan'];
foreach ($fixed_kriteria_saw as $kriteria_info) {
    $headers[] = 'Nilai ' . $kriteria_info['nama_kriteria'];
}
$headers[] = 'Total Skor SAW';
$headers[] = 'Peringkat SAW';

foreach ($ranking_results_all as $id_sekolah => $result) {
    $row = $result['sekolah_data'];
    $data_row = [
        $row['id_sekolah'],
        $row['nama_sekolah'],
        $row['alamat'],
        $row['akreditasi'],
        $row['total_guru'],
        $row['total_murid_aktif'],
        (float)$row['biaya_spp'], // Hapus number_format
        (float)$row['fasilitas'],
        (float)$row['jarak_jalan_raya'], // Hapus number_format
        (float)$row['program_unggulan']
    ];

    foreach ($fixed_kriteria_saw as $id_kriteria => $kriteria_info) {
        $nilai_penilaian = $penilaian_data_all[$id_sekolah][$id_kriteria] ?? '';
        $display_value = '';
        if (is_numeric($nilai_penilaian)) {
            if ($id_kriteria == 1) { // Akreditasi
                $akreditasi_map = isset($fixed_kriteria_saw[1]['options']) ? array_flip($fixed_kriteria_saw[1]['options']) : [];
                $display_value = $akreditasi_map[$nilai_penilaian] ?? $nilai_penilaian;
            } else {
                // Untuk semua kriteria lainnya, gunakan nilai asli tanpa number_format
                $display_value = (float)$nilai_penilaian;
            }
        } else {
            $display_value = $nilai_penilaian;
        }
        $data_row[] = $display_value;
    }

    $data_row[] = round($result['total_skor'], 4); // Gunakan round()
    $data_row[] = $result['peringkat'];
    $data_rows[] = $data_row;
}
    } else {
        $_SESSION['error'] = "Tipe ekspor tidak valid atau tidak ada data yang ditemukan.";
        return;
    }

    // Set headers
    if ($output_format == 'Xlsx') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
    } elseif ($output_format == 'Csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(','); // Set delimiter for CSV
        $writer->setEnclosure('"');  // Set enclosure for CSV
        $writer->setLineEnding("\r\n"); // Set line ending for CSV
        $writer->setSheetIndex(0); // Only write the first sheet for CSV
    } else {
        $_SESSION['error'] = "Format output tidak didukung.";
        return;
    }

    $sheet->fromArray($headers, NULL, 'A1');
    $sheet->fromArray($data_rows, NULL, 'A2');

    $writer->save('php://output');
    exit();
}

// Process export requests
if (isset($_POST['export_sekolah'])) {
    exportData($koneksi, 'data_sekolah_' . date('Y-m-d'), 'sekolah', $fixed_kriteria_saw, 'Xlsx');
} elseif (isset($_POST['export_kriteria'])) {
    exportData($koneksi, 'data_kriteria_' . date('Y-m-d'), 'kriteria', $fixed_kriteria_saw, 'Xlsx');
} elseif (isset($_POST['export_penilaian'])) {
    exportData($koneksi, 'data_penilaian_' . date('Y-m-d'), 'penilaian', $fixed_kriteria_saw, 'Xlsx');
} elseif (isset($_POST['export_ranking'])) {
    exportData($koneksi, 'data_ranking_' . date('Y-m-d'), 'ranking', $fixed_kriteria_saw, 'Xlsx');
} elseif (isset($_POST['export_semua'])) {
    exportData($koneksi, 'data_lengkap_spk_' . date('Y-m-d'), 'semua', $fixed_kriteria_saw, 'Xlsx');
}

// Only include navbar and render HTML if no export action was triggered
require_once 'navbar.php'; // This must be after the export logic
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data | SPK Pemilihan SMA Swasta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 0.75rem 1.25rem;
            color: #4e73df;
            font-weight: 700;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
        .btn-success {
            background-color: #1cc88a;
            border-color: #1cc88a;
        }
        .btn-success:hover {
            background-color: #13855c;
            border-color: #13855c;
        }
        .export-option-card {
            transition: transform 0.2s;
        }
        .export-option-card:hover {
            transform: translateY(-5px);
        }
        .footer {
            padding: 1.5rem;
            color: #858796;
            background-color: white;
            border-top: 1px solid #e3e6f0;
            margin-top: 3rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-file-export me-2"></i>Export Data</h1>

        <?php if(isset($_SESSION['pesan'])) { ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['pesan']; unset($_SESSION['pesan']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php } ?>

        <?php if(isset($_SESSION['error'])) { ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title m-0">Pilih Data untuk Diekspor</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Anda dapat mengekspor berbagai jenis data dari sistem SPK ini ke dalam format **Excel (XLSX)**. File XLSX dapat dibuka dengan aplikasi spreadsheet seperti Microsoft Excel atau Google Sheets.
                        </div>

                        <div class="row">
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 export-option-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-school fa-3x text-primary mb-3"></i>
                                        <h5 class="card-title">Data Sekolah</h5>
                                        <p class="card-text text-muted">Informasi dasar sekolah, termasuk akreditasi, guru, murid aktif, SPP, total fasilitas, jarak, dan program unggulan.</p>
                                        <form method="post">
                                            <button type="submit" name="export_sekolah" class="btn btn-primary btn-sm mt-2">
                                                <i class="fas fa-file-excel me-1"></i> Download XLSX
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 export-option-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-list-check fa-3x text-success mb-3"></i>
                                        <h5 class="card-title">Data Kriteria</h5>
                                        <p class="card-text text-muted">Daftar kriteria yang digunakan dalam sistem SPK.</p>
                                        <form method="post">
                                            <button type="submit" name="export_kriteria" class="btn btn-primary btn-sm mt-2">
                                                <i class="fas fa-file-excel me-1"></i> Download XLSX
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 export-option-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-clipboard-list fa-3x text-info mb-3"></i>
                                        <h5 class="card-title">Data Penilaian</h5>
                                        <p class="card-text text-muted">Nilai setiap sekolah pada masing-masing kriteria.</p>
                                        <form method="post">
                                            <button type="submit" name="export_penilaian" class="btn btn-primary btn-sm mt-2">
                                                <i class="fas fa-file-excel me-1"></i> Download XLSX
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 export-option-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-chart-bar fa-3x text-warning mb-3"></i>
                                        <h5 class="card-title">Data Ranking</h5>
                                        <p class="card-text text-muted">Hasil perhitungan ranking sekolah berdasarkan metode SAW.</p>
                                        <form method="post">
                                            <button type="submit" name="export_ranking" class="btn btn-primary btn-sm mt-2">
                                                <i class="fas fa-file-excel me-1"></i> Download XLSX
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <div class="card h-100 export-option-card bg-light border-primary border-3">
                                    <div class="card-body text-center">
                                        <i class="fas fa-file-excel fa-3x text-success mb-3"></i>
                                        <h5 class="card-title">Export Semua Data Lengkap</h5>
                                        <p class="card-text text-muted">Gabungan semua data sekolah, penilaian, dan ranking dalam satu file Excel (XLSX).</p>
                                        <form method="post">
                                            <button type="submit" name="export_semua" class="btn btn-success btn-lg mt-2">
                                                <i class="fas fa-file-excel me-1"></i> Export Semua Data
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php require_once 'footer.php'; ?>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
    <div id="downloadToast" class="toast align-items-center text-bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                ✅ Data berhasil diminta untuk diunduh!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const forms = document.querySelectorAll("form");
    forms.forEach(form => {
        const btn = form.querySelector("button[type=submit]");
        if (btn) {
            btn.addEventListener("click", function () {
                setTimeout(() => {
                    const toastEl = document.getElementById('downloadToast');
                    const toast = new bootstrap.Toast(toastEl);
                    toast.show();
                }, 300); // Tampilkan popup sedikit setelah tombol diklik
            });
        }
    });
});
</script>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>