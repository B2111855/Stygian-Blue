<?php
include '../../../database/config.php';
session_start();

$staffId = $_SESSION['ID_TK'] ?? null;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_lichhen']) && $staffId) {
    $idLichHen = (int)$_POST['id_lichhen'];
    // Kiểm tra nhân viên có thực sự được phân công lịch này không
    $checkStmt = $conn->prepare("SELECT lh.THOI_GIAN_BAT_DAU, lh.TRANGTHAI FROM phan_cong_nhan_vien pc JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN WHERE pc.ID_TK = ? AND pc.ID_LICHHEN = ? LIMIT 1");
    $checkStmt->bind_param("si", $staffId, $idLichHen);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $startTime = $row['THOI_GIAN_BAT_DAU'];
        $trangThai = $row['TRANGTHAI'];
        $now = new DateTime();
        $startDate = null;
        try {
            $startDate = new DateTime($startTime);
        } catch (Exception $e) {
            $startDate = null;
        }
        // Chỉ cho phép xác nhận nếu lịch chưa hoàn thành/hủy và đã đến thời gian bắt đầu
        if ($startDate && $now >= $startDate && $trangThai !== 'Đã hoàn thành' && $trangThai !== 'Đã hủy') {
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
        } else {
            if ($trangThai === 'Đã hoàn thành') {
                $_SESSION['error'] = "Lịch hẹn này đã được xác nhận hoàn thành trước đó.";
            } elseif ($trangThai === 'Đã hủy') {
                $_SESSION['error'] = "Lịch hẹn này đã bị hủy, không thể xác nhận hoàn thành.";
            } elseif ($startDate && $now < $startDate) {
                $_SESSION['error'] = "Chưa đến thời gian bắt đầu lịch hẹn, không thể xác nhận hoàn thành.";
            } else {
                $_SESSION['error'] = "Không thể xác nhận hoàn thành lịch hẹn.";
            }
        }
    } else {
        $_SESSION['error'] = "Bạn không có quyền xác nhận lịch hẹn này.";
    }
    $checkStmt->close();
}

header("Location: ../staff_dashboard.php?page=s");
exit;
