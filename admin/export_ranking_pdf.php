<?php
session_start();
require_once '../config.php'; // Sesuaikan path jika perlu
require_once '../vendor/autoload.php'; // Path ke autoloader Composer

use Dompdf\Dompdf;
use Dompdf\Options;

// --- Definisi Kriteria SAW (Sama seperti di halaman hasil) ---
const KRITERIA_SAW = [
    1 => ['nama_kriteria' => 'Akreditasi', 'bobot' => 0.25, 'tipe' => 'benefit', 'options' => ['A' => 4, 'B' => 3, 'C' => 2]],
    2 => ['nama_kriteria' => 'Biaya SPP', 'bobot' => 0.30, 'tipe' => 'cost'],
    3 => ['nama_kriteria' => 'Fasilitas', 'bobot' => 0.15, 'tipe' => 'benefit'],
    4 => ['nama_kriteria' => 'Jarak Sekolah dengan Jalan Raya', 'bobot' => 0.10, 'tipe' => 'cost'],
    5 => ['nama_kriteria' => 'Program Unggulan', 'bobot' => 0.20, 'tipe' => 'benefit'],
];

// --- Fungsi Pengambilan Data (Copy dari hasil_ranking.php) ---
function getSekolahDanKriteriaData(mysqli $koneksi): array
{
    $data_lengkap = [];
    $sql = "SELECT
                s.id_sekolah, s.nama_sekolah, s.alamat, s.total_guru, s.total_murid_aktif,
                k.akreditasi, k.biaya_spp, k.total_fasilitas, k.jarak_jalan_raya, k.nilai_program_unggulan
            FROM
                sekolah s
            LEFT JOIN
                kriteria k ON s.id_sekolah = k.id_sekolah";
    
    $query = mysqli_query($koneksi, $sql);
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $data_lengkap[$row['id_sekolah']] = $row;
        }
    }
    return $data_lengkap;
}

// --- Fungsi Perhitungan SAW (Copy dari hasil_ranking.php) ---
function calculateSAWRanking(array $data_lengkap, array $kriteria_saw): array
{
    if (empty($data_lengkap)) return [];

    $original_matrix = [];
    foreach ($data_lengkap as $id_sekolah => $data) {
        $akreditasi_char = $data['akreditasi'] ?? null;
        $akreditasi_numeric = $kriteria_saw[1]['options'][$akreditasi_char] ?? 0;

        $original_matrix[$id_sekolah] = [
            1 => $akreditasi_numeric,
            2 => (float)($data['biaya_spp'] ?? 0),
            3 => (float)($data['total_fasilitas'] ?? 0),
            4 => (float)($data['jarak_jalan_raya'] ?? 0),
            5 => (float)($data['nilai_program_unggulan'] ?? 0),
        ];
    }

    $max_min_values = [];
    foreach ($kriteria_saw as $id_kriteria => $kriteria_info) {
        $column_values = array_column($original_matrix, $id_kriteria);
        if (empty($column_values)) continue; 

        if ($kriteria_info['tipe'] === 'benefit') {
            $max_min_values[$id_kriteria] = max($column_values);
        } else {
            $non_zero_values = array_filter($column_values, fn($val) => $val > 0);
            $max_min_values[$id_kriteria] = !empty($non_zero_values) ? min($non_zero_values) : 0;
        }
    }

    $normalized_scores = [];
    foreach ($original_matrix as $id_sekolah => $scores) {
        foreach ($kriteria_saw as $id_kriteria => $kriteria_info) {
            $nilai = $scores[$id_kriteria];
            $max_min_val = $max_min_values[$id_kriteria] ?? 0;

            if ($max_min_val == 0) {
                $normalized_scores[$id_sekolah][$id_kriteria] = 0;
            } elseif ($kriteria_info['tipe'] === 'benefit') {
                $normalized_scores[$id_sekolah][$id_kriteria] = $nilai / $max_min_val;
            } else {
                $normalized_scores[$id_sekolah][$id_kriteria] = ($nilai > 0) ? $max_min_val / $nilai : 0;
            }
        }
    }

    $ranking_results = [];
    foreach ($data_lengkap as $id_sekolah => $data) {
        $total_skor = 0;
        foreach ($kriteria_saw as $id_kriteria => $kriteria_info) {
            $bobot = $kriteria_info['bobot'];
            $normalized_val = $normalized_scores[$id_sekolah][$id_kriteria];
            $total_skor += $normalized_val * $bobot;
        }
        $ranking_results[$id_sekolah] = [
            'total_skor' => $total_skor,
            'sekolah_data' => $data,
            'original_scores' => $original_matrix[$id_sekolah],
        ];
    }

    uasort($ranking_results, fn($a, $b) => $b['total_skor'] <=> $a['total_skor']);

    $rank = 1;
    foreach ($ranking_results as &$data) {
        $data['peringkat'] = $rank++;
    }
    
    return $ranking_results;
}


// --- EKSEKUSI UTAMA UNTUK GENERATE PDF ---

// 1. Ambil dan hitung data
$data_lengkap_sekolah = getSekolahDanKriteriaData($koneksi);
$ranking_data = calculateSAWRanking($data_lengkap_sekolah, KRITERIA_SAW);

// 2. Siapkan HTML untuk PDF
$html = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Peringkat Sekolah</title>
    <style>
        body { 
            font-family: "Helvetica", sans-serif; 
            font-size: 10px;
        }
        h1 {
            text-align: center;
            font-size: 18px;
            margin-bottom: 20px;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
            word-wrap: break-word;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #777;
        }
    </style>
</head>
<body>
    <h1>Laporan Hasil Peringkat Sekolah</h1>
    <p>Tanggal Cetak: ' . date("d F Y") . '</p>
    <table>
        <thead>
            <tr>
                <th class="text-center">Peringkat</th>
                <th>Nama Sekolah</th>
                <th>Alamat</th>
                <th class="text-center">Akreditasi</th>
                <th class="text-right">Biaya SPP (Rp)</th>
                <th class="text-right">Fasilitas</th>
                <th class="text-right">Jarak (KM)</th>
                <th class="text-right">Prog. Unggulan</th>
                <th class="text-center">Skor Akhir</th>
            </tr>
        </thead>
        <tbody>';

if (empty($ranking_data)) {
    $html .= '<tr><td colspan="9" class="text-center">Tidak ada data untuk ditampilkan.</td></tr>';
} else {
    foreach ($ranking_data as $result) {
        $sekolah = $result['sekolah_data'];
        $original_scores = $result['original_scores'];
        
        // Dapatkan label akreditasi
        $akreditasi_label = array_search($original_scores[1], KRITERIA_SAW[1]['options']) ?: 'N/A';

        $html .= '
            <tr>
                <td class="text-center">' . $result['peringkat'] . '</td>
                <td>' . htmlspecialchars($sekolah['nama_sekolah']) . '</td>
                <td>' . htmlspecialchars($sekolah['alamat']) . '</td>
                <td class="text-center">' . htmlspecialchars($akreditasi_label) . '</td>
                <td class="text-right">' . number_format($original_scores[2], 0, ',', '.') . '</td>
                <td class="text-right">' . number_format($original_scores[3], 0) . '</td>
                <td class="text-right">' . number_format($original_scores[4], 3) . '</td>
                <td class="text-right">' . number_format($original_scores[5], 2) . '</td>
                <td class="text-center">' . number_format($result['total_skor'], 4) . '</td>
            </tr>';
    }
}

$html .= '
        </tbody>
    </table>
    <div class="footer">
        Laporan ini dihasilkan oleh Sistem Pendukung Keputusan Pemilihan Sekolah.
    </div>
</body>
</html>';

// 3. Konfigurasi dan Render Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Memungkinkan memuat gambar/CSS eksternal jika ada

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// (Opsional) Atur ukuran kertas dan orientasi, 'landscape' agar lebih lebar
$dompdf->setPaper('A4', 'landscape');

// Render HTML ke PDF
$dompdf->render();

// Keluarkan PDF ke browser untuk diunduh
$filename = 'laporan_peringkat_sekolah_' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ['Attachment' => 1]); // Attachment => 1 untuk langsung download

exit();