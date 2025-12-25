<?php
/**
 * Script để xóa dữ liệu lỗi: Chi phí mặt bằng không có ID chi nhánh hợp lệ
 * 
 * Vấn đề: Có bản ghi chi phí "Chi phí mặt bằng" (ID_LOAI = ?) với ID_CN không tồn tại trong bảng chi_nhanh
 */

include __DIR__ . '/../../../database/config.php';

// Kiểm tra loại chi phí "Chi phí mặt bằng"
$checkLoai = mysqli_query($conn, "
    SELECT ID_LOAI, TEN_LOAI 
    FROM chi_phi_loai 
    WHERE TEN_LOAI LIKE '%mặt bằng%' OR TEN_LOAI LIKE '%mat bang%'
");

echo "=== Tìm loại chi phí 'Chi phí mặt bằng' ===\n";
$loaiData = [];
while ($row = mysqli_fetch_assoc($checkLoai)) {
    echo "Tìm thấy: ID_LOAI = " . $row['ID_LOAI'] . ", TEN_LOAI = " . $row['TEN_LOAI'] . "\n";
    $loaiData[] = $row['ID_LOAI'];
}

if (empty($loaiData)) {
    echo "❌ Không tìm thấy loại chi phí 'Chi phí mặt bằng'\n";
    mysqli_close($conn);
    exit;
}

echo "\n=== Tìm bản ghi chi phí có ID_CN không hợp lệ ===\n";

// Lấy danh sách ID chi nhánh hợp lệ
$validBranches = [];
$branchQuery = mysqli_query($conn, "SELECT ID_CN FROM chi_nhanh");
while ($row = mysqli_fetch_assoc($branchQuery)) {
    $validBranches[] = $row['ID_CN'];
}

echo "Danh sách ID chi nhánh hợp lệ: " . implode(", ", $validBranches) . "\n\n";

// Tìm bản ghi chi phí lỗi
foreach ($loaiData as $idLoai) {
    $invalidQuery = mysqli_query($conn, "
        SELECT cp.ID_CP, cp.ID_LOAI, cp.ID_CN, cp.THANG, cp.GIA_TRI, cp.NGAY_GIO
        FROM chi_phi_phat_sinh cp
        WHERE cp.ID_LOAI = $idLoai
        AND cp.ID_CN NOT IN (" . implode(",", $validBranches) . ")
    ");

    echo "Kiểm tra ID_LOAI = $idLoai:\n";
    
    if (mysqli_num_rows($invalidQuery) > 0) {
        echo "Tìm thấy " . mysqli_num_rows($invalidQuery) . " bản ghi lỗi:\n";
        
        $invalidIds = [];
        while ($row = mysqli_fetch_assoc($invalidQuery)) {
            echo "  - ID_CP: {$row['ID_CP']}, ID_CN: {$row['ID_CN']}, THANG: {$row['THANG']}, GIA_TRI: {$row['GIA_TRI']}\n";
            $invalidIds[] = $row['ID_CP'];
        }

        // Xóa bản ghi lỗi
        if (!empty($invalidIds)) {
            $idList = implode(",", $invalidIds);
            $deleteQuery = "DELETE FROM chi_phi_phat_sinh WHERE ID_CP IN ($idList)";
            
            echo "\nThực hiện xóa...\n";
            echo "Query: $deleteQuery\n";
            
            if (mysqli_query($conn, $deleteQuery)) {
                echo "✅ Đã xóa " . count($invalidIds) . " bản ghi lỗi thành công!\n";
            } else {
                echo "❌ Lỗi xóa: " . mysqli_error($conn) . "\n";
            }
        }
    } else {
        echo "✅ Không có bản ghi lỗi\n";
    }
    echo "\n";
}

mysqli_close($conn);
echo "=== Hoàn thành ===\n";
?>
