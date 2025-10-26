<?php
include '../../../database/config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id_lichhen'])) {
    $id = $_POST['id_lichhen'];

    $stmt = $conn->prepare("UPDATE LICH_HEN SET TRANGTHAI = 'Đã hủy' WHERE ID_LICHHEN = ? AND TRANGTHAI = 'Đang chờ'");
    $stmt->bind_param("s", $id);
    $stmt->execute();

    $stmt->close();
    $conn->close();
    header("Location: view_schedule.php"); // Trang danh sách lịch hẹn
    exit();
}
?>
