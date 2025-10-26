<?php
include 'C:\xampp\htdocs\StygianBlue\database\config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_hd'])) {
    $id_hd = (int)$_POST['id_hd'];

    // Cập nhật trạng thái thanh toán
    $stmt = $conn->prepare("UPDATE hoa_don SET TRANGTHAI_THANHTOAN = 'Đã thanh toán' WHERE ID_HD = ?");
    if (!$stmt) {
        echo "<script>alert('Lỗi khi cập nhật hóa đơn: {$conn->error}'); window.location.href='admin_dashboard.php?page=payments';</script>";
        exit;
    }
    $stmt->bind_param("i", $id_hd);
    $stmt->execute();

    // Lấy thông tin hóa đơn và chi nhánh
    // Sửa lại sau prepare
    $stmtInfo = $conn->prepare("
    SELECT hd.TONG_TIEN, cn.ID_CN
    FROM hoa_don hd
    JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN
    JOIN chi_nhanh cn ON lh.ID_CHINHANH = cn.ID_CN
    WHERE hd.ID_HD = ?
");

    if (!$stmtInfo) {
        echo "<script>alert('Lỗi khi chuẩn bị truy vấn: " . $conn->error . "'); window.location.href='admin_dashboard.php?page=payments';</script>";
        exit;
    }


    $stmtInfo->bind_param("i", $id_hd);
    $stmtInfo->execute();
    $result = $stmtInfo->get_result();

    if ($row = $result->fetch_assoc()) {
        $tongTien = $row['TONG_TIEN'];
        $id_cn = $row['ID_CN'];

        // Ghi vào bảng tài chính
        $stmtInsert = $conn->prepare("
            INSERT INTO tai_chinh (ID_HD, NGAY_GIAO_DICH, SO_TIEN, LOAI_GIAO_DICH, ID_CN)
            VALUES (?, NOW(), ?, 'doanh thu', ?)
        ");

        if (!$stmtInsert) {
            $message = "Lỗi khi ghi vào bảng tài chính: " . $conn->error;
            echo "<script>
                alert(" . json_encode($message) . ");
                window.location.href='admin_dashboard.php?page=payments';
            </script>";
            exit;
        }

        $stmtInsert->bind_param("idi", $id_hd, $tongTien, $id_cn);
        $stmtInsert->execute();
    }

    echo "<script>alert('Xác nhận thanh toán thành công!'); window.location.href='/stygianblue/app/admin/admin_dashboard.php?page=payments';</script>";
    exit;
}
