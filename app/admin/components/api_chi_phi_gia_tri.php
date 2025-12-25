<?php
/**
 * API: Quản lý Giá Trị Chi Phí Theo Tháng
 * File: app/admin/components/api_chi_phi_gia_tri.php
 * 
 * Endpoints:
 * - GET ?action=list: Lấy danh sách chi phí trong tháng/chi nhánh
 * - GET ?action=history&id_loai=X: Lịch sử chi phí của 1 loại qua các tháng
 * - GET ?action=summary&thang=YYYY-MM: Tổng chi phí theo loại qua tất cả branches
 * - POST: Thêm/cập nhật giá trị chi phí
 * - POST ?action=copy: Sao chép từ tháng trước
 * - DELETE: Xóa chi phí (có logging)
 */

include '../../../database/config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Helper function: Log audit trail
function logAuditTrail($id_cp, $id_loai, $id_cn, $thang, $gia_tri_cu, $gia_tri_moi, $hanh_dong, $ghi_chu = '') {
    global $conn;
    $nguoi_tham_gia = $_SESSION['user_email'] ?? 'system';
    $ghi_chu = mysqli_real_escape_string($conn, $ghi_chu);
    
    $log_query = "INSERT INTO chi_phi_audit_log 
                  (ID_CP, ID_LOAI, ID_CN, THANG, GIA_TRI_CU, GIA_TRI_MOI, HANH_DONG, NGUOI_THAM_GIA, GHI_CHU, NGAY_GIO)
                  VALUES ($id_cp, $id_loai, $id_cn, '$thang', " .
                  ($gia_tri_cu !== null ? "$gia_tri_cu" : "NULL") . ", " .
                  ($gia_tri_moi !== null ? "$gia_tri_moi" : "NULL") . ", " .
                  "'$hanh_dong', '$nguoi_tham_gia', '$ghi_chu', NOW())";
    
    mysqli_query($conn, $log_query);
}

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                // Lấy danh sách chi phí của 1 chi nhánh trong 1 tháng
                $id_cn = (int)($_GET['id_cn'] ?? 0);
                $thang = $_GET['thang'] ?? date('Y-m');
                
                if (!$id_cn) {
                    throw new Exception('Chi nhánh không hợp lệ');
                }
                
                $query = "SELECT 
                           cp.ID_CP,
                           cp.ID_LOAI,
                           cpl.TEN_LOAI,
                           cpl.MOTA_LOAI,
                           cp.GIA_TRI,
                           cp.NGAY_GIO,
                           cp.MOTA_CP
                         FROM chi_phi_phat_sinh cp
                         LEFT JOIN chi_phi_loai cpl ON cp.ID_LOAI = cpl.ID_LOAI
                         WHERE cp.ID_CN = $id_cn 
                         AND cp.THANG = '$thang'
                         AND cpl.TRANG_THAI = 'active'
                         ORDER BY cpl.TEN_LOAI";
                
                $result = mysqli_query($conn, $query);
                if (!$result) {
                    throw new Exception(mysqli_error($conn));
                }
                
                $chi_phi = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $chi_phi[] = $row;
                }
                
                echo json_encode([
                    'status' => 'success',
                    'thang' => $thang,
                    'id_cn' => $id_cn,
                    'data' => $chi_phi
                ]);
            } elseif ($action === 'history') {
                // Lịch sử chi phí của 1 loại qua các tháng
                $id_loai = (int)($_GET['id_loai'] ?? 0);
                
                if (!$id_loai) {
                    throw new Exception('ID loại chi phí không hợp lệ');
                }
                
                $query = "SELECT 
                           cp.ID_CP,
                           cp.ID_LOAI,
                           cpl.TEN_LOAI,
                           cp.ID_CN,
                           cn.TEN_CN,
                           cp.THANG,
                           cp.GIA_TRI,
                           cp.NGAY_GIO,
                           cp.MOTA_CP
                         FROM chi_phi_phat_sinh cp
                         LEFT JOIN chi_phi_loai cpl ON cp.ID_LOAI = cpl.ID_LOAI
                         LEFT JOIN chi_nhanh cn ON cp.ID_CN = cn.ID_CN
                         WHERE cp.ID_LOAI = $id_loai
                         ORDER BY cp.THANG DESC, cn.TEN_CN ASC";
                
                $result = mysqli_query($conn, $query);
                if (!$result) {
                    throw new Exception(mysqli_error($conn));
                }
                
                $history = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $history[] = $row;
                }
                
                // Get loại name
                $loai_query = mysqli_query($conn, "SELECT TEN_LOAI FROM chi_phi_loai WHERE ID_LOAI = $id_loai");
                $loai_name = mysqli_fetch_assoc($loai_query)['TEN_LOAI'] ?? 'N/A';
                
                echo json_encode([
                    'status' => 'success',
                    'id_loai' => $id_loai,
                    'ten_loai' => $loai_name,
                    'data' => $history
                ]);
            } elseif ($action === 'summary') {
                // Tổng chi phí theo loại qua tất cả branches
                                $thang = $_GET['thang'] ?? date('Y-m');
                                $id_cn = (int)($_GET['id_cn'] ?? 0);

                                $whereParts = ["cp.THANG = '$thang'", "cpl.TRANG_THAI = 'active'"];
                                if ($id_cn > 0) {
                                        $whereParts[] = "cp.ID_CN = $id_cn";
                                }
                                $whereClause = implode(' AND ', $whereParts);
                
                                $query = "SELECT 
                                                     cp.ID_LOAI,
                                                     cpl.TEN_LOAI,
                                                     cp.THANG,
                                                     SUM(cp.GIA_TRI) as TONG_GIA_TRI,
                                                     COUNT(DISTINCT cp.ID_CN) as SO_CHI_NHANH
                                                 FROM chi_phi_phat_sinh cp
                                                 LEFT JOIN chi_phi_loai cpl ON cp.ID_LOAI = cpl.ID_LOAI
                                                 WHERE $whereClause
                                                 GROUP BY cp.ID_LOAI, cpl.TEN_LOAI, cp.THANG
                                                 ORDER BY TONG_GIA_TRI DESC";
                
                $result = mysqli_query($conn, $query);
                if (!$result) {
                    throw new Exception(mysqli_error($conn));
                }
                
                $summary = [];
                $tong_chung = 0;
                while ($row = mysqli_fetch_assoc($result)) {
                    $summary[] = $row;
                    $tong_chung += (int)$row['TONG_GIA_TRI'];
                }
                
                echo json_encode([
                    'status' => 'success',
                    'thang' => $thang,
                    'id_cn' => $id_cn,
                    'data' => $summary,
                    'tong_chung' => $tong_chung
                ]);
            } elseif ($action === 'details') {
                // Chi tiết chi phí theo tháng (có filter loại chi phí tùy chọn)
                $thang = $_GET['thang'] ?? date('Y-m');
                $id_loai = (int)($_GET['id_loai'] ?? 0);
                
                $whereClause = "cp.THANG = '$thang' AND cpl.TRANG_THAI = 'active'";
                if ($id_loai) {
                    $whereClause .= " AND cp.ID_LOAI = $id_loai";
                }
                
                $query = "SELECT 
                           cp.ID_CP,
                           cp.ID_LOAI,
                           cpl.TEN_LOAI,
                           cp.ID_CN,
                           cn.TEN_CN,
                           cp.GIA_TRI,
                           cp.NGAY_GIO,
                           cp.MOTA_CP
                         FROM chi_phi_phat_sinh cp
                         LEFT JOIN chi_phi_loai cpl ON cp.ID_LOAI = cpl.ID_LOAI
                         LEFT JOIN chi_nhanh cn ON cp.ID_CN = cn.ID_CN
                         WHERE $whereClause
                         ORDER BY cn.TEN_CN ASC, cpl.TEN_LOAI ASC";
                
                $result = mysqli_query($conn, $query);
                if (!$result) {
                    throw new Exception(mysqli_error($conn));
                }
                
                $details = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $details[] = $row;
                }
                
                echo json_encode([
                    'status' => 'success',
                    'thang' => $thang,
                    'data' => $details
                ]);
            }
            break;

        case 'POST':
            if ($action === 'copy') {
                // Sao chép chi phí từ tháng trước
                $id_cn = (int)($_POST['id_cn'] ?? 0);
                $thang_nguon = $_POST['thang_nguon'] ?? '';
                $thang_dich = $_POST['thang_dich'] ?? date('Y-m');
                
                if (!$id_cn || !$thang_nguon) {
                    throw new Exception('Dữ liệu không hợp lệ');
                }
                
                $query = "INSERT INTO chi_phi_phat_sinh (ID_LOAI, ID_CN, THANG, GIA_TRI, NGAY_GIO)
                         SELECT ID_LOAI, ID_CN, '$thang_dich', GIA_TRI, CURDATE()
                         FROM chi_phi_phat_sinh
                         WHERE ID_CN = $id_cn AND THANG = '$thang_nguon'
                         ON DUPLICATE KEY UPDATE 
                           GIA_TRI = VALUES(GIA_TRI),
                           NGAY_GIO = VALUES(NGAY_GIO)";
                
                if (!mysqli_query($conn, $query)) {
                    throw new Exception(mysqli_error($conn));
                }
                
                echo json_encode([
                    'status' => 'success',
                    'message' => "Đã sao chép chi phí từ tháng $thang_nguon sang $thang_dich"
                ]);
            } else {
                // Thêm/cập nhật giá trị chi phí
                $id_loai = (int)($_POST['id_loai'] ?? 0);
                $id_cn = (int)($_POST['id_cn'] ?? 0);
                $thang = $_POST['thang'] ?? date('Y-m');
                $gia_tri = (int)($_POST['gia_tri'] ?? 0);
                $mota = mysqli_real_escape_string($conn, $_POST['mota_cp'] ?? '');
                
                if (!$id_loai || !$id_cn || $gia_tri <= 0) {
                    throw new Exception('Dữ liệu không hợp lệ');
                }
                
                // Kiểm tra xem đã có bản ghi này không
                $check = mysqli_query($conn, "SELECT ID_CP, GIA_TRI FROM chi_phi_phat_sinh 
                                             WHERE ID_LOAI = $id_loai 
                                             AND ID_CN = $id_cn 
                                             AND THANG = '$thang'");
                
                if (mysqli_num_rows($check) > 0) {
                    // Cập nhật
                    $row = mysqli_fetch_assoc($check);
                    $id_cp = $row['ID_CP'];
                    $gia_tri_cu = $row['GIA_TRI'];
                    
                    $query = "UPDATE chi_phi_phat_sinh 
                             SET GIA_TRI = $gia_tri, MOTA_CP = '$mota', NGAY_GIO = NOW()
                             WHERE ID_CP = $id_cp";
                    
                    if (!mysqli_query($conn, $query)) {
                        throw new Exception(mysqli_error($conn));
                    }
                    
                    // Cập nhật tai_chinh: xóa bản ghi cũ, thêm bản ghi mới
                    $deleteFinanceQuery = "DELETE FROM tai_chinh WHERE ID_CN = $id_cn AND LOAI_GIAO_DICH = 'chi phí' AND LOAI_CHI_TIET = 'Chi phí phát sinh' AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = '$thang'";
                    mysqli_query($conn, $deleteFinanceQuery);
                    
                    $firstOfMonth = date('Y-m-01');
                    $financeInsertQuery = "INSERT INTO tai_chinh (LOAI_GIAO_DICH, SO_TIEN, LOAI_CHI_TIET, NGAY_GIAO_DICH, ID_CN)
                                          VALUES ('chi phí', $gia_tri, 'Chi phí phát sinh', '$firstOfMonth', $id_cn)";
                    
                    if (!mysqli_query($conn, $financeInsertQuery)) {
                        throw new Exception(mysqli_error($conn));
                    }
                    
                    // Log audit
                    logAuditTrail($id_cp, $id_loai, $id_cn, $thang, $gia_tri_cu, $gia_tri, 'update', "Cập nhật chi phí");
                } else {
                    // Thêm mới
                    $query = "INSERT INTO chi_phi_phat_sinh (ID_LOAI, ID_CN, THANG, GIA_TRI, NGAY_GIO, MOTA_CP)
                             VALUES ($id_loai, $id_cn, '$thang', $gia_tri, NOW(), '$mota')";
                    
                    if (!mysqli_query($conn, $query)) {
                        throw new Exception(mysqli_error($conn));
                    }
                    
                    // Get inserted ID
                    $id_cp = mysqli_insert_id($conn);
                    
                    // Ghi vào tai_chinh
                    $firstOfMonth = date('Y-m-01');
                    $financeInsertQuery = "INSERT INTO tai_chinh (LOAI_GIAO_DICH, SO_TIEN, LOAI_CHI_TIET, NGAY_GIAO_DICH, ID_CN)
                                          VALUES ('chi phí', $gia_tri, 'Chi phí phát sinh', '$firstOfMonth', $id_cn)";
                    
                    if (!mysqli_query($conn, $financeInsertQuery)) {
                        throw new Exception(mysqli_error($conn));
                    }
                    
                    // Log audit
                    logAuditTrail($id_cp, $id_loai, $id_cn, $thang, null, $gia_tri, 'insert', "Thêm chi phí mới");
                }
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Giá trị chi phí đã được cập nhật'
                ]);
            }
            break;

        case 'PUT':
            // Xử lý cập nhật chi phí (gọi từ JavaScript fetch với method PUT)
            parse_str(file_get_contents("php://input"), $_PUT);
            $id_cp = (int)($_GET['id'] ?? $_PUT['id'] ?? 0);
            
            if (!$id_cp) {
                throw new Exception('ID chi phí không hợp lệ');
            }
            
            // Get old values before update
            $old_data = mysqli_query($conn, "SELECT ID_LOAI, ID_CN, THANG, GIA_TRI FROM chi_phi_phat_sinh WHERE ID_CP = $id_cp");
            if (mysqli_num_rows($old_data) === 0) {
                throw new Exception('Chi phí không tồn tại');
            }
            
            $row = mysqli_fetch_assoc($old_data);
            $id_loai = (int)($_PUT['id_loai'] ?? $row['ID_LOAI']);
            $id_cn = (int)($_PUT['id_cn'] ?? $row['ID_CN']);
            $thang = $_PUT['thang'] ?? $row['THANG'];
            $gia_tri = (int)($_PUT['gia_tri'] ?? $row['GIA_TRI']);
            $mota = mysqli_real_escape_string($conn, $_PUT['mota_cp'] ?? '');
            
            $gia_tri_cu = $row['GIA_TRI'];
            
            $query = "UPDATE chi_phi_phat_sinh 
                     SET GIA_TRI = $gia_tri, MOTA_CP = '$mota', NGAY_GIO = NOW()
                     WHERE ID_CP = $id_cp";
            
            if (!mysqli_query($conn, $query)) {
                throw new Exception(mysqli_error($conn));
            }
            
            // Cập nhật tai_chinh: xóa bản ghi cũ, thêm bản ghi mới
            $deleteFinanceQuery = "DELETE FROM tai_chinh WHERE ID_CN = $id_cn AND LOAI_GIAO_DICH = 'chi phí' AND LOAI_CHI_TIET = 'Chi phí phát sinh' AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = '$thang'";
            mysqli_query($conn, $deleteFinanceQuery);
            
            $firstOfMonth = date('Y-m-01');
            $financeInsertQuery = "INSERT INTO tai_chinh (LOAI_GIAO_DICH, SO_TIEN, LOAI_CHI_TIET, NGAY_GIAO_DICH, ID_CN)
                                  VALUES ('chi phí', $gia_tri, 'Chi phí phát sinh', '$firstOfMonth', $id_cn)";
            
            if (!mysqli_query($conn, $financeInsertQuery)) {
                throw new Exception(mysqli_error($conn));
            }
            
            // Log audit
            logAuditTrail($id_cp, $id_loai, $id_cn, $thang, $gia_tri_cu, $gia_tri, 'update', "Cập nhật chi phí");
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Chi phí đã được cập nhật'
            ]);
            break;

        case 'DELETE':
            // Xóa chi phí
            $id_cp = (int)($_GET['id'] ?? 0);
            
            if (!$id_cp) {
                throw new Exception('ID chi phí không hợp lệ');
            }
            
            // Get old values before delete
            $old_data = mysqli_query($conn, "SELECT ID_LOAI, ID_CN, THANG, GIA_TRI FROM chi_phi_phat_sinh WHERE ID_CP = $id_cp");
            if (mysqli_num_rows($old_data) === 0) {
                throw new Exception('Chi phí không tồn tại');
            }
            
            $row = mysqli_fetch_assoc($old_data);
            $id_loai = $row['ID_LOAI'];
            $id_cn = $row['ID_CN'];
            $thang = $row['THANG'];
            $gia_tri = $row['GIA_TRI'];
            
            $query = "DELETE FROM chi_phi_phat_sinh WHERE ID_CP = $id_cp";
            
            if (!mysqli_query($conn, $query)) {
                throw new Exception(mysqli_error($conn));
            }
            
            // Xóa từ tai_chinh
            $deleteFinanceQuery = "DELETE FROM tai_chinh WHERE ID_CN = $id_cn AND LOAI_GIAO_DICH = 'chi phí' AND LOAI_CHI_TIET = 'Chi phí phát sinh' AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = '$thang'";
            mysqli_query($conn, $deleteFinanceQuery);
            
            // Log audit
            logAuditTrail($id_cp, $id_loai, $id_cn, $thang, $gia_tri, null, 'delete', "Xóa chi phí");
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Chi phí đã được xóa'
            ]);
            break;

        default:
            throw new Exception('Action không hỗ trợ');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
