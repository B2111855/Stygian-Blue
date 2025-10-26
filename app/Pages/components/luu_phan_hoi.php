<?php
include '../../../database/config.php';
session_start();

if (!isset($_SESSION['ID_TK'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tk = $_SESSION['ID_TK'];
    $id_dv = $_POST['id_dv'];
    $noidung = trim($_POST['noidung']);
    $xephang = (int)$_POST['xephang'];

    // Kiểm tra xem phản hồi đã tồn tại chưa
    $checkQuery = "SELECT * FROM phan_hoi_cua_khach_hang WHERE ID_TK = ? AND ID_DV = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("si", $id_tk, $id_dv);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // Cập nhật phản hồi đã tồn tại
        $updateQuery = "UPDATE phan_hoi_cua_khach_hang SET NOI_DUNG = ?, XEP_HANG_DV = ?, NGAY_GUI = NOW() WHERE ID_TK = ? AND ID_DV = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("sisi", $noidung, $xephang, $id_tk, $id_dv);
        if ($updateStmt->execute()) {
            $_SESSION['phanhoi_success'] = "Phản hồi đã được cập nhật thành công!";
        } else {
            $_SESSION['phanhoi_error'] = "Lỗi khi cập nhật phản hồi: " . $conn->error;
        }
        $updateStmt->close();
    } else {
        // Thêm mới phản hồi
        $insertQuery = "INSERT INTO phan_hoi_cua_khach_hang (ID_TK, ID_DV, NOI_DUNG, NGAY_GUI, XEP_HANG_DV) VALUES (?, ?, ?, NOW(), ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("sisi", $id_tk, $id_dv, $noidung, $xephang);
        if ($insertStmt->execute()) {
            $_SESSION['phanhoi_success'] = "Cảm ơn bạn đã gửi phản hồi!";
        } else {
            $_SESSION['phanhoi_error'] = "Lỗi khi gửi phản hồi: " . $conn->error;
        }
        $insertStmt->close();
    }

    $checkStmt->close();
}

header("Location: ../Views/xemLichhen.php");
exit();
