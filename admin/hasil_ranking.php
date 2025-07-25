<?php
session_start();
require_once '../config.php'; // Pastikan path ini benar

// --- Definisi Kriteria SAW Tetap (Konstanta) ---
const KRITERIA_SAW = [
    1 => ['nama_kriteria' => 'Akreditasi', 'bobot' => 0.25, 'tipe' => 'benefit', 'options' => ['A' => 4, 'B' => 3, 'C' => 2]],
    2 => ['nama_kriteria' => 'Biaya SPP', 'bobot' => 0.30, 'tipe' => 'cost'],
    3 => ['nama_kriteria' => 'Fasilitas', 'bobot' => 0.15, 'tipe' => 'benefit'],
    4 => ['nama_kriteria' => 'Jarak Sekolah dengan Jalan Raya', 'bobot' => 0.10, 'tipe' => 'cost'],
    5 => ['nama_kriteria' => 'Program Unggulan', 'bobot' => 0.20, 'tipe' => 'benefit'],
];

// --- Fungsi Pembantu untuk Mengambil Data (Disesuaikan) ---
/**
 * Mengambil data gabungan dari tabel sekolah dan kriteria secara eksplisit.
 * @param mysqli $koneksi Objek koneksi database.
 * @return array Data gabungan sekolah dan kriteria, diindeks berdasarkan id_sekolah.
 */
function getSekolahDanKriteriaData(mysqli $koneksi): array
{
    $data_lengkap = [];
    // REKOMENDASI: Query dibuat lebih eksplisit, hanya mengambil kolom yang dibutuhkan.
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
    } else {
        error_log("Error fetching combined school and criteria data: " . mysqli_error($koneksi));
    }
    return $data_lengkap;
}


/**
 * Melakukan perhitungan SAW (Simple Additive Weighting).
 * @param array $data_lengkap Data gabungan sekolah dan kriteria.
 * @param array $kriteria_saw Definisi kriteria tetap.
 * @return array Hasil perankingan beserta langkah-langkah perhitungan.
 */
function calculateSAWRanking(array $data_lengkap, array $kriteria_saw): array
{
    // JIKA DATA KOSONG
    if (empty($data_lengkap)) {
        // SOLUSI: Tambahkan 'kriteria' ke dalam array yang dikembalikan
        // agar strukturnya konsisten dan tidak menyebabkan error.
        return [
            'ranking' => [],
            'normalisasi' => [],
            'terbobot' => [],
            'data_asli' => [],
            'kriteria' => $kriteria_saw
        ];
    }

    $normalized_scores = [];
    $weighted_normalized_scores = [];
    $max_min_values = [];
    $original_matrix = [];

    // Langkah 1: Ubah data ke dalam format matriks dan konversi nilai Akreditasi
    foreach ($data_lengkap as $id_sekolah => $data) {
        // Konversi nilai akreditasi (A, B, C) ke numerik
        $akreditasi_char = $data['akreditasi'] ?? null;
        $akreditasi_numeric = isset($kriteria_saw[1]['options'][$akreditasi_char]) ? $kriteria_saw[1]['options'][$akreditasi_char] : 0;

        $original_matrix[$id_sekolah] = [
            1 => $akreditasi_numeric,
            2 => (float)($data['biaya_spp'] ?? 0),
            3 => (float)($data['total_fasilitas'] ?? 0),
            4 => (float)($data['jarak_jalan_raya'] ?? 0),
            5 => (float)($data['nilai_program_unggulan'] ?? 0),
        ];
    }

    // Langkah 2: Cari nilai Max/Min untuk setiap kriteria
    foreach ($kriteria_saw as $id_kriteria => $kriteria_info) {
        $column_values = array_column($original_matrix, $id_kriteria);
        if (empty($column_values)) continue; // Lanjut jika tidak ada data

        if ($kriteria_info['tipe'] === 'benefit') {
            $max_min_values[$id_kriteria] = max($column_values);
        } else { // 'cost'
            // Untuk cost, cari nilai minimum yang bukan nol
            $non_zero_values = array_filter($column_values, function ($val) {
                return $val > 0;
            });
            $max_min_values[$id_kriteria] = !empty($non_zero_values) ? min($non_zero_values) : 0;
        }
    }

    // Langkah 3: Normalisasi
    foreach ($original_matrix as $id_sekolah => $scores) {
        foreach ($kriteria_saw as $id_kriteria => $kriteria_info) {
            $nilai = $scores[$id_kriteria];
            $max_min_val = $max_min_values[$id_kriteria] ?? 0;

            if ($max_min_val == 0) {
                $normalized_scores[$id_sekolah][$id_kriteria] = 0;
            } elseif ($kriteria_info['tipe'] === 'benefit') {
                $normalized_scores[$id_sekolah][$id_kriteria] = $nilai / $max_min_val;
            } else { // 'cost'
                $normalized_scores[$id_sekolah][$id_kriteria] = ($nilai > 0) ? $max_min_val / $nilai : 0;
            }
        }
    }

    // Langkah 4: Perhitungan Skor Total (Penjumlahan Terbobot)
    $ranking_results = [];
    foreach ($data_lengkap as $id_sekolah => $data) {
        $total_skor = 0;
        foreach ($kriteria_saw as $id_kriteria => $kriteria_info) {
            $bobot = $kriteria_info['bobot'];
            $normalized_val = $normalized_scores[$id_sekolah][$id_kriteria];
            $weighted_val = $normalized_val * $bobot;
            $total_skor += $weighted_val;
            $weighted_normalized_scores[$id_sekolah][$id_kriteria] = $weighted_val;
        }
        $ranking_results[$id_sekolah] = [
            'total_skor' => $total_skor,
            'sekolah_data' => $data,
            'normalized_scores' => $normalized_scores[$id_sekolah],
            'original_scores' => $original_matrix[$id_sekolah],
        ];
    }

    // Langkah 5: Urutkan hasil berdasarkan skor total
    uasort($ranking_results, function ($a, $b) {
        return $b['total_skor'] <=> $a['total_skor'];
    });

    $rank = 1;
    foreach ($ranking_results as $id_sekolah => &$data) {
        $data['peringkat'] = $rank++;
    }

    return [
        'ranking' => $ranking_results,
        'normalisasi' => $normalized_scores,
        'terbobot' => $weighted_normalized_scores,
        'data_asli' => $original_matrix,
        'kriteria' => $kriteria_saw
    ];
}

// --- Eksekusi Utama ---
if (isset($koneksi) && $koneksi) {
    // Ambil semua data dalam satu fungsi
    $data_lengkap_sekolah = getSekolahDanKriteriaData($koneksi);

    // Lakukan perhitungan SAW
    $saw_results = calculateSAWRanking($data_lengkap_sekolah, KRITERIA_SAW);

    // Ekstrak hasil untuk ditampilkan
    $ranking_results = $saw_results['ranking'];
    $normalisasi_matrix = $saw_results['normalisasi'];
    $terbobot_matrix = $saw_results['terbobot'];
    $original_data_matrix = $saw_results['data_asli'];
    $kriteria_for_tables = $saw_results['kriteria'];
} else {
    // Fallback jika koneksi gagal
    $ranking_results = [];
    $normalisasi_matrix = [];
    $terbobot_matrix = [];
    $original_data_matrix = [];
    $kriteria_for_tables = KRITERIA_SAW;
    error_log("Database connection failed in hasil_ranking.php");
}

$total_sekolah_dinilai = count($ranking_results);

// Data untuk Ikon Kriteria (UI)
$criterion_icons = [
    'Akreditasi' => 'fas fa-award',
    'Biaya SPP' => 'fas fa-money-bill-wave',
    'Fasilitas' => 'fas fa-building',
    'Jarak Sekolah dengan Jalan Raya' => 'fas fa-road',
    'Program Unggulan' => 'fas fa-star',
    'default' => 'fas fa-check-circle'
];

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Ranking Sekolah</title>
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

        .badge-criteria {
            padding: 0.5rem 0.75rem;
            font-weight: 600;
            font-size: 0.75rem;
            border-radius: 0.35rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }

        .btn-info {
            background-color: #36b9cc;
            border-color: #36b9cc;
            color: white;
        }

        .btn-info:hover {
            background-color: #2a96a5;
            border-color: #258391;
            color: white;
        }

        .stat-card {
            display: flex;
            flex-direction: column;
            min-width: 0;
            padding: 1.5rem;
            border-left: 4px solid;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            height: 100%;
        }

        .stat-card-primary {
            border-left-color: var(--primary-color);
        }

        .stat-card-success {
            border-left-color: #1cc88a;
        }

        .stat-card .stat-card-icon {
            color: #dddfeb;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-card .stat-card-title {
            color: var(--text-secondary);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .stat-card .stat-card-value {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .criterion-card {
            transition: transform 0.2s;
            cursor: default;
        }

        .criterion-card:hover {
            transform: none;
        }

        .criterion-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }

        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            line-height: 1;
            text-align: center;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .rank-1 {
            background-color: #f6c23e;
            /* Gold */
            color: #333;
        }

        .rank-2 {
            background-color: #c0c0c0;
            /* Silver */
            color: #333;
        }

        .rank-3 {
            background-color: #cd7f32;
            /* Bronze */
            color: white;
        }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        .modal-footer {
            border-top: none;
        }

        .progress {
            height: 0.75rem;
            margin-top: 0.5rem;
            border-radius: 0.25rem;
            background-color: var(--border-color);
        }

        .progress-bar {
            background-color: var(--primary-color);
            border-radius: 0.25rem;
        }

        /* Accordion Styles */
        .accordion-button {
            font-weight: 700;
            color: var(--primary-color);
        }

        .accordion-button:not(.collapsed) {
            background-color: var(--primary-color);
            color: white;
        }

        .accordion-button:not(.collapsed)::after {
            filter: brightness(0) invert(1);
            /* Change arrow color to white */
        }

        .accordion-item {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            margin-bottom: 10px;
            overflow: hidden;
            /* Ensures rounded corners apply */
        }

        .accordion-body {
            padding: 20px;
            background-color: white;
            border-top: 1px solid var(--border-color);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {

            .table thead th,
            .table tbody td {
                padding: 0.5rem;
                font-size: 0.9rem;
            }

            .rank-badge {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-card .stat-card-value {
                font-size: 1.2rem;
            }

            .h3 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <?php
    // Conditionally include navbar if accessed by admin
    if (isset($_SESSION['user_id'])) {
        require_once '../admin/navbar.php'; // This is admin navbar
    } else {
        require_once '../guest/guest_navbar.php'; // This is guest navbar
    }
    ?>

    <div class="container-fluid py-4">
        <h1 class="h3 mb-4 text-gray-500">
            <i class="fas fa-trophy me-2"></i> Hasil Ranking Sekolah
        </h1>

        <div class="row mb-4">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="stat-card stat-card-primary">
                    <div class="stat-card-icon">
                        <i class="fas fa-school"></i>
                    </div>
                    <div class="stat-card-title">Total Sekolah Dinilai</div>
                    <div class="stat-card-value"><?php echo $total_sekolah_dinilai; ?> Sekolah</div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="stat-card stat-card-success">
                    <div class="stat-card-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="stat-card-title">Metode Penilaian</div>
                    <div class="stat-card-value">Simple Additive Weighting (SAW)</div>
                </div>
            </div>
            <div class="col-xl-4 col-md-12 mb-4">
                <div class="stat-card" style="border-left-color: #36b9cc;">
                    <div class="stat-card-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-card-title">Tanggal Penilaian</div>
                    <div class="stat-card-value"><?php echo date("d F Y"); ?></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-list-check me-2"></i>
                        <span>Kriteria Penilaian</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php
                            foreach (KRITERIA_SAW as $kriteria_info) {
                                $icon = $criterion_icons[$kriteria_info['nama_kriteria']] ?? $criterion_icons['default'];
                                $badge_color = $kriteria_info['tipe'] == 'benefit' ? 'primary' : 'danger';
                                $arrow_icon = $kriteria_info['tipe'] == 'benefit' ? 'fa-arrow-up' : 'fa-arrow-down';
                                $tip_text = $kriteria_info['tipe'] == 'benefit' ? 'Semakin Tinggi Semakin Baik' : 'Semakin Rendah Semakin Baik';
                            ?>
                                <div class="list-group-item criterion-card d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="<?php echo $icon; ?> criterion-icon text-<?php echo $badge_color; ?>"></i>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($kriteria_info['nama_kriteria']); ?></span>
                                    </div>
                                    <span class="badge bg-<?php echo $badge_color; ?> badge-criteria">
                                        <i class="fas <?php echo $arrow_icon; ?> me-1"></i>
                                        <?php echo htmlspecialchars($tip_text); ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-info-circle me-2"></i>
                        <span>Informasi Sistem</span>
                    </div>
                    <div class="card-body">
                        <p><i class="fas fa-bullseye text-primary me-2"></i> <strong>Tujuan:</strong> Membantu calon siswa dan orang tua dalam memilih sekolah terbaik.</p>
                        <p><i class="fas fa-chart-line text-success me-2"></i> <strong>Metode SAW:</strong> Memperhitungkan berbagai kriteria untuk mendapatkan hasil yang komprehensif.</p>
                        <p><i class="fas fa-exclamation-triangle text-warning me-2"></i> <strong>Catatan:</strong> Hasil perankingan ini hanya sebagai referensi, keputusan akhir tetap di tangan Anda.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div>
                            <i class="fas fa-ranking-star me-2"></i>
                            <span>Daftar Peringkat Sekolah</span>
                        </div>
                        <div>
                            <a href="export_ranking_pdf.php" target="_blank" class="btn btn-sm btn-danger">
                                <i class="fas fa-file-pdf me-1"></i> Export ke PDF
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ranking_results)) { ?>
                            <div class="alert alert-warning" role="alert">
                                Belum ada data sekolah atau penilaian untuk diranking.
                            </div>
                        <?php } else { ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="rankingTable">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Peringkat</th>
                                            <th>Nama Sekolah</th>
                                            <th class="text-center">Akreditasi</th>
                                            <th class="text-center">Skor</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        foreach ($ranking_results as $result) {
                                            $row = $result['sekolah_data'];
                                            $rank_class = '';
                                            if ($result['peringkat'] == 1) $rank_class = 'rank-1';
                                            else if ($result['peringkat'] == 2) $rank_class = 'rank-2';
                                            else if ($result['peringkat'] == 3) $rank_class = 'rank-3';

                                            // Get original akreditasi label (A, B, C)
                                            $akreditasi_label = array_search($result['original_scores'][1], KRITERIA_SAW[1]['options']);
                                            if ($akreditasi_label === false) { // Handle case if original value isn't directly mapped
                                                $akreditasi_label = $result['original_scores'][1]; // Use numeric if not found
                                            }

                                            $badge_color = '';
                                            if ($akreditasi_label == 'A') $badge_color = 'success';
                                            else if ($akreditasi_label == 'B') $badge_color = 'primary';
                                            else if ($akreditasi_label == 'C') $badge_color = 'warning';
                                            else $badge_color = 'secondary';
                                        ?>
                                            <tr <?php echo ($result['peringkat'] <= 3) ? 'class="fw-bold"' : ''; ?>>
                                                <td class="text-center">
                                                    <span class="rank-badge <?php echo $rank_class; ?>">
                                                        <?php echo $result['peringkat']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['nama_sekolah']); ?>
                                                    <?php if ($result['peringkat'] == 1): ?>
                                                        <span class="badge bg-warning text-dark ms-1"><i class="fas fa-crown"></i> Top</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge rounded-pill bg-<?php echo $badge_color; ?>">
                                                        <?php echo htmlspecialchars($akreditasi_label); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="fw-bold"><?php echo number_format($result['total_skor'], 4); ?></div>
                                                    <div class="progress">
                                                        <div class="progress-bar bg-primary" role="progressbar"
                                                            style="width: <?php echo ($result['total_skor'] * 100); ?>%"
                                                            aria-valuenow="<?php echo htmlspecialchars($result['total_skor']); ?>"
                                                            aria-valuemin="0" aria-valuemax="1">
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#detailModal<?php echo $row['id_sekolah']; ?>">
                                                        <i class="fas fa-eye me-1"></i> Detail
                                                    </button>
                                                </td>
                                            </tr>

                                            <div class="modal fade" id="detailModal<?php echo $row['id_sekolah']; ?>" tabindex="-1" aria-labelledby="detailModalLabel<?php echo $row['id_sekolah']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="detailModalLabel<?php echo $row['id_sekolah']; ?>">
                                                                <i class="fas fa-school me-2"></i>
                                                                Detail Sekolah: <?php echo htmlspecialchars($row['nama_sekolah']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="text-center mb-4">
                                                                <span class="rank-badge <?php echo $rank_class; ?> fs-4 p-3 mb-2">
                                                                    #<?php echo $result['peringkat']; ?>
                                                                </span>
                                                                <h4 class="mt-2 text-primary">Skor Total: <?php echo number_format($result['total_skor'], 4); ?></h4>
                                                                <div class="progress mx-auto" style="height: 25px; width: 80%;">
                                                                    <div class="progress-bar bg-primary fw-bold" role="progressbar"
                                                                        style="width: <?php echo ($result['total_skor'] * 100); ?>%"
                                                                        aria-valuenow="<?php echo htmlspecialchars($result['total_skor']); ?>"
                                                                        aria-valuemin="0" aria-valuemax="1">
                                                                        <?php echo number_format($result['total_skor'], 4); ?>
                                                                    </div>
                                                                </div>
                                                                <p class="text-muted mt-2"><small>Peringkat ke-<?php echo $result['peringkat']; ?> dari <?php echo $total_sekolah_dinilai; ?> sekolah</small></p>
                                                            </div>

                                                            <hr>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="fas fa-info-circle me-2"></i> Informasi Umum</h6>
                                                                    <ul class="list-group list-group-flush">
                                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                            <span><i class="fas fa-map-marker-alt text-danger me-2"></i> Alamat:</span>
                                                                            <span><?php echo htmlspecialchars($row['alamat']); ?></span>
                                                                        </li>
                                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                            <span><i class="fas fa-award text-primary me-2"></i> Akreditasi:</span>
                                                                            <span>
                                                                                <span class="badge bg-<?php echo $badge_color; ?> px-3 py-2">
                                                                                    <?php echo htmlspecialchars($akreditasi_label); ?>
                                                                                </span>
                                                                            </span>
                                                                        </li>
                                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                            <span><i class="fas fa-chalkboard-teacher text-info me-2"></i> Total Guru:</span>
                                                                            <span><?php echo htmlspecialchars($row['total_guru']); ?> orang</span>
                                                                        </li>
                                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                            <span><i class="fas fa-graduation-cap text-success me-2"></i> Total Murid Aktif:</span>
                                                                            <span><?php echo htmlspecialchars(is_numeric($row['total_murid_aktif']) ? number_format($row['total_murid_aktif']) : $row['total_murid_aktif']); ?></span>
                                                                        </li>
                                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                            <span><i class="fas fa-road text-warning me-2"></i> Jarak Jalan Raya:</span>
                                                                            <span>
                                                                                <?php
                                                                                $jarak_km_display = $row['jarak_jalan_raya']; // Menggunakan nilai asli dari $row
                                                                                if ($jarak_km_display < 1) {
                                                                                    echo htmlspecialchars(number_format($jarak_km_display * 1000, 0)) . " Meter";
                                                                                } else {
                                                                                    echo htmlspecialchars(number_format($jarak_km_display, 3)) . " KM";
                                                                                }
                                                                                ?>
                                                                            </span>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="fas fa-list-check me-2"></i> Nilai Kriteria</h6>
                                                                    <ul class="list-group list-group-flush">
                                                                        <?php
                                                                        foreach (KRITERIA_SAW as $id_kriteria_modal => $kriteria_info_modal) {
                                                                            $nilai_asli_numeric = $result['original_scores'][$id_kriteria_modal] ?? 'N/A';
                                                                            $nilai_ternormalisasi = $result['normalized_scores'][$id_kriteria_modal] ?? 0;

                                                                            $display_value_asli = $nilai_asli_numeric;
                                                                            // Custom display for original values based on type
                                                                            if ($id_kriteria_modal == 1) { // Akreditasi
                                                                                $display_value_asli = array_search($nilai_asli_numeric, KRITERIA_SAW[1]['options']) ?: $nilai_asli_numeric;
                                                                            } elseif ($id_kriteria_modal == 2) { // Biaya SPP
                                                                                $display_value_asli = 'Rp ' . (is_numeric($nilai_asli_numeric) ? number_format($nilai_asli_numeric, 0, ',', '.') : $nilai_asli_numeric);
                                                                            } elseif ($id_kriteria_modal == 4) { // Jarak
                                                                                // Display distance in KM or Meter based on value
                                                                                if (is_numeric($nilai_asli_numeric)) {
                                                                                    if ($nilai_asli_numeric < 1) {
                                                                                        $display_value_asli = number_format($nilai_asli_numeric * 1000, 0) . ' Meter';
                                                                                    } else {
                                                                                        $display_value_asli = number_format($nilai_asli_numeric, 3) . ' KM';
                                                                                    }
                                                                                } else {
                                                                                    $display_value_asli = htmlspecialchars($nilai_asli_numeric);
                                                                                }
                                                                            } else {
                                                                                $display_value_asli = is_numeric($nilai_asli_numeric) ? number_format($nilai_asli_numeric, 2) : $nilai_asli_numeric;
                                                                            }
                                                                        ?>
                                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                                <div>
                                                                                    <i class="<?php echo $criterion_icons[$kriteria_info_modal['nama_kriteria']] ?? $criterion_icons['default']; ?> text-secondary me-2"></i>
                                                                                    <?php echo htmlspecialchars($kriteria_info_modal['nama_kriteria']); ?>:
                                                                                </div>
                                                                                <div class="text-end">
                                                                                    Nilai: <span class="fw-bold"><?php echo htmlspecialchars($display_value_asli); ?></span>
                                                                                    (Norm: <?php echo number_format($nilai_ternormalisasi, 4); ?>)
                                                                                </div>
                                                                            </li>
                                                                        <?php } ?>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="card-footer">
                        <?php if (isset($_SESSION['user_id'])) { ?>
                            <a href="../admin/dashboard.php" class="btn btn-secondary">Kembali ke Dashboard</a>
                        <?php } else { ?>
                            <a href="../index.php" class="btn btn-info">Kembali ke Halaman Utama</a>
                        <?php } ?>
                    </div>
                </div>

                <div class="accordion mt-4" id="detailPerhitunganAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                <strong><i class="fas fa-info-circle me-2"></i> Lihat Detail Langkah-langkah Perhitungan SAW</strong>
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#detailPerhitunganAccordion">
                            <div class="accordion-body">
                                <div class="card mb-4">
                                    <div class="card-header"><i class="fas fa-table"></i> Matriks Keputusan (Nilai Asli)</div>
                                    <div class="card-body table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Sekolah</th>
                                                    <?php foreach ($kriteria_for_tables as $k): ?>
                                                        <th><?php echo htmlspecialchars($k['nama_kriteria']); ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ranking_results as $result): // Loop through ranked results 
                                                ?>
                                                    <?php $s_data = $result['sekolah_data']; ?>
                                                    <?php $id_sekolah = $s_data['id_sekolah']; ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($s_data['nama_sekolah']); ?></td>
                                                        <?php foreach ($kriteria_for_tables as $id_kriteria => $k_info): ?>
                                                            <td>
                                                                <?php
                                                                $val = $original_data_matrix[$id_sekolah][$id_kriteria] ?? 'N/A';
                                                                if ($id_kriteria == 1) { // Akreditasi
                                                                    echo array_search($val, KRITERIA_SAW[1]['options']) ?: $val;
                                                                } elseif ($id_kriteria == 2) { // Biaya SPP
                                                                    echo is_numeric($val) ? 'Rp ' . number_format($val, 0, ',', '.') : $val;
                                                                } elseif ($id_kriteria == 3) { // Fasilitas
                                                                    echo is_numeric($val) ? number_format($val, 0) : htmlspecialchars($val);
                                                                } elseif ($id_kriteria == 4) { // Jarak
                                                                    // Display distance in KM or Meter based on value
                                                                    if (is_numeric($val)) {
                                                                        if ($val < 1) {
                                                                            echo number_format($val * 1000, 0) . ' Meter';
                                                                        } else {
                                                                            echo number_format($val, 3) . ' KM';
                                                                        }
                                                                    } else {
                                                                        echo htmlspecialchars($val);
                                                                    }
                                                                } else {
                                                                    echo is_numeric($val) ? number_format($val, 2) : htmlspecialchars($val);
                                                                }
                                                                ?>
                                                            </td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="card mb-4">
                                    <div class="card-header"><i class="fas fa-compress-alt"></i> Matriks Ternormalisasi (R)</div>
                                    <div class="card-body table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Sekolah</th>
                                                    <?php foreach ($kriteria_for_tables as $k): ?>
                                                        <th><?php echo htmlspecialchars($k['nama_kriteria']); ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ranking_results as $result): // Loop through ranked results 
                                                ?>
                                                    <?php $s_data = $result['sekolah_data']; ?>
                                                    <?php $id_sekolah = $s_data['id_sekolah']; ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($s_data['nama_sekolah']); ?></td>
                                                        <?php foreach ($kriteria_for_tables as $id_kriteria => $k_info): ?>
                                                            <td><?php echo number_format($normalisasi_matrix[$id_sekolah][$id_kriteria] ?? 0, 4); ?></td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="card mb-4">
                                    <div class="card-header"><i class="fas fa-weight-hanging"></i> Matriks Ternormalisasi Terbobot (Y)</div>
                                    <div class="card-body table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Sekolah</th>
                                                    <?php foreach ($kriteria_for_tables as $k): ?>
                                                        <th><?php echo htmlspecialchars($k['nama_kriteria']); ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ranking_results as $result): // Loop through ranked results 
                                                ?>
                                                    <?php $s_data = $result['sekolah_data']; ?>
                                                    <?php $id_sekolah = $s_data['id_sekolah']; ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($s_data['nama_sekolah']); ?></td>
                                                        <?php foreach ($kriteria_for_tables as $id_kriteria => $k_info): ?>
                                                            <td><?php echo number_format($terbobot_matrix[$id_sekolah][$id_kriteria] ?? 0, 4); ?></td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once '../admin/footer.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.btn-info').forEach(function(btn) {
            btn.addEventListener('mouseover', function() {
                this.closest('tr').classList.add('table-active');
            });

            btn.addEventListener('mouseout', function() {
                this.closest('tr').classList.remove('table-active');
            });
        });

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
</body>

</html>