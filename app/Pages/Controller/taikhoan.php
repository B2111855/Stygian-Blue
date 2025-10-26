<?php

include '../../../database\config.php'; // Bao gồm file cấu hình

function getUserInfo($conn, $idTk = null) {
    // Kiểm tra xem người dùng đã đăng nhập chưa
    if ($idTk === null) {
        if (!isset($_SESSION['ID_TK'])) {
            return null; // Nếu chưa đăng nhập, trả về null
        }
        $idTk = $_SESSION['ID_TK']; // Lấy ID_TK từ phiên làm việc
    }

    // Đảm bảo ID_TK là số nguyên
    $idTk = intval($idTk);

    // Truy vấn thông tin người dùng từ cơ sở dữ liệu
    $stmt = $conn->prepare("SELECT ID_TK, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT FROM tai_khoan WHERE ID_TK = ?");
    $stmt->bind_param("s", $idTk);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc(); // Trả về thông tin người dùng
    } else {
        return null; // Nếu không tìm thấy thông tin
    }
}
