<?php
// Include the database connection
include '../../database/config.php';

// Check if the invoice request is set
if (isset($_GET['id_lichhen'])) {
    $id_lichhen = (int)$_GET['id_lichhen'];

    // 1) Lấy danh sách dịch vụ đã đặt từ BOOKING_ITEM (ITEM_TYPE='service')
    $services = [];
    $sumServices = 0;
    $stmtSvc = mysqli_prepare($conn, "
        SELECT bi.REF_ID AS ID_DV, dv.TEN_DV, bi.DON_GIA, bi.SO_LUONG
        FROM BOOKING_ITEM bi
        JOIN DICH_VU dv ON dv.ID_DV = bi.REF_ID
        WHERE bi.ID_LICHHEN = ? AND bi.ITEM_TYPE = 'service'
        ORDER BY bi.ID_ITEM ASC
    ");
    if ($stmtSvc) {
        mysqli_stmt_bind_param($stmtSvc, 'i', $id_lichhen);
        mysqli_stmt_execute($stmtSvc);
        $resSvc = mysqli_stmt_get_result($stmtSvc);
        while ($resSvc && $row = mysqli_fetch_assoc($resSvc)) {
            $row['DON_GIA'] = (float)$row['DON_GIA'];
            $row['SO_LUONG'] = (int)$row['SO_LUONG'];
            $row['THANH_TIEN'] = $row['DON_GIA'] * $row['SO_LUONG'];
            $services[] = $row;
            $sumServices += $row['THANH_TIEN'];
        }
        mysqli_stmt_close($stmtSvc);
    }

    // 2) Lấy phụ phí di chuyển (nếu có cột TRAVEL_FEE)
    $travelFee = 0;
    if ($colCheck = mysqli_query($conn, "SHOW COLUMNS FROM lich_hen LIKE 'TRAVEL_FEE'")) {
        if (mysqli_num_rows($colCheck) > 0) {
            $stmtTF = mysqli_prepare($conn, "SELECT COALESCE(TRAVEL_FEE,0) AS TF FROM lich_hen WHERE ID_LICHHEN = ?");
            if ($stmtTF) {
                mysqli_stmt_bind_param($stmtTF, 'i', $id_lichhen);
                mysqli_stmt_execute($stmtTF);
                $resTF = mysqli_stmt_get_result($stmtTF);
                $rowTF = $resTF ? mysqli_fetch_assoc($resTF) : null;
                $travelFee = (float)($rowTF['TF'] ?? 0);
                mysqli_stmt_close($stmtTF);
            }
        }
        mysqli_free_result($colCheck);
    }

    // 3) Tổng tiền hóa đơn = tổng dịch vụ + phụ phí
    $total_price = $sumServices + $travelFee;

    // 4) Tạo hóa đơn và ghi chi tiết từng dịch vụ
    if ($total_price > 0) {
        // Tạo hóa đơn
        $stmtIns = mysqli_prepare($conn, "INSERT INTO hoa_don (ID_LICHHEN, NGAY_GIO, TONG_TIEN, TRANGTHAI_THANHTOAN) VALUES (?, NOW(), ?, 'Chưa thanh toán')");
        if ($stmtIns) {
            mysqli_stmt_bind_param($stmtIns, 'id', $id_lichhen, $total_price);
            if (mysqli_stmt_execute($stmtIns)) {
                $id_hd_new = mysqli_insert_id($conn);

                // Ghi line items nếu có bảng chi_tiet_hoa_don
                $hasDetailTable = false;
                if ($tblCheck = mysqli_query($conn, "SHOW TABLES LIKE 'chi_tiet_hoa_don'")) {
                    $hasDetailTable = mysqli_num_rows($tblCheck) > 0;
                    mysqli_free_result($tblCheck);
                }

                if ($hasDetailTable && !empty($services)) {
                    $stmtLine = mysqli_prepare($conn, "INSERT INTO chi_tiet_hoa_don (ID_HD, MO_TA, SO_LUONG, DON_GIA, THANH_TIEN) VALUES (?, ?, ?, ?, ?)");
                    if ($stmtLine) {
                        foreach ($services as $svc) {
                            $moTa = 'Dịch vụ: ' . ($svc['TEN_DV'] ?? ('#' . $svc['ID_DV']));
                            $soLuong = (int)$svc['SO_LUONG'];
                            $donGia = (float)$svc['DON_GIA'];
                            $thanhTien = (float)$svc['THANH_TIEN'];
                            mysqli_stmt_bind_param($stmtLine, 'isidd', $id_hd_new, $moTa, $soLuong, $donGia, $thanhTien);
                            mysqli_stmt_execute($stmtLine);
                        }
                        mysqli_stmt_close($stmtLine);
                    }
                    // Ghi phụ phí di chuyển thành một dòng riêng nếu > 0
                    if ($travelFee > 0) {
                        $stmtTFLine = mysqli_prepare($conn, "INSERT INTO chi_tiet_hoa_don (ID_HD, MO_TA, SO_LUONG, DON_GIA, THANH_TIEN) VALUES (?, 'Phụ phí di chuyển', 1, ?, ?)");
                        if ($stmtTFLine) {
                            mysqli_stmt_bind_param($stmtTFLine, 'idd', $id_hd_new, $travelFee, $travelFee);
                            mysqli_stmt_execute($stmtTFLine);
                            mysqli_stmt_close($stmtTFLine);
                        }
                    }
                }

                // Lấy lại hóa đơn vừa tạo để hiển thị
                $invoice_sql = "SELECT * FROM hoa_don WHERE ID_HD = $id_hd_new";
                $invoice_result = mysqli_query($conn, $invoice_sql);
                $invoice = $invoice_result ? mysqli_fetch_assoc($invoice_result) : null;

                // Chuẩn bị thông tin lịch hẹn để hiển thị
                $appointment = [
                    'ID_LICHHEN' => $id_lichhen,
                    'TEN_DV' => !empty($services) ? $services[0]['TEN_DV'] : 'Dịch vụ',
                    'THOI_GIAN_BAT_DAU' => '',
                    'THOI_GIAN_KET_THUC' => '',
                ];
                $stmtTime = mysqli_prepare($conn, "SELECT THOI_GIAN_BAT_DAU, THOI_GIAN_KET_THUC FROM lich_hen WHERE ID_LICHHEN = ?");
                if ($stmtTime) {
                    mysqli_stmt_bind_param($stmtTime, 'i', $id_lichhen);
                    mysqli_stmt_execute($stmtTime);
                    $resTime = mysqli_stmt_get_result($stmtTime);
                    $rowTime = $resTime ? mysqli_fetch_assoc($resTime) : null;
                    if ($rowTime) {
                        $appointment['THOI_GIAN_BAT_DAU'] = $rowTime['THOI_GIAN_BAT_DAU'];
                        $appointment['THOI_GIAN_KET_THUC'] = $rowTime['THOI_GIAN_KET_THUC'];
                    }
                    mysqli_stmt_close($stmtTime);
                }
            }
            mysqli_stmt_close($stmtIns);
        }
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
            <p><strong>Dịch Vụ:</strong> <?= htmlspecialchars($appointment['TEN_DV']); ?><?php if (!empty($services) && count($services) > 1): ?> (+<?= count($services) - 1 ?>)<?php endif; ?></p>
            <p><strong>Thời Gian Bắt Đầu:</strong> <?= $appointment['THOI_GIAN_BAT_DAU']; ?></p>
            <p><strong>Thời Gian Kết Thúc:</strong> <?= $appointment['THOI_GIAN_KET_THUC']; ?></p>
            <?php if (!empty($services)): ?>
            <div class="mt-2">
                <p class="font-semibold">Chi tiết dịch vụ:</p>
                <ul class="list-disc ml-5 text-sm">
                    <?php foreach ($services as $svc): ?>
                        <li>
                            <?= htmlspecialchars($svc['TEN_DV']); ?> × <?= (int)$svc['SO_LUONG']; ?> — <?= number_format($svc['DON_GIA'], 0, ',', '.'); ?> VND (thành tiền: <?= number_format($svc['THANH_TIEN'], 0, ',', '.'); ?>)
                        </li>
                    <?php endforeach; ?>
                    <?php if ($travelFee > 0): ?>
                        <li>Phụ phí di chuyển — <?= number_format($travelFee, 0, ',', '.'); ?> VND</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
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
