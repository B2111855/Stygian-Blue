<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Kết nối database
include $_SERVER['DOCUMENT_ROOT'] . '/stygianblue/database/config.php';

// Kiểm tra dữ liệu nhận được từ JavaScript
if (!isset($_GET['date']) || !isset($_GET['branch_id'])) {
    echo json_encode(['error' => 'Thiếu tham số date hoặc branch_id']);
    exit;
}

$date = $_GET['date'];
$branch_id = $_GET['branch_id'];

// Truy vấn dữ liệu từ bảng LICH_HEN
$sql = $sql = "SELECT TIME_FORMAT(THOI_GIAN_BAT_DAU, '%H:%i:%s') AS gio 
                FROM LICH_HEN 
                WHERE DATE(THOI_GIAN_BAT_DAU) = ? 
                AND ID_CHINHANH = ? 
                AND TRANGTHAI IN ('đang chờ', 'đã xác nhận')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $date, $branch_id);
$stmt->execute();
$result = $stmt->get_result();

$booked_slots = [];
while ($row = $result->fetch_assoc()) {
    $booked_slots[] = $row['gio'];
}

// Trả kết quả về cho JavaScript
echo json_encode($booked_slots);
exit;
