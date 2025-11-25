<?php
// Dedicated AJAX handler for services - NO HTML OUTPUT
header('Content-Type: application/json; charset=utf-8');

require_once '../../middlewares/require_admin.php';
require_once '../../database/config.php';
require_once __DIR__ . '/../../repositories/ServiceRepository.php';
require_once __DIR__ . '/../../services/DeletionPolicyService.php';
require_once __DIR__ . '/../../helpers/system_log.php';

use App\Repositories\ServiceRepository;
use App\Services\DeletionPolicyService;

$repo = new ServiceRepository($conn);
$policy = new DeletionPolicyService($conn);

// ============================================
// INLINE UPDATE HANDLER
// ============================================
if (isset($_POST['ajax_inline_update'])) {
    $id = (int)($_POST['ID_DV'] ?? 0);
    $newDuration = isset($_POST['THOI_GIAN']) ? (int)$_POST['THOI_GIAN'] : null;
    $newPrice = isset($_POST['GIA']) ? (int)$_POST['GIA'] : null;
    
    $current = $repo->findById($id);
    if (!$current) {
        echo json_encode(['ok' => false, 'error' => 'Không tìm thấy dịch vụ']);
        exit;
    }
    
    // Build payload
    $payload = [
        'ID_DV' => $id,
        'TEN_DV' => $current['TEN_DV'],
        'MOTA_DV' => $current['MOTA_DV'],
        'THOI_GIAN' => $newDuration !== null ? $newDuration : (int)$current['THOI_GIAN'],
    ];
    
    // Validate
    if ($newDuration !== null && $newDuration <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Thời lượng phải > 0']);
        exit;
    }
    if ($newPrice !== null && $newPrice <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Giá phải > 0']);
        exit;
    }
    
    if ($newPrice !== null) {
        $payload['GIA'] = $newPrice;
    }
    
    $before = $current;
    $ok = $repo->update($payload, null);
    
    if ($ok) {
        $after = $repo->findById($id);
        record_system_log($conn, 'SERVICE_INLINE_UPDATE', 'service:' . $id, $before, $after);
        
        $minutes = (int)$after['THOI_GIAN'];
        $h = floor($minutes / 60);
        $m = $minutes % 60;
        $durationStr = $h . ' giờ ' . $m . ' phút';
        
        // Get updated price
        $finalPrice = isset($after['DON_GIA']) ? (int)$after['DON_GIA'] : ($newPrice !== null ? $newPrice : 0);
        
        echo json_encode([
            'ok' => true,
            'ID_DV' => $id,
            'THOI_GIAN' => $after['THOI_GIAN'],
            'duration_fmt' => $durationStr,
            'DON_GIA' => $finalPrice,
            'DON_GIA_FMT' => $finalPrice > 0 ? number_format($finalPrice, 0, ',', '.') . ' ₫' : 'Chưa có'
        ]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Cập nhật thất bại']);
    }
    exit;
}

// ============================================
// BULK ACTIONS HANDLER
// ============================================
if (isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'] ?? '';
    $ids = isset($_POST['service_ids']) && is_array($_POST['service_ids']) ? array_map('intval', $_POST['service_ids']) : [];
    
    if (empty($ids)) {
        echo json_encode(['ok' => false, 'error' => 'Chưa chọn dịch vụ nào']);
        exit;
    }
    
    $results = ['ok' => true, 'processed' => 0, 'failed' => 0, 'errors' => []];
    
    switch ($action) {
        case 'activate':
            foreach ($ids as $id) {
                $stmt = $conn->prepare("UPDATE dich_vu SET TRANG_THAI='active' WHERE ID_DV=? AND IS_DELETED=0");
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $results['processed']++;
                    record_system_log($conn, 'SERVICE_BULK_ACTIVATE', 'service:' . $id, null, ['ID_DV' => $id, 'TRANG_THAI' => 'active']);
                } else {
                    $results['failed']++;
                }
            }
            break;
            
        case 'draft':
            foreach ($ids as $id) {
                $stmt = $conn->prepare("UPDATE dich_vu SET TRANG_THAI='draft' WHERE ID_DV=? AND IS_DELETED=0");
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $results['processed']++;
                    record_system_log($conn, 'SERVICE_BULK_DRAFT', 'service:' . $id, null, ['ID_DV' => $id, 'TRANG_THAI' => 'draft']);
                } else {
                    $results['failed']++;
                }
            }
            break;
            
        case 'retire':
            foreach ($ids as $id) {
                if ($policy->canRetire($id)) {
                    $before = $repo->findById($id);
                    if ($repo->retire($id)) {
                        $results['processed']++;
                        record_system_log($conn, 'SERVICE_BULK_RETIRE', 'service:' . $id, $before, ['TRANG_THAI' => 'retired']);
                    } else {
                        $results['failed']++;
                    }
                } else {
                    $results['failed']++;
                    $reasons = $policy->serviceDeletionCheck($id);
                    $results['errors'][] = "ID $id: " . $policy->formatReasons($reasons);
                }
            }
            break;
            
        default:
            echo json_encode(['ok' => false, 'error' => 'Hành động không hợp lệ']);
            exit;
    }
    
    echo json_encode($results);
    exit;
}

// No valid action
echo json_encode(['ok' => false, 'error' => 'Invalid request']);
exit;
