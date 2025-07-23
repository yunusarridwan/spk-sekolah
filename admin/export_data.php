<?php
session_start();
require_once '../config.php';
require_once '../vendor/autoload.php'; // Autoloader untuk PhpSpreadsheet dan Dompdf

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;

// Cek login admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// --- FUNGSI PENGAMBILAN DATA GABUNGAN (DIGUNAKAN UNTUK EXCEL DAN PDF) ---
function getComprehensiveData($koneksi) {
    // Query untuk mengambil data gabungan dari sekolah dan kriteria
    $sql = "SELECT s.*, k.* FROM sekolah s LEFT JOIN kriteria k ON s.id_sekolah = k.id_sekolah ORDER BY s.nama_sekolah ASC";
    $result = mysqli_query($koneksi, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[$row['id_sekolah']] = $row;
    }
    return $data;
}

// --- FUNGSI PERHITUNGAN RANKING SAW (DIGUNAKAN UNTUK EXCEL DAN PDF) ---
function calculateRanking($data_lengkap) {
    // Definisi Kriteria SAW (bisa ditaruh di sini atau sebagai konstanta global)
    $kriteria_saw = [
        1 => ['nama' => 'Akreditasi', 'bobot' => 0.25, 'tipe' => 'benefit', 'options' => ['A' => 4, 'B' => 3, 'C' => 2]],
        2 => ['nama' => 'Biaya SPP', 'bobot' => 0.30, 'tipe' => 'cost'],
        3 => ['nama' => 'Fasilitas', 'bobot' => 0.15, 'tipe' => 'benefit'],
        4 => ['nama' => 'Jarak', 'bobot' => 0.10, 'tipe' => 'cost'],
        5 => ['nama' => 'Program Unggulan', 'bobot' => 0.20, 'tipe' => 'benefit'],
    ];

    if (empty($data_lengkap)) return [];

    // Proses perhitungan SAW seperti di halaman ranking
    $original_matrix = [];
    foreach ($data_lengkap as $id => $d) {
        $original_matrix[$id] = [
            1 => $kriteria_saw[1]['options'][$d['akreditasi']] ?? 0,
            2 => (float)($d['biaya_spp'] ?? 0),
            3 => (float)($d['total_fasilitas'] ?? 0),
            4 => (float)($d['jarak_jalan_raya'] ?? 0),
            5 => (float)($d['nilai_program_unggulan'] ?? 0),
        ];
    }
    
    $max_min_values = [];
    foreach ($kriteria_saw as $id_k => $k_info) {
        $column = array_column($original_matrix, $id_k);
        if (empty($column)) continue;
        if ($k_info['tipe'] === 'benefit') {
            $max_min_values[$id_k] = max($column);
        } else {
            $non_zero = array_filter($column);
            $max_min_values[$id_k] = empty($non_zero) ? 0 : min($non_zero);
        }
    }

    $normalized_scores = [];
    foreach ($original_matrix as $id_s => $scores) {
        foreach ($kriteria_saw as $id_k => $k_info) {
            $val = $scores[$id_k];
            $max_min = $max_min_values[$id_k];
            if ($max_min == 0) $normalized_scores[$id_s][$id_k] = 0;
            elseif ($k_info['tipe'] === 'benefit') $normalized_scores[$id_s][$id_k] = $val / $max_min;
            else $normalized_scores[$id_s][$id_k] = ($val > 0) ? $max_min / $val : 0;
        }
    }
    
    $final_scores = [];
    foreach ($data_lengkap as $id_s => $d) {
        $total = 0;
        foreach ($kriteria_saw as $id_k => $k_info) {
            $total += $normalized_scores[$id_s][$id_k] * $k_info['bobot'];
        }
        $final_scores[$id_s] = $total;
    }
    
    arsort($final_scores);
    
    $ranked_data = [];
    $rank = 1;
    foreach ($final_scores as $id_s => $score) {
        $ranked_data[$id_s] = $data_lengkap[$id_s];
        $ranked_data[$id_s]['total_skor'] = $score;
        $ranked_data[$id_s]['peringkat'] = $rank++;
    }
    return $ranked_data;
}

// --- FUNGSI EXPORT KE EXCEL (XLSX) ---
function exportToXlsx($data, $ranking) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data Lengkap Sekolah');

    $headers = [
        'Peringkat', 'Nama Sekolah', 'Alamat', 'Total Guru', 'Total Murid',
        'Akreditasi', 'Biaya SPP (Rp)', 'Total Fasilitas', 'Jarak (KM)', 'Nilai Program Unggulan', 'Skor Akhir'
    ];
    $sheet->fromArray($headers, NULL, 'A1');

    $data_rows = [];
    foreach ($ranking as $id => $row) {
        $data_rows[] = [
            $row['peringkat'],
            $row['nama_sekolah'],
            $row['alamat'],
            $row['total_guru'],
            $row['total_murid_aktif'],
            $row['akreditasi'],
            $row['biaya_spp'],
            $row['total_fasilitas'],
            $row['jarak_jalan_raya'],
            $row['nilai_program_unggulan'],
            round($row['total_skor'], 4)
        ];
    }
    $sheet->fromArray($data_rows, NULL, 'A2');

    // Auto size columns
    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    $filename = 'data_lengkap_spk_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

// --- FUNGSI EXPORT KE PDF ---
function exportToPdf($data, $ranking) {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);

    $html = '<html><head><style>
                body { font-family: sans-serif; font-size: 10px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                th { background-color: #f2f2f2; }
                h1 { text-align: center; }
            </style></head><body>';
    $html .= '<h1>Data Lengkap Peringkat Sekolah</h1>';
    $html .= '<table><thead><tr>
                <th>Peringkat</th>
                <th>Nama Sekolah</th>
                <th>Alamat</th>
                <th>Akreditasi</th>
                <th>Biaya SPP</th>
                <th>Jarak (KM)</th>
                <th>Skor Akhir</th>
              </tr></thead><tbody>';

    foreach ($ranking as $id => $row) {
        $html .= '<tr>
                    <td>' . $row['peringkat'] . '</td>
                    <td>' . htmlspecialchars($row['nama_sekolah']) . '</td>
                    <td>' . htmlspecialchars($row['alamat']) . '</td>
                    <td>' . htmlspecialchars($row['akreditasi']) . '</td>
                    <td>Rp ' . number_format($row['biaya_spp']) . '</td>
                    <td>' . number_format($row['jarak_jalan_raya'], 2) . '</td>
                    <td>' . number_format($row['total_skor'], 4) . '</td>
                  </tr>';
    }
    $html .= '</tbody></table></body></html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    $filename = 'data_lengkap_spk_' . date('Y-m-d') . '.pdf';
    $dompdf->stream($filename, ['Attachment' => 1]);
    exit();
}

// Proses permintaan export
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $all_data = getComprehensiveData($koneksi);
    $ranking_data = calculateRanking($all_data);

    if (isset($_POST['export_xlsx'])) {
        exportToXlsx($all_data, $ranking_data);
    } elseif (isset($_POST['export_pdf'])) {
        exportToPdf($all_data, $ranking_data);
    }
}

require_once 'navbar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data | SPK Pemilihan SMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fc; font-family: 'Nunito', sans-serif; }
        .card { box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-file-export me-2"></i>Export Data</h1>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <h5 class="m-0 font-weight-bold text-primary">Export Data Lengkap</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Sistem akan menggabungkan data sekolah dan kriteria, melakukan perangkingan, lalu mengekspor hasilnya dalam format yang Anda pilih.
                        </div>
                        <i class="fas fa-file-invoice fa-4x text-gray-300 my-3"></i>
                        <p class="text-muted">
                            Pilih format file untuk mengunduh gabungan semua data sekolah, penilaian, dan hasil ranking dalam satu dokumen.
                        </p>
                        
                        <form method="post" class="d-inline-flex flex-wrap justify-content-center gap-2 mt-3">
                            <button type="submit" name="export_xlsx" class="btn btn-success btn-lg">
                                <i class="fas fa-file-excel me-2"></i> Export ke Excel (.xlsx)
                            </button>
                            <button type="submit" name="export_pdf" class="btn btn-danger btn-lg">
                                <i class="fas fa-file-pdf me-2"></i> Export ke PDF (.pdf)
                            </button>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php require_once 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>