<?php
$host = 'localhost';
$username = 'root'; // Ganti dengan username database Anda
$password = '';     // Ganti dengan password database Anda
$database = 'db_sekolah'; // Pastikan nama database sesuai

$koneksi = mysqli_connect($host, $username, $password, $database);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>
