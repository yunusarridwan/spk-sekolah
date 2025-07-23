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

// --- PENYESUAIAN: Fungsi untuk mengambil data dari tabel SEKOLAH dan KRITERIA ---
/**
 * Mengambil data gabungan dari tabel sekolah dan kriteria.
 * @param mysqli $koneksi Objek koneksi database.
 * @return array Data gabungan sekolah dan kriteria, diindeks berdasarkan id_sekolah.
 */
function getSekolahDanKriteriaData(mysqli $koneksi): array
{
    $data_lengkap = [];
    // Query JOIN untuk mengambil semua data yang diperlukan sesuai ERD
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


// --- Fungsi Perhitungan SAW (Tidak perlu diubah, tapi saya sertakan yang sudah diperbaiki dari prompt sebelumnya) ---
function calculateSAWRanking(array $data_lengkap, array $kriteria_saw): array
{
    if (empty($data_lengkap)) {
        return [
            'ranking' => [], 
            'normalisasi' => [], 
            'terbobot' => [], 
            'data_asli' => [], 
            'kriteria' => $kriteria_saw // Pastikan key 'kriteria' ada
        ];
    }

    $normalized_scores = [];
    $weighted_normalized_scores = [];
    $max_min_values = [];
    $original_matrix = [];

    // Langkah 1: Ubah data ke dalam format matriks dan konversi nilai Akreditasi
    foreach ($data_lengkap as $id_sekolah => $data) {
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
        if (empty($column_values)) continue; 

        if ($kriteria_info['tipe'] === 'benefit') {
            $max_min_values[$id_kriteria] = max($column_values);
        } else { // 'cost'
            $non_zero_values = array_filter($column_values, function($val) { return $val > 0; });
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
    // Panggil fungsi yang sudah disesuaikan
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
        }

        .table thead th {
            font-weight: 600;
            background-color: var(--secondary-color);
        }

        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .rank-1 { background-color: #f6c23e; color: #333; }
        .rank-2 { background-color: #c0c0c0; color: #333; }
        .rank-3 { background-color: #cd7f32; color: white; }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
        }

        .progress {
            height: 0.75rem;
        }

        .accordion-button:not(.collapsed) {
            background-color: var(--primary-color);
            color: white;
        }
        .accordion-button:not(.collapsed)::after {
            filter: brightness(0) invert(1);
        }
    </style>
</head>
<body>
    <?php
    if (isset($_SESSION['user_id'])) {
        require_once '../admin/navbar.php';
    } else {
        require_once 'guest_navbar.php';
    }
    ?>

    <div class="container-fluid py-4">
        <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-trophy me-2"></i>Hasil Ranking Sekolah</h1>

        <div class="row mb-4">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-start-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Sekolah</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_sekolah_dinilai; ?> Sekolah</div>
                            </div>
                            <div class="col-auto"><i class="fas fa-school fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-start-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Metode Penilaian</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">Simple Additive Weighting (SAW)</div>
                            </div>
                            <div class="col-auto"><i class="fas fa-calculator fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-start-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Tanggal Penilaian</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo date("d F Y"); ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-calendar fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list-check me-2"></i>Kriteria Penilaian</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach(KRITERIA_SAW as $kriteria_info): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="<?php echo $criterion_icons[$kriteria_info['nama_kriteria']]; ?> me-2 text-<?php echo $kriteria_info['tipe'] == 'benefit' ? 'success' : 'danger'; ?>"></i>
                                    <?php echo htmlspecialchars($kriteria_info['nama_kriteria']); ?>
                                </div>
                                <span class="badge bg-<?php echo $kriteria_info['tipe'] == 'benefit' ? 'success' : 'danger'; ?> rounded-pill">
                                    <?php echo ucfirst($kriteria_info['tipe']); ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-ranking-star me-2"></i>Daftar Peringkat Sekolah</h6>
                        <button class="btn btn-sm btn-outline-primary" id="printBtn"><i class="fas fa-print me-1"></i> Cetak</button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($ranking_results)): ?>
                            <div class="alert alert-warning text-center" role="alert">Belum ada data sekolah untuk diranking.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="rankingTable">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Peringkat</th>
                                            <th>Nama Sekolah</th>
                                            <th class="text-center">Skor Akhir</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ranking_results as $id_sekolah => $result): ?>
                                            <tr class="<?php if($result['peringkat'] <= 3) echo 'table-light'; ?>">
                                                <td class="text-center fw-bold">
                                                    <span class="rank-badge <?php if($result['peringkat'] <= 3) echo 'rank-'.$result['peringkat']; ?>">
                                                        <?php echo $result['peringkat']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($result['sekolah_data']['nama_sekolah']); ?>
                                                    <?php if($result['peringkat'] == 1): ?><span class="badge bg-warning text-dark ms-2"><i class="fas fa-crown"></i> Pilihan Terbaik</span><?php endif; ?>
                                                </td>
                                                <td class="text-center fw-bold">
                                                    <?php echo number_format($result['total_skor'], 4); ?>
                                                    <div class="progress mt-1">
                                                        <div class="progress-bar" role="progressbar" style="width: <?php echo ($result['total_skor'] * 100); ?>%" aria-valuenow="<?php echo $result['total_skor']; ?>" aria-valuemin="0" aria-valuemax="1"></div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $id_sekolah; ?>">
                                                        <i class="fas fa-eye me-1"></i> Detail
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                 <div class="accordion mt-4" id="detailPerhitunganAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                <strong><i class="fas fa-calculator me-2"></i>Lihat Detail Langkah Perhitungan SAW</strong>
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
                                                    <?php foreach ($kriteria_for_tables as $k): ?><th><?php echo htmlspecialchars($k['nama_kriteria']); ?></th><?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($ranking_results as $id_s => $res): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($res['sekolah_data']['nama_sekolah']); ?></td>
                                                    <?php foreach ($kriteria_for_tables as $id_k => $k_info): 
                                                        $val = $original_data_matrix[$id_s][$id_k] ?? 'N/A';
                                                        if ($id_k == 1) { echo array_search($val, $k_info['options']) ?: $val; }
                                                        else { echo is_numeric($val) ? number_format($val, 2) : $val; }
                                                    ?>
                                                    <td><?php echo is_numeric($val) ? ( $id_k == 2 ? 'Rp '.number_format($val) : number_format($val, 2) ) : (array_search($val, $k_info['options']) ?: $val); ?></td>
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
                                                <?php foreach ($kriteria_for_tables as $k): ?><th><?php echo htmlspecialchars($k['nama_kriteria']); ?></th><?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($ranking_results as $id_s => $res): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($res['sekolah_data']['nama_sekolah']); ?></td>
                                                <?php foreach ($kriteria_for_tables as $id_k => $k_info): ?>
                                                <td><?php echo number_format($normalisasi_matrix[$id_s][$id_k] ?? 0, 4); ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="card">
                                    <div class="card-header"><i class="fas fa-weight-hanging"></i> Matriks Terbobot (Y)</div>
                                    <div class="card-body table-responsive">
                                        <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>Sekolah</th>
                                                <?php foreach ($kriteria_for_tables as $k): ?><th><?php echo htmlspecialchars($k['nama_kriteria']); ?></th><?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($ranking_results as $id_s => $res): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($res['sekolah_data']['nama_sekolah']); ?></td>
                                                <?php foreach ($kriteria_for_tables as $id_k => $k_info): ?>
                                                <td><?php echo number_format($terbobot_matrix[$id_s][$id_k] ?? 0, 4); ?></td>
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

    <?php foreach ($ranking_results as $id_sekolah => $result): 
        $row = $result['sekolah_data'];
        $akreditasi_label = array_search($result['original_scores'][1], KRITERIA_SAW[1]['options']) ?: 'N/A';
        $badge_color = 'secondary';
        if($akreditasi_label == 'A') $badge_color = 'success';
        else if($akreditasi_label == 'B') $badge_color = 'primary';
        else if($akreditasi_label == 'C') $badge_color = 'warning';
    ?>
    <div class="modal fade" id="detailModal<?php echo $id_sekolah; ?>" tabindex="-1" aria-labelledby="detailModalLabel<?php echo $id_sekolah; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalLabel<?php echo $id_sekolah; ?>">Detail: <?php echo htmlspecialchars($row['nama_sekolah']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-info-circle me-2 text-primary"></i>Informasi Umum</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong>Alamat:</strong> <?php echo htmlspecialchars($row['alamat']); ?></li>
                                <li class="list-group-item"><strong>Total Guru:</strong> <?php echo htmlspecialchars($row['total_guru']); ?></li>
                                <li class="list-group-item"><strong>Total Murid:</strong> <?php echo htmlspecialchars(number_format($row['total_murid_aktif'])); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-list-check me-2 text-primary"></i>Rincian Nilai Kriteria</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong>Akreditasi:</strong> <span class="badge bg-<?php echo $badge_color; ?>"><?php echo $akreditasi_label; ?></span></li>
                                <li class="list-group-item"><strong>Biaya SPP:</strong> Rp <?php echo number_format($result['original_scores'][2], 0, ',', '.'); ?></li>
                                <li class="list-group-item"><strong>Fasilitas:</strong> <?php echo number_format($result['original_scores'][3], 2); ?></li>
                                <li class="list-group-item"><strong>Jarak:</strong> <?php echo ($result['original_scores'][4] < 1 ? number_format($result['original_scores'][4]*1000).' M' : number_format($result['original_scores'][4], 2).' KM'); ?></li>
                                <li class="list-group-item"><strong>Program Unggulan:</strong> <?php echo number_format($result['original_scores'][5], 2); ?></li>
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
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('printBtn').addEventListener('click', () => window.print());
    </script>
</body>
</html>