<?php
// API: Kiểm tra trang phục có thuộc gói trang phục nào không
header('Content-Type: application/json');
include_once '../../../database/config.php';

$id_tp = isset($_GET['id_tp']) ? (int)$_GET['id_tp'] : 0;
$result = ['in_package' => false];

if ($id_tp > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM goi_trang_phuc_chi_tiet WHERE ID_TRANG_PHUC = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id_tp);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $result['in_package'] = ((int)$row['cnt'] > 0);
        }
        $stmt->close();
    }
}
echo json_encode($result);