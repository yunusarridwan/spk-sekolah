<?php
// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';
require_once 'navbar.php';

// Cek apakah user sudah login (implicitly admin)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); // Redirect ke login.php jika belum login
    exit();
}

// Define fixed criteria with their details for display and processing
// These IDs must match the ones inserted in spk.sql
$fixed_kriteria_details = [
    1 => ['nama_kriteria' => 'Akreditasi', 'tipe' => 'benefit', 'input_type' => 'select', 'options' => ['A' => 4, 'B' => 3, 'C' => 2]], // A=4, B=3, C=2
    2 => ['nama_kriteria' => 'Biaya SPP', 'tipe' => 'cost', 'input_type' => 'number'],
    3 => ['nama_kriteria' => 'Fasilitas', 'tipe' => 'benefit', 'input_type' => 'number'], // Example: score 1-10
    4 => ['nama_kriteria' => 'Jarak Sekolah dengan Jalan Raya', 'tipe' => 'cost', 'input_type' => 'number'], // Example: in km
    5 => ['nama_kriteria' => 'Program Unggulan', 'tipe' => 'benefit', 'input_type' => 'number'], // Example: score 1-10
];

// Proses tambah sekolah
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_sekolah = mysqli_real_escape_string($koneksi, $_POST['nama_sekolah']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $akreditasi_val = mysqli_real_escape_string($koneksi, $_POST['akreditasi_val']); // The letter A, B, C
    $total_guru = intval($_POST['total_guru']);
    $total_murid_aktif = intval($_POST['total_murid_aktif']);
    $biaya_spp_val = floatval($_POST['biaya_spp_val']); // This is the value for the 'sekolah' table
    $fasilitas_val = floatval($_POST['fasilitas_val']);
    $jarak_jalan_raya_meter_val = floatval($_POST['jarak_jalan_raya_val']); // Get value in meters
    $program_unggulan_val = floatval($_POST['program_unggulan_val']); // Assuming this is a numerical score

    // Validasi input
    $errors = [];

    if (empty($nama_sekolah)) { $errors[] = "Nama sekolah tidak boleh kosong"; }
    if (empty($alamat)) { $errors[] = "Alamat tidak boleh kosong"; }
    if (empty($akreditasi_val)) { $errors[] = "Akreditasi tidak boleh kosong"; }
    if ($total_guru <= 0) { $errors[] = "Total guru harus bernilai positif"; }
    if ($total_murid_aktif <= 0) { $errors[] = "Total murid aktif harus bernilai positif"; }
    if ($biaya_spp_val <= 0) { $errors[] = "Biaya SPP harus bernilai positif"; }
    if ($fasilitas_val < 0) { $errors[] = "Total fasilitas tidak boleh negatif"; }
    if ($jarak_jalan_raya_meter_val < 0) { $errors[] = "Jarak sekolah tidak boleh negatif"; }
    if ($program_unggulan_val < 0) { $errors[] = "Nilai program unggulan tidak boleh negatif"; }

    // Cek apakah ada errors
    if (empty($errors)) {
        // Konversi jarak dari meter ke kilometer sebelum disimpan ke database
        $jarak_jalan_raya_km_val = $jarak_jalan_raya_meter_val / 1000;

        // Mulai transaksi
        mysqli_begin_transaction($koneksi);

        try {
            // Insert data ke tabel sekolah
            $query_sekolah = "INSERT INTO sekolah (nama_sekolah, alamat, akreditasi, total_guru, total_murid_aktif, biaya_spp, fasilitas, jarak_jalan_raya, program_unggulan, id_users) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_sekolah = mysqli_prepare($koneksi, $query_sekolah);
            $id_user = $_SESSION['user_id']; // Ambil ID user dari session
mysqli_stmt_bind_param($stmt_sekolah, "sssiddiddi", $nama_sekolah, $alamat, $akreditasi_val, $total_guru, $total_murid_aktif, $biaya_spp_val, $fasilitas_val, $jarak_jalan_raya_km_val, $program_unggulan_val, $id_user);
            mysqli_stmt_execute($stmt_sekolah);
            $id_sekolah_baru = mysqli_insert_id($koneksi); // Ambil ID sekolah yang baru ditambahkan

            // Map the form input values to the respective kriteria IDs for insertion into the 'penilaian' table
            $kriteria_values = [
                1 => $fixed_kriteria_details[1]['options'][$akreditasi_val], // Akreditasi (numerical value)
                2 => $biaya_spp_val,
                3 => $fasilitas_val,
                4 => $jarak_jalan_raya_km_val, // Use the converted KM value for penilaian
                5 => $program_unggulan_val,
            ];

            // Insert penilaian for each criterion into the 'penilaian' table
            foreach ($fixed_kriteria_details as $id_kriteria => $kriteria_info) {
                // Ensure the value exists before attempting to insert
                if (isset($kriteria_values[$id_kriteria])) {
                    $nilai_kriteria = $kriteria_values[$id_kriteria];

                    $query_penilaian = "INSERT INTO penilaian (id_sekolah, id_kriteria, nilai) VALUES (?, ?, ?)";
                    $stmt_penilaian = mysqli_prepare($koneksi, $query_penilaian);
                    mysqli_stmt_bind_param($stmt_penilaian, "iid", $id_sekolah_baru, $id_kriteria, $nilai_kriteria);
                    mysqli_stmt_execute($stmt_penilaian);
                } else {
                    // This case should ideally not be hit if all fixed criteria are covered
                    // You might want to log this or add an error if a criterion value is missing
                    error_log("Missing value for kriteria ID: " . $id_kriteria);
                }
            }

            mysqli_commit($koneksi);
            $_SESSION['pesan'] = "Sekolah dan penilaian berhasil ditambahkan!";
            header("Location: daftar_sekolah.php");
            exit();

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $_SESSION['error'] = "Gagal menambahkan sekolah: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Sekolah Baru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .container.mt-5 {
            flex: 1;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .alert-info-tip {
            background-color: #e0f7fa; /* Light blue */
            border-color: #00bcd4; /* Cyan */
            color: #006064; /* Darker cyan */
            padding: 1rem 1.5rem;
            border-radius: .375rem;
            margin-top: 2rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        .alert-info-tip .bi {
            font-size: 1.8rem;
            color: #00838f; /* Even darker cyan */
        }
        .alert-info-tip strong {
            color: #004d40; /* Dark green for emphasis */
        }
        .form-label {
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Tambah Sekolah</h2>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['pesan'])) { ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?php echo $_SESSION['pesan']; unset($_SESSION['pesan']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <?php if (isset($_SESSION['error'])) { ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>

                <form action="" method="post">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nama_sekolah" class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                            <input type="text" name="nama_sekolah" id="nama_sekolah" class="form-control" required value="<?php echo isset($_POST['nama_sekolah']) ? htmlspecialchars($_POST['nama_sekolah']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="akreditasi_val" class="form-label">Akreditasi <span class="text-danger">*</span></label>
                            <select name="akreditasi_val" id="akreditasi_val" class="form-select" required>
                                <option value="">Pilih Akreditasi</option>
                                <?php foreach ($fixed_kriteria_details[1]['options'] as $label => $value) { ?>
                                    <option value="<?php echo $label; ?>" <?php echo (isset($_POST['akreditasi_val']) && $_POST['akreditasi_val'] == $label) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php } ?>
                            </select>
                            <small class="form-text text-muted">Nilai Akreditasi: A (Sangat Baik), B (Baik), C (Cukup)</small>
                        </div>
                        <div class="col-12">
                            <label for="alamat" class="form-label">Alamat <span class="text-danger">*</span></label>
                            <textarea name="alamat" id="alamat" class="form-control" rows="3" required><?php echo isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : ''; ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="total_guru" class="form-label">Total Guru <span class="text-danger">*</span></label>
                            <input type="number" name="total_guru" id="total_guru" class="form-control" required min="1" value="<?php echo isset($_POST['total_guru']) ? htmlspecialchars($_POST['total_guru']) : ''; ?>">
                            <small class="form-text text-muted">Jumlah guru harus bernilai positif.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="total_murid_aktif" class="form-label">Total Murid Aktif <span class="text-danger">*</span></label>
                            <input type="number" name="total_murid_aktif" id="total_murid_aktif" class="form-control" required min="1" value="<?php echo isset($_POST['total_murid_aktif']) ? htmlspecialchars($_POST['total_murid_aktif']) : ''; ?>">
                            <small class="form-text text-muted">Jumlah murid aktif harus bernilai positif.</small>
                        </div>

                        <div class="col-md-6">
                            <label for="biaya_spp" class="form-label">Biaya SPP Per Bulan <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="biaya_spp_val" id="biaya_spp" class="form-control" required min="1" value="<?php echo isset($_POST['biaya_spp_val']) ? htmlspecialchars($_POST['biaya_spp_val']) : ''; ?>">
                            </div>
                            <small class="form-text text-muted">Semakin rendah semakin baik (kriteria Cost).</small>
                        </div>

                        <div class="col-md-6">
                            <label for="fasilitas_val" class="form-label">Total Fasilitas <span class="text-danger">*</span></label>
                            <input type="number" name="fasilitas_val" id="fasilitas_val" class="form-control" required min="0" value="<?php echo isset($_POST['fasilitas_val']) ? htmlspecialchars($_POST['fasilitas_val']) : ''; ?>">
                            <small class="form-text text-muted">Total fasilitas harus bernilai positif.</small>
                        </div>

                        <div class="col-md-6">
                            <label for="jarak_jalan_raya_val" class="form-label">Jarak Sekolah dengan Jalan Raya <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="jarak_jalan_raya_val" id="jarak_jalan_raya_val" class="form-control" required min="0" value="<?php echo isset($_POST['jarak_jalan_raya_val']) ? htmlspecialchars($_POST['jarak_jalan_raya_val']) : ''; ?>">
                                <span class="input-group-text">Meter</span> <!-- Changed from KM to Meter -->
                            </div>
                            <small class="form-text text-muted">Semakin rendah semakin baik (kriteria Cost). Nilai akan dikonversi ke KM saat disimpan.</small> <!-- Updated description -->
                        </div>

                        <div class="col-md-6">
                            <label for="program_unggulan_val" class="form-label">Nilai Program Unggulan <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="program_unggulan_val" id="program_unggulan_val" class="form-control" required min="0" value="<?php echo isset($_POST['program_unggulan_val']) ? htmlspecialchars($_POST['program_unggulan_val']) : ''; ?>">
                            <small class="form-text text-muted">Berikan nilai program unggulan (contoh: 1-10, semakin tinggi semakin baik - kriteria Benefit).</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Tambah Sekolah</button>
                        <a href="daftar_sekolah.php" class="btn btn-secondary"><i class="bi bi-x-circle me-1"></i> Batal</a>
                    </div>
                </form>

                <div class="alert-info-tip mt-5">
                    <i class="bi bi-lightbulb-fill"></i>
                    <div>
                        <strong>Penting: Perhatikan Tipe Kriteria!</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <li>Kriteria <b>Benefit: </b>Nilai yang semakin tinggi menunjukkan performa yang <b>lebih baik</b>.</li>
                            <li>Kriteria <b>Cost: </b> Nilai yang semakin rendah menunjukkan performa yang <b>lebih baik</b>.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store the fixed criteria details with their options for easy access
        const fixedKriteriaDetails = <?php echo json_encode($fixed_kriteria_details); ?>;

        // The JavaScript for updating hidden fields is no longer strictly necessary for the PHP processing,
        // as PHP now directly uses the main form fields for criteria values.
        // However, if you have other client-side logic that relies on these hidden fields,
        // you might keep them. For this specific issue, they are not needed on the PHP side.
        // I've removed the hidden inputs from the HTML and the corresponding JS.
        // If you need the hidden inputs for other JS, you can put them back,
        // but ensure their values are correctly set before form submission.
    </script>
</body>
</html>