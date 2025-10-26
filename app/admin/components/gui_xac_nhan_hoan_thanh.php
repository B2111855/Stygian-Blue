<?php
include '../../../database/config.php';
session_start();

$staffId = $_SESSION['ID_TK'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_lichhen']) && $staffId) {
    $idLichHen = (int)$_POST['id_lichhen'];

    // Cập nhật trạng thái của lịch hẹn thành "Đã hoàn thành"
    $updateStmt = $conn->prepare("UPDATE lich_hen SET TRANGTHAI = 'Đã hoàn thành' WHERE ID_LICHHEN = ?");
    $updateStmt->bind_param("i", $idLichHen);

    // Ghi nhận xác nhận vào bảng xac_nhan_hoan_thanh
    $insertStmt = $conn->prepare("INSERT INTO xac_nhan_hoan_thanh (ID_LICHHEN, ID_TK) VALUES (?, ?)");
    $insertStmt->bind_param("is", $idLichHen, $staffId);

    if ($updateStmt->execute() && $insertStmt->execute()) {
        $_SESSION['success'] = "Bạn đã xác nhận hoàn thành lịch hẹn #$idLichHen.";
    } else {
        $_SESSION['error'] = "Đã có lỗi xảy ra khi xác nhận.";
    }

    $updateStmt->close();
    $insertStmt->close();
}

header("Location: ../staff_dashboard.php?page=s");
exit;
