<?php
// JSON endpoint: delete branch with safety checks
header('Content-Type: application/json; charset=utf-8');
try {
    include '../../database/config.php';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['ok'=>false,'message'=>'Method not allowed']); exit;
    }
    $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
    if ($branch_id <= 0) { echo json_encode(['ok'=>false,'message'=>'Thiếu hoặc sai branch_id']); exit; }

    // Check links
    $stmt1 = $conn->prepare('SELECT COUNT(*) AS total FROM nhan_vien WHERE ID_CN = ?');
    $stmt1->bind_param('i', $branch_id); $stmt1->execute(); $res1 = $stmt1->get_result()->fetch_assoc()['total'] ?? 0;

    $stmt2 = $conn->prepare('SELECT COUNT(*) AS total FROM trang_thiet_bi WHERE ID_CN = ?');
    $stmt2->bind_param('i', $branch_id); $stmt2->execute(); $res2 = $stmt2->get_result()->fetch_assoc()['total'] ?? 0;

    $stmt3 = $conn->prepare("SELECT COUNT(*) AS total FROM lich_hen WHERE ID_CHINHANH = ? AND TRANGTHAI = 'Đã xác nhận'");
    $stmt3->bind_param('i', $branch_id); $stmt3->execute(); $res3 = $stmt3->get_result()->fetch_assoc()['total'] ?? 0;

    if ($res1 > 0 || $res2 > 0 || $res3 > 0) {
        echo json_encode([
            'ok'=>false,
            'reason'=>'has_links',
            'message'=>'Không thể xóa',
            'counts'=>['employees'=>$res1,'devices'=>$res2,'appointments'=>$res3]
        ]); exit;
    }

    $del = $conn->prepare('DELETE FROM chi_nhanh WHERE ID_CN = ?');
    $del->bind_param('i', $branch_id);
    if ($del->execute()) {
        echo json_encode(['ok'=>true,'message'=>'Xóa chi nhánh thành công']);
    } else {
        echo json_encode(['ok'=>false,'message'=>'Lỗi xóa: '.$del->error]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Server error','error'=>$e->getMessage()]);
}
