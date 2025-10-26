<?php
// Include the database connection
include '../../database/config.php';

// Check if the invoice request is set
if (isset($_GET['id_lichhen'])) {
    $id_lichhen = $_GET['id_lichhen'];

    // Fetch the service, time, and price information for the given appointment
    $sql = "
        SELECT lh.ID_LICHHEN, lh.ID_DV, lh.THOI_GIAN_BAT_DAU, lh.THOI_GIAN_KET_THUC, 
               dv.TEN_DV, dv.thoi_gian, dg.DON_GIA
        FROM lich_hen lh
        JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
        JOIN don_gia_dich_vu dg ON lh.ID_DV = dg.ID_DV
        WHERE lh.ID_LICHHEN = '$id_lichhen'
    ";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $appointment = mysqli_fetch_assoc($result);

        // Calculate the duration in hours
        $start_time = new DateTime($appointment['THOI_GIAN_BAT_DAU']);
        $end_time = new DateTime($appointment['THOI_GIAN_KET_THUC']);
        $duration = $start_time->diff($end_time);
        $hours = $duration->h + ($duration->days * 24); // Convert days to hours

        // Calculate the total price
        $total_price = $hours * $appointment['DON_GIA'];

        // Insert the invoice into the `hoa_don` table
        $insert_invoice_sql = "
            INSERT INTO hoa_don (ID_LICHHEN, NGAY_GIO, TONG_TIEN)
            VALUES ('$id_lichhen', NOW(), '$total_price')
        ";
        mysqli_query($conn, $insert_invoice_sql);

        // Fetch the generated invoice details
        $invoice_sql = "
            SELECT * FROM hoa_don WHERE ID_LICHHEN = '$id_lichhen'
        ";
        $invoice_result = mysqli_query($conn, $invoice_sql);
        $invoice = mysqli_fetch_assoc($invoice_result);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa Đơn Dịch Vụ</title>
    <link rel="stylesheet" href="/path/to/your/css/style.css">
</head>

<body class="bg-gray-100 p-6">
    <h1 class="text-2xl font-bold mb-4">Hóa Đơn Dịch Vụ</h1>

    <?php if (isset($invoice)) : ?>
        <div class="bg-white p-6 shadow-md rounded mb-4">
            <h2 class="font-bold mb-2">Chi Tiết Hóa Đơn</h2>
            <p><strong>ID Hóa Đơn:</strong> <?= $invoice['ID_HD']; ?></p>
            <p><strong>Dịch Vụ:</strong> <?= $appointment['TEN_DV']; ?></p>
            <p><strong>Thời Gian Bắt Đầu:</strong> <?= $appointment['THOI_GIAN_BAT_DAU']; ?></p>
            <p><strong>Thời Gian Kết Thúc:</strong> <?= $appointment['THOI_GIAN_KET_THUC']; ?></p>
            <p><strong>Thời Gian (Giờ):</strong> <?= $hours; ?> giờ</p>
            <p><strong>Đơn Giá:</strong> <?= number_format($appointment['DON_GIA'], 0, ',', '.'); ?> VND/giờ</p>
            <p><strong>Tổng Tiền:</strong> <?= number_format($invoice['TONG_TIEN'], 0, ',', '.'); ?> VND</p>
        </div>
    <?php else : ?>
        <div class="bg-red-500 text-white p-3 rounded mb-4">
            Không tìm thấy thông tin hóa đơn cho lịch hẹn này.
        </div>
    <?php endif; ?>

    <a href="index.php" class="bg-gray-500 text-white p-2 mt-4 rounded">Trở Lại</a>
</body>

</html>

<?php
// Close database connection
mysqli_close($conn);
?>
