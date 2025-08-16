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

// Proses tambah sekolah
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $nama_sekolah = mysqli_real_escape_string($koneksi, $_POST['nama_sekolah']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $total_guru = intval($_POST['total_guru']);
    $total_murid_aktif = intval($_POST['total_murid_aktif']);
    $id_admin = $_SESSION['user_id']; // Ambil ID admin dari session

    // Ambil data untuk tabel kriteria
    $akreditasi = mysqli_real_escape_string($koneksi, $_POST['akreditasi_val']);
    $biaya_spp = floatval($_POST['biaya_spp_val']);
    $total_fasilitas = intval($_POST['fasilitas_val']);
    $jarak_jalan_raya_meter = floatval($_POST['jarak_jalan_raya_val']);
    $nilai_program_unggulan = floatval($_POST['program_unggulan_val']);

    // Validasi input
    $errors = [];
    if (empty($nama_sekolah)) {
        $errors[] = "Nama sekolah tidak boleh kosong";
    }
    if (empty($alamat)) {
        $errors[] = "Alamat tidak boleh kosong";
    }
    if ($total_guru <= 0) {
        $errors[] = "Total guru harus bernilai positif";
    }
    if ($total_murid_aktif <= 0) {
        $errors[] = "Total murid aktif harus bernilai positif";
    }
    if (empty($akreditasi)) {
        $errors[] = "Akreditasi tidak boleh kosong";
    }
    if ($biaya_spp <= 0) {
        $errors[] = "Biaya SPP harus bernilai positif";
    }
    if ($total_fasilitas < 0) {
        $errors[] = "Total fasilitas tidak boleh negatif";
    }
    if ($jarak_jalan_raya_meter < 0) {
        $errors[] = "Jarak sekolah tidak boleh negatif";
    }
    if ($nilai_program_unggulan < 0) {
        $errors[] = "Nilai program unggulan tidak boleh negatif";
    }

    // Jika tidak ada error, lanjutkan proses
    if (empty($errors)) {
        // Konversi jarak dari meter ke kilometer
        $jarak_jalan_raya_km = $jarak_jalan_raya_meter / 1000;

        // Mulai transaksi database
        mysqli_begin_transaction($koneksi);

        try {
            // 1. Insert data ke tabel `sekolah`
            $query_sekolah = "INSERT INTO sekolah (nama_sekolah, alamat, total_guru, total_murid_aktif, id_admin) VALUES (?, ?, ?, ?, ?)";
            $stmt_sekolah = mysqli_prepare($koneksi, $query_sekolah);
            mysqli_stmt_bind_param($stmt_sekolah, "ssiii", $nama_sekolah, $alamat, $total_guru, $total_murid_aktif, $id_admin);
            mysqli_stmt_execute($stmt_sekolah);

            // Ambil ID sekolah yang baru saja ditambahkan
            $id_sekolah_baru = mysqli_insert_id($koneksi);

            // 2. Insert data ke tabel `kriteria`
            $query_kriteria = "INSERT INTO kriteria (id_sekolah, akreditasi, biaya_spp, total_fasilitas, jarak_jalan_raya, nilai_program_unggulan) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_kriteria = mysqli_prepare($koneksi, $query_kriteria);
            mysqli_stmt_bind_param($stmt_kriteria, "isdidd", $id_sekolah_baru, $akreditasi, $biaya_spp, $total_fasilitas, $jarak_jalan_raya_km, $nilai_program_unggulan);
            mysqli_stmt_execute($stmt_kriteria);

            // Jika semua query berhasil, commit transaksi
            mysqli_commit($koneksi);
            $_SESSION['pesan'] = "Sekolah dan data kriteria berhasil ditambahkan!";
            header("Location: daftar_sekolah.php");
            exit();
        } catch (Exception $e) {
            // Jika terjadi error, rollback transaksi
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
    <style>
        .card-header {
            background-color: #0d6efd;
            color: white;
            font-weight: bold;
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
                <?php if (isset($_SESSION['error'])) { ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $_SESSION['error'];
                                                                            unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>

                <?php if (isset($_SESSION['error'])) { ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $_SESSION['error'];
                                                                            unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>

                <form action="tambah_sekolah.php" method="post">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="nama_sekolah" class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                            <input type="text" name="nama_sekolah" id="nama_sekolah" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="akreditasi_val" class="form-label">Akreditasi <span class="text-danger">*</span></label>
                            <select name="akreditasi_val" id="akreditasi_val" class="form-select" required>
                                <option value="" disabled selected>Pilih Akreditasi</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                            </select>
                            <small class="form-text text-muted">Nilai Akreditasi: A (Sangat Baik), B (Baik), C (Cukup)</small>
                        </div>

                        <div class="col-12">
                            <label for="alamat" class="form-label">Alamat <span class="text-danger">*</span></label>
                            <textarea name="alamat" id="alamat" class="form-control" rows="3" required></textarea>
                        </div>

                        <div class="col-md-6">
                            <label for="total_guru" class="form-label">Total Guru <span class="text-danger">*</span></label>
                            <input type="number" name="total_guru" id="total_guru" class="form-control" required min="1">
                            <small class="form-text text-muted">Jumlah guru harus bernilai positif.</small>
                        </div>

                        <div class="col-md-6">
                            <label for="total_murid_aktif" class="form-label">Total Murid Aktif <span class="text-danger">*</span></label>
                            <input type="number" name="total_murid_aktif" id="total_murid_aktif" class="form-control" required min="1">
                            <small class="form-text text-muted">Jumlah murid aktif harus bernilai positif.</small>
                        </div>

                        <div class="col-md-6">
                            <label for="biaya_spp_val" class="form-label">Biaya SPP Per Bulan <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="biaya_spp_val" id="biaya_spp_val" class="form-control" required min="1">
                            </div>
                            <small class="form-text text-muted">Semakin rendah semakin baik (kriteria Cost).</small>
                        </div>

                        <div class="col-md-6">
                            <label for="fasilitas_val" class="form-label">Total Fasilitas <span class="text-danger">*</span></label>
                            <input type="number" name="fasilitas_val" id="fasilitas_val" class="form-control" required min="1">
                            <small class="form-text text-muted">Total fasilitas harus bernilai positif.</small>
                        </div>

                        <div class="col-md-6">
                            <label for="jarak_jalan_raya_val" class="form-label">Jarak Sekolah dengan Jalan Raya <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="any" name="jarak_jalan_raya_val" id="jarak_jalan_raya_val" class="form-control" required min="1">
                                <span class="input-group-text">Meter</span>
                            </div>
                            <small class="form-text text-muted">Semakin rendah semakin baik (kriteria Cost). Nilai akan dikonversi ke KM saat disimpan.</small>
                        </div>

                        <div class="col-md-6">
                            <label for="program_unggulan_val" class="form-label">Nilai Program Unggulan <span class="text-danger">*</span></label>
                            <input type="number" step="any" name="program_unggulan_val" id="program_unggulan_val" class="form-control" required min="1" max="10">
                            <small class="form-text text-muted">Berikan nilai program unggulan (contoh: 1-10, semakin tinggi semakin baik - kriteria Benefit).</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Tambah Sekolah</button>
                        <a href="daftar_sekolah.php" class="btn btn-secondary"><i class="bi bi-x-circle me-1"></i> Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php require_once 'footer.php'; ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>