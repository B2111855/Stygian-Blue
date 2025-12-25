<?php
/**
 * Controller: Auto-copy Chi Phí từ tháng trước
 * File: app/admin/components/controller_copy_previous_month.php
 * 
 * Chức năng: Tự động copy giá trị chi phí từ tháng trước nếu tháng này chưa có
 * Gọi từ: manage_finances.php hoặc scheduler
 * 
 * Usage:
 * POST /controller_copy_previous_month.php
 * {
 *   "id_cn": 2,           // Chi nhánh
 *   "thang_dich": "2025-12"  // Tháng đích (nếu không có, mặc định là tháng hiện tại)
 * }
 */

include '../../../database/config.php';

header('Content-Type: application/json');

try {
    $id_cn = (int)($_POST['id_cn'] ?? 0);
    $thang_dich = $_POST['thang_dich'] ?? date('Y-m');
    
    if (!$id_cn) {
        throw new Exception('Chi nhánh không hợp lệ');
    }
    
    // Tính tháng trước
    $dateObj = DateTime::createFromFormat('Y-m', $thang_dich);
    if (!$dateObj) {
        throw new Exception('Tháng không hợp lệ');
    }
    
    $dateObj->modify('-1 month');
    $thang_nguon = $dateObj->format('Y-m');
    
    // Lấy danh sách chi phí từ tháng trước
    $query = "SELECT ID_LOAI, GIA_TRI, MOTA_CP 
              FROM chi_phi_phat_sinh 
              WHERE ID_CN = $id_cn AND THANG = '$thang_nguon'";
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }
    
    $chi_phi_tong = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $chi_phi_tong[] = $row;
    }
    
    if (empty($chi_phi_tong)) {
        echo json_encode([
            'status' => 'info',
            'message' => "Tháng $thang_nguon không có chi phí để copy"
        ]);
        exit;
    }
    
    // Copy các chi phí sang tháng dich (chỉ insert nếu chưa có)
    $da_copy = 0;
    $da_co = 0;
    
    foreach ($chi_phi_tong as $cp) {
        $id_loai = $cp['ID_LOAI'];
        $gia_tri = $cp['GIA_TRI'];
        $mota_cp = mysqli_real_escape_string($conn, $cp['MOTA_CP']);
        
        // Kiểm tra xem đã có bản ghi này không
        $check = mysqli_query($conn, "SELECT ID_CP FROM chi_phi_phat_sinh 
                                     WHERE ID_LOAI = $id_loai 
                                     AND ID_CN = $id_cn 
                                     AND THANG = '$thang_dich'");
        
        if (mysqli_num_rows($check) > 0) {
            // Đã có rồi, không copy
            $da_co++;
        } else {
            // Chưa có, insert bản ghi mới
            $insert = "INSERT INTO chi_phi_phat_sinh (ID_LOAI, ID_CN, THANG, GIA_TRI, NGAY_GIO, MOTA_CP)
                      VALUES ($id_loai, $id_cn, '$thang_dich', $gia_tri, CURDATE(), '$mota_cp')";
            
            if (mysqli_query($conn, $insert)) {
                $da_copy++;
            } else {
                throw new Exception(mysqli_error($conn));
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => "Đã copy $da_copy chi phí từ tháng $thang_nguon",
        'da_copy' => $da_copy,
        'da_co' => $da_co,
        'thang_nguon' => $thang_nguon,
        'thang_dich' => $thang_dich
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
