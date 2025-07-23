-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 23 Jul 2025 pada 17.29
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_sekolah`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `Id_admin` int(11) NOT NULL,
  `nama_lengkap` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`Id_admin`, `nama_lengkap`, `username`, `password`) VALUES
(1, 'Administrator', 'admin', '$2y$10$hEXxn1.xM2GnpxvXUkdhHOfl01OcIIHhx/AF.v2vTxVbhMQtzz1nS');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kriteria`
--

CREATE TABLE `kriteria` (
  `id_kriteria` int(11) NOT NULL,
  `id_sekolah` int(11) NOT NULL,
  `akreditasi` char(1) NOT NULL,
  `biaya_spp` decimal(10,2) NOT NULL,
  `total_fasilitas` int(11) NOT NULL,
  `jarak_jalan_raya` decimal(10,3) NOT NULL,
  `nilai_program_unggulan` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kriteria`
--

INSERT INTO `kriteria` (`id_kriteria`, `id_sekolah`, `akreditasi`, `biaya_spp`, `total_fasilitas`, `jarak_jalan_raya`, `nilai_program_unggulan`) VALUES
(1, 2, 'B', 2500000.00, 11, 0.833, 9.00),
(2, 3, 'A', 650000.00, 44, 0.001, 9.30),
(3, 4, 'A', 1300000.00, 36, 0.014, 9.40),
(4, 5, 'A', 445000.00, 46, 0.001, 9.60);

-- --------------------------------------------------------

--
-- Struktur dari tabel `sekolah`
--

CREATE TABLE `sekolah` (
  `id_sekolah` int(11) NOT NULL,
  `nama_sekolah` varchar(255) NOT NULL,
  `alamat` text NOT NULL,
  `total_guru` int(11) NOT NULL,
  `total_murid_aktif` int(11) NOT NULL,
  `id_admin` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `sekolah`
--

INSERT INTO `sekolah` (`id_sekolah`, `nama_sekolah`, `alamat`, `total_guru`, `total_murid_aktif`, `id_admin`) VALUES
(2, 'SMAS EL SHADDAI INTERCONTINENTAL SCHOOL', 'JL Pos Pengumben Raya No. 40 Komp. Permata Mediterania Ulujami Kec. Pesanggrahan Kota Jakarta Selatan D.K.I. Jakarta', 4, 8, 1),
(3, 'SMAS TRIGUNA JAKARTA', 'JL. BINTARO PERMAI II NO.9 Bintaro Kec. Pesanggrahan Kota Jakarta Selatan D.K.I. Jakarta', 24, 305, 1),
(4, 'SMAS DARUNNAJAH', 'JL.ULUJAMI RAYA NO. 86 Ulujami Kec. Pesanggrahan Kota Jakarta Selatan D.K.I. Jakarta', 22, 206, 1),
(5, 'SMAS KARTIKA X-1', 'JL. RAYA KODAM BINTARO NO. 53, Pesanggrahan, Kec. Pesanggrahan, Kota Jakarta Selatan, D.K.I. Jakarta.', 33, 712, 1);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`Id_admin`);

--
-- Indeks untuk tabel `kriteria`
--
ALTER TABLE `kriteria`
  ADD PRIMARY KEY (`id_kriteria`),
  ADD KEY `fk_kriteria_sekolah` (`id_sekolah`);

--
-- Indeks untuk tabel `sekolah`
--
ALTER TABLE `sekolah`
  ADD PRIMARY KEY (`id_sekolah`),
  ADD KEY `fk_sekolah_admin` (`id_admin`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `Id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `kriteria`
--
ALTER TABLE `kriteria`
  MODIFY `id_kriteria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `sekolah`
--
ALTER TABLE `sekolah`
  MODIFY `id_sekolah` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `kriteria`
--
ALTER TABLE `kriteria`
  ADD CONSTRAINT `fk_kriteria_sekolah` FOREIGN KEY (`id_sekolah`) REFERENCES `sekolah` (`id_sekolah`);

--
-- Ketidakleluasaan untuk tabel `sekolah`
--
ALTER TABLE `sekolah`
  ADD CONSTRAINT `fk_sekolah_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`Id_admin`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
