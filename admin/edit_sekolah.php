<?php
// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';
require_once 'navbar.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Ambil ID sekolah dari parameter URL
$id_sekolah = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_sekolah === 0) {
    header("Location: daftar_sekolah.php");
    exit();
}

// Proses update data jika ada request POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $nama_sekolah = mysqli_real_escape_string($koneksi, $_POST['nama_sekolah']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $total_guru = intval($_POST['total_guru']);
    $total_murid_aktif = intval($_POST['total_murid_aktif']);

    // Ambil data kriteria
    $akreditasi = mysqli_real_escape_string($koneksi, $_POST['akreditasi_val']);
    $biaya_spp = floatval($_POST['biaya_spp_val']);
    $total_fasilitas = intval($_POST['fasilitas_val']);
    $jarak_jalan_raya_meter = floatval($_POST['jarak_jalan_raya_val']);
    $nilai_program_unggulan = floatval($_POST['program_unggulan_val']);

    // Validasi (sama seperti di halaman tambah)
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
    // Tambahkan validasi lain jika perlu...

    if (empty($errors)) {
        // Konversi jarak dari meter ke KM untuk disimpan ke database
        $jarak_jalan_raya_km = $jarak_jalan_raya_meter / 1000;

        // Mulai transaksi database
        mysqli_begin_transaction($koneksi);
        try {
            // 1. UPDATE tabel `sekolah`
            $query_sekolah = "UPDATE sekolah SET nama_sekolah=?, alamat=?, total_guru=?, total_murid_aktif=? WHERE id_sekolah=?";
            $stmt_sekolah = mysqli_prepare($koneksi, $query_sekolah);
            mysqli_stmt_bind_param($stmt_sekolah, "ssiii", $nama_sekolah, $alamat, $total_guru, $total_murid_aktif, $id_sekolah);
            mysqli_stmt_execute($stmt_sekolah);

            // 2. UPDATE tabel `kriteria`
            $query_kriteria = "UPDATE kriteria SET akreditasi=?, biaya_spp=?, total_fasilitas=?, jarak_jalan_raya=?, nilai_program_unggulan=? WHERE id_sekolah=?";
            $stmt_kriteria = mysqli_prepare($koneksi, $query_kriteria);
            mysqli_stmt_bind_param($stmt_kriteria, "sdiddi", $akreditasi, $biaya_spp, $total_fasilitas, $jarak_jalan_raya_km, $nilai_program_unggulan, $id_sekolah);
            mysqli_stmt_execute($stmt_kriteria);

            // Commit transaksi jika semua berhasil
            mysqli_commit($koneksi);
            $_SESSION['pesan'] = "Data sekolah berhasil diperbarui!";
            header("Location: daftar_sekolah.php");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $_SESSION['error'] = "Gagal memperbarui data: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

// Ambil data gabungan dari tabel sekolah dan kriteria untuk ditampilkan di form
$query_gabungan = "SELECT s.*, k.* FROM sekolah s LEFT JOIN kriteria k ON s.id_sekolah = k.id_sekolah WHERE s.id_sekolah = ?";
$stmt_gabungan = mysqli_prepare($koneksi, $query_gabungan);
mysqli_stmt_bind_param($stmt_gabungan, "i", $id_sekolah);
mysqli_stmt_execute($stmt_gabungan);
$result_gabungan = mysqli_stmt_get_result($stmt_gabungan);
$data = mysqli_fetch_assoc($result_gabungan);

if (!$data) {
    $_SESSION['pesan'] = "Sekolah tidak ditemukan!";
    header("Location: daftar_sekolah.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
                <h2 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Sekolah</h2>
            </div>
            <div class="card-body p-4">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo $_SESSION['error'];
                        unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="edit_sekolah.php?id=<?php echo $id_sekolah; ?>" method="post">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="nama_sekolah" class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                            <input type="text" name="nama_sekolah" id="nama_sekolah" class="form-control" value="<?php echo htmlspecialchars($data['nama_sekolah']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="akreditasi_val" class="form-label">Akreditasi <span class="text-danger">*</span></label>
                            <select name="akreditasi_val" id="akreditasi_val" class="form-select" required>
                                <option value="">Pilih Akreditasi</option>
                                <option value="A" <?php echo ($data['akreditasi'] == 'A') ? 'selected' : ''; ?>>A</option>
                                <option value="B" <?php echo ($data['akreditasi'] == 'B') ? 'selected' : ''; ?>>B</option>
                                <option value="C" <?php echo ($data['akreditasi'] == 'C') ? 'selected' : ''; ?>>C</option>
                            </select>
                            <small class="form-text text-muted">Nilai Akreditasi: A (Sangat Baik), B (Baik), C (Cukup)</small>
                        </div>

                        <div class="col-12">
                            <label for="alamat" class="form-label">Alamat <span class="text-danger">*</span></label>
                            <textarea name="alamat" id="alamat" class="form-control" rows="3" required><?php echo htmlspecialchars($data['alamat']); ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label for="total_guru" class="form-label">Total Guru <span class="text-danger">*</span></label>
                            <input type="number" name="total_guru" id="total_guru" class="form-control" value="<?php echo htmlspecialchars($data['total_guru']); ?>" required min="1">
                            <small class="form-text text-muted">Jumlah guru harus bernilai positif.</small>
                        </div>

                        <div class="col-md-6">
                            <label for="total_murid_aktif" class="form-label">Total Murid Aktif <span class="text-danger">*</span></label>
                            <input type="number" name="total_murid_aktif" id="total_murid_aktif" class="form-control" value="<?php echo htmlspecialchars($data['total_murid_aktif']); ?>" required min="1">
                            <small class="form-text text-muted">Jumlah murid aktif harus bernilai positif.</small>
                        </div>

                        <div class="col-md-6">
                            <label for="biaya_spp_val" class="form-label">Biaya SPP Per Bulan <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="biaya_spp_val" id="biaya_spp_val" class="form-control" value="<?php echo htmlspecialchars($data['biaya_spp']); ?>" required min="1">
                            </div>
                            <small class="form-text text-muted">Semakin rendah semakin baik (kriteria Cost).</small>
                        </div>

                        <div class="col-md-6">
                            <label for="fasilitas_val" class="form-label">Total Fasilitas <span class="text-danger">*</span></label>
                            <input type="number" name="fasilitas_val" id="fasilitas_val" class="form-control" value="<?php echo htmlspecialchars($data['total_fasilitas']); ?>" required min="0">
                            <small class="form-text text-muted">Total fasilitas harus bernilai positif.</small>
                        </div>

                        <div class="col-md-6">
                            <label for="jarak_jalan_raya_val" class="form-label">Jarak Sekolah dengan Jalan Raya <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="any" name="jarak_jalan_raya_val" id="jarak_jalan_raya_val" class="form-control" value="<?php echo htmlspecialchars($data['jarak_jalan_raya'] * 1000); ?>" required min="0">
                                <span class="input-group-text">Meter</span>
                            </div>
                            <small class="form-text text-muted">Semakin rendah semakin baik (kriteria Cost). Nilai akan dikonversi ke KM saat disimpan.</small>
                        </div>

                        <div class="col-md-6">
                            <label for="program_unggulan_val" class="form-label">Nilai Program Unggulan <span class="text-danger">*</span></label>
                            <input type="number" step="any" name="program_unggulan_val" id="program_unggulan_val" class="form-control" value="<?php echo htmlspecialchars($data['nilai_program_unggulan']); ?>" required min="0" max="10">
                            <small class="form-text text-muted">Berikan nilai program unggulan (contoh: 1-10, semakin tinggi semakin baik - kriteria Benefit).</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-clockwise me-1"></i> Perbarui Data</button>
                        <a href="daftar_sekolah.php" class="btn btn-secondary"><i class="bi bi-x-circle me-1"></i> Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php require_once 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>