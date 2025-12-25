<?php
$servername = "localhost";  //
$username = "root";  // Tên đăng nhập MySQL (ví dụ: root)
$password = "";  // Mật khẩu MySQL
$dbname = "stygianblue_dbv6";  // Tên cơ sở dữ liệu

// Tạo kết nối với cổng
$conn = new mysqli($servername, $username, $password, $dbname, 7070);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Auto-create lich_hen_tokens table if it doesn't exist
$createTokenTableSQL = "
    CREATE TABLE IF NOT EXISTS lich_hen_tokens (
        ID INT AUTO_INCREMENT PRIMARY KEY,
        ID_LICHHEN INT NOT NULL,
        TOKEN VARCHAR(255) UNIQUE NOT NULL,
        CREATED_AT DATETIME DEFAULT CURRENT_TIMESTAMP,
        EXPIRE_AT DATETIME NOT NULL,
        USED_AT DATETIME NULL,
        FOREIGN KEY (ID_LICHHEN) REFERENCES lich_hen(ID_LICHHEN) ON DELETE CASCADE,
        INDEX idx_token (TOKEN),
        INDEX idx_expire (EXPIRE_AT),
        INDEX idx_lichhen (ID_LICHHEN)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

if (!$conn->query($createTokenTableSQL)) {
    // Silently fail if table already exists or other errors
    // Log only if it's not a "table already exists" error
    if (strpos($conn->error, 'already exists') === false && $conn->error !== '') {
        error_log('Error creating lich_hen_tokens table: ' . $conn->error);
    }
}
?>