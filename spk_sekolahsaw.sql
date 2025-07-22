-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 22, 2025 at 10:28 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `spk_sekolahsaw`
--

-- --------------------------------------------------------

--
-- Table structure for table `kriteria`
--

CREATE TABLE `kriteria` (
  `id_kriteria` int(11) NOT NULL,
  `nama_kriteria` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kriteria`
--

INSERT INTO `kriteria` (`id_kriteria`, `nama_kriteria`) VALUES
(1, 'Akreditasi'),
(2, 'Biaya SPP'),
(3, 'Fasilitas'),
(4, 'Jarak Sekolah dengan Jalan Raya'),
(5, 'Program Unggulan');

-- --------------------------------------------------------

--
-- Table structure for table `penilaian`
--

CREATE TABLE `penilaian` (
  `id_penilaian` int(11) NOT NULL,
  `id_sekolah` int(11) NOT NULL,
  `id_kriteria` int(11) NOT NULL,
  `nilai` decimal(10,3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penilaian`
--

INSERT INTO `penilaian` (`id_penilaian`, `id_sekolah`, `id_kriteria`, `nilai`) VALUES
(41, 12, 1, 3.000),
(42, 12, 2, 2500000.000),
(43, 12, 3, 11.000),
(44, 12, 4, 0.833),
(45, 12, 5, 9.000),
(46, 13, 1, 4.000),
(47, 13, 2, 650000.000),
(48, 13, 3, 44.000),
(49, 13, 4, 0.001),
(50, 13, 5, 9.300),
(51, 14, 1, 4.000),
(52, 14, 2, 1300000.000),
(53, 14, 3, 36.000),
(54, 14, 4, 0.014),
(55, 14, 5, 9.400),
(61, 16, 1, 4.000),
(62, 16, 2, 445000.000),
(63, 16, 3, 46.000),
(64, 16, 4, 0.001),
(65, 16, 5, 9.600);

-- --------------------------------------------------------

--
-- Table structure for table `sekolah`
--

CREATE TABLE `sekolah` (
  `id_sekolah` int(11) NOT NULL,
  `nama_sekolah` varchar(255) NOT NULL,
  `alamat` text NOT NULL,
  `akreditasi` char(1) NOT NULL,
  `total_guru` int(11) NOT NULL,
  `total_murid_aktif` int(11) DEFAULT NULL,
  `biaya_spp` decimal(10,2) NOT NULL,
  `fasilitas` int(11) DEFAULT NULL,
  `jarak_jalan_raya` decimal(10,3) NOT NULL,
  `program_unggulan` decimal(5,2) NOT NULL,
  `id_users` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sekolah`
--

INSERT INTO `sekolah` (`id_sekolah`, `nama_sekolah`, `alamat`, `akreditasi`, `total_guru`, `total_murid_aktif`, `biaya_spp`, `fasilitas`, `jarak_jalan_raya`, `program_unggulan`, `id_users`) VALUES
(12, 'SMAS EL SHADDAI INTERCONTINENTAL SCHOOL', 'JL Pos Pengumben Raya No. 40 Komp. Permata Mediterania Ulujami Kec. Pesanggrahan Kota Jakarta Selatan D.K.I. Jakarta', 'B', 4, 8, 2500000.00, 11, 0.833, 9.00, 1),
(13, 'SMAS TRIGUNA JAKARTA', 'JL. BINTARO PERMAI II NO.9 Bintaro Kec. Pesanggrahan Kota Jakarta Selatan D.K.I. Jakarta', 'A', 24, 305, 650000.00, 44, 0.001, 9.30, 1),
(14, 'SMAS DARUNNAJAH', 'JL.ULUJAMI RAYA NO. 86 Ulujami Kec. Pesanggrahan Kota Jakarta Selatan D.K.I. Jakarta', 'A', 22, 206, 1300000.00, 36, 0.014, 9.40, 1),
(16, 'SMAS KARTIKA X-1', 'JL. RAYA KODAM BINTARO NO. 53, Pesanggrahan, Kec. Pesanggrahan, Kota Jakarta Selatan, D.K.I. Jakarta.', 'A', 33, 712, 445000.00, 46, 0.001, 9.60, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama_lengkap` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama_lengkap`, `username`, `password`) VALUES
(1, 'Administrator', 'admin', '$2y$10$hEXxn1.xM2GnpxvXUkdhHOfl01OcIIHhx/AF.v2vTxVbhMQtzz1nS');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `kriteria`
--
ALTER TABLE `kriteria`
  ADD PRIMARY KEY (`id_kriteria`),
  ADD UNIQUE KEY `nama_kriteria` (`nama_kriteria`);

--
-- Indexes for table `penilaian`
--
ALTER TABLE `penilaian`
  ADD PRIMARY KEY (`id_penilaian`),
  ADD UNIQUE KEY `id_sekolah` (`id_sekolah`,`id_kriteria`),
  ADD KEY `id_kriteria` (`id_kriteria`);

--
-- Indexes for table `sekolah`
--
ALTER TABLE `sekolah`
  ADD PRIMARY KEY (`id_sekolah`),
  ADD KEY `id_users` (`id_users`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `penilaian`
--
ALTER TABLE `penilaian`
  MODIFY `id_penilaian` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `sekolah`
--
ALTER TABLE `sekolah`
  MODIFY `id_sekolah` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `penilaian`
--
ALTER TABLE `penilaian`
  ADD CONSTRAINT `penilaian_ibfk_1` FOREIGN KEY (`id_sekolah`) REFERENCES `sekolah` (`id_sekolah`) ON DELETE CASCADE,
  ADD CONSTRAINT `penilaian_ibfk_2` FOREIGN KEY (`id_kriteria`) REFERENCES `kriteria` (`id_kriteria`) ON DELETE CASCADE;

--
-- Constraints for table `sekolah`
--
ALTER TABLE `sekolah`
  ADD CONSTRAINT `sekolah_ibfk_1` FOREIGN KEY (`id_users`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
