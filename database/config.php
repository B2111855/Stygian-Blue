<?php
$servername = "localhost";  //
$username = "root";  // Tên đăng nhập MySQL (ví dụ: root)
$password = "";  // Mật khẩu MySQL
$dbname = "StygianBlue_DBv4";  // Tên cơ sở dữ liệu

// Tạo kết nối với cổng
$conn = new mysqli($servername, $username, $password, $dbname, 7070);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>