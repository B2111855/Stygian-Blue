<?php
include '../../../database/config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['invoice_id'])) {
    $invoice_id = $_POST['invoice_id'];

    // Cập nhật hóa đơn: đánh dấu đã yêu cầu xác minh thanh toán
    $sql = "UPDATE hoa_don SET YEU_CAU_XAC_NHAN = 1 WHERE ID_HD = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $invoice_id);
        if ($stmt->execute()) {
            echo "<script>alert('Yêu cầu xác minh thanh toán đã được gửi. Nhân viên sẽ xác nhận sớm.'); window.location.href='../Views/hoa_don.php';</script>";
        } else {
            echo "Lỗi khi cập nhật yêu cầu xác minh: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Lỗi prepare: " . $conn->error;
    }
} else {
    echo "Dữ liệu không hợp lệ.";
}

$conn->close();
?>
