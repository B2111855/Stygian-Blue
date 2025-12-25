<?php
/**
 * API: Quản lý Loại Chi Phí
 * File: app/admin/components/api_chi_phi_loai.php
 * 
 * Endpoints:
 * - GET: Lấy danh sách loại chi phí
 * - POST: Thêm loại chi phí mới
 * - PUT: Cập nhật loại chi phí
 * - DELETE: Xóa loại chi phí
 */

include '../../../database/config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                // Lấy danh sách loại chi phí
                $all = $_GET['all'] ?? 0;
                $whereClause = $all ? '' : "WHERE TRANG_THAI = 'active'";
                
                $query = "SELECT * FROM chi_phi_loai $whereClause ORDER BY TEN_LOAI";
                $result = mysqli_query($conn, $query);
                
                if (!$result) {
                    throw new Exception(mysqli_error($conn));
                }
                
                $loais = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $loais[] = $row;
                }
                
                echo json_encode([
                    'status' => 'success',
                    'data' => $loais
                ]);
            } elseif ($action === 'detail') {
                // Lấy chi tiết 1 loại chi phí
                $id = (int)$_GET['id'];
                $query = "SELECT * FROM chi_phi_loai WHERE ID_LOAI = $id";
                $result = mysqli_query($conn, $query);
                
                if (!$result) {
                    throw new Exception(mysqli_error($conn));
                }
                
                $loai = mysqli_fetch_assoc($result);
                
                echo json_encode([
                    'status' => 'success',
                    'data' => $loai
                ]);
            }
            break;

        case 'POST':
            // Thêm loại chi phí mới
            $ten_loai = mysqli_real_escape_string($conn, $_POST['ten_loai'] ?? '');
            $mota_loai = mysqli_real_escape_string($conn, $_POST['mota_loai'] ?? '');
            $trang_thai = $_POST['trang_thai'] ?? 'active';
            
            if (empty($ten_loai)) {
                throw new Exception('Tên loại chi phí không được để trống');
            }
            
            if (!in_array($trang_thai, ['active', 'inactive'], true)) {
                $trang_thai = 'active';
            }
            
            // Kiểm tra tên đã tồn tại
            $check = mysqli_query($conn, "SELECT ID_LOAI FROM chi_phi_loai WHERE TEN_LOAI = '$ten_loai'");
            if (mysqli_num_rows($check) > 0) {
                throw new Exception('Loại chi phí này đã tồn tại');
            }
            
            $query = "INSERT INTO chi_phi_loai (TEN_LOAI, MOTA_LOAI, TRANG_THAI)
                     VALUES ('$ten_loai', '$mota_loai', '$trang_thai')";
            
            if (!mysqli_query($conn, $query)) {
                throw new Exception(mysqli_error($conn));
            }
            
            $id_loai = mysqli_insert_id($conn);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Loại chi phí đã được tạo',
                'id_loai' => $id_loai
            ]);
            break;

        case 'PUT':
            // Cập nhật loại chi phí
            parse_str(file_get_contents("php://input"), $_PUT);
            
            $id_loai = (int)($_PUT['id_loai'] ?? 0);
            $ten_loai = mysqli_real_escape_string($conn, $_PUT['ten_loai'] ?? '');
            $mota_loai = mysqli_real_escape_string($conn, $_PUT['mota_loai'] ?? '');
            $trang_thai = $_PUT['trang_thai'] ?? null;
            
            if (!$id_loai) {
                throw new Exception('ID loại chi phí không hợp lệ');
            }
            
            if (empty($ten_loai)) {
                throw new Exception('Tên loại chi phí không được để trống');
            }
            
            if ($trang_thai !== null && !in_array($trang_thai, ['active', 'inactive'], true)) {
                throw new Exception('Trạng thái không hợp lệ');
            }
            
            // Kiểm tra tên đã tồn tại (ngoại trừ bản ghi hiện tại)
            $check = mysqli_query($conn, "SELECT ID_LOAI FROM chi_phi_loai 
                                         WHERE TEN_LOAI = '$ten_loai' AND ID_LOAI != $id_loai");
            if (mysqli_num_rows($check) > 0) {
                throw new Exception('Tên loại chi phí này đã tồn tại');
            }
            
            $fields = "TEN_LOAI = '$ten_loai', MOTA_LOAI = '$mota_loai'";
            if ($trang_thai !== null) {
                $fields .= ", TRANG_THAI = '$trang_thai'";
            }
            
            $query = "UPDATE chi_phi_loai SET $fields WHERE ID_LOAI = $id_loai";
            
            if (!mysqli_query($conn, $query)) {
                throw new Exception(mysqli_error($conn));
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Loại chi phí đã được cập nhật'
            ]);
            break;

        case 'DELETE':
            // Xóa loại chi phí (hard delete - xóa vĩnh viễn)
            $id_loai = (int)($_GET['id'] ?? 0);
            
            if (!$id_loai) {
                throw new Exception('ID loại chi phí không hợp lệ');
            }
            
            // Kiểm tra nếu có dữ liệu chi phí sử dụng loại này, xóa dữ liệu trước
            $deleteExpenses = "DELETE FROM chi_phi_phat_sinh WHERE ID_LOAI = $id_loai";
            if (!mysqli_query($conn, $deleteExpenses)) {
                throw new Exception('Lỗi xóa dữ liệu chi phí: ' . mysqli_error($conn));
            }
            
            // Sau đó xóa loại chi phí
            $query = "DELETE FROM chi_phi_loai WHERE ID_LOAI = $id_loai";
            
            if (!mysqli_query($conn, $query)) {
                throw new Exception(mysqli_error($conn));
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Loại chi phí và dữ liệu liên quan đã được xóa vĩnh viễn'
            ]);
            break;

        default:
            throw new Exception('Method không hỗ trợ');
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
