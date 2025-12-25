<?php
/**
 * check_availability.php
 * 
 * Endpoint kiểm tra khả dụng khoảng thời gian lịch hẹn.
 * Dùng bởi UI (lienhe.php) khi khách chọn slot thời gian.
 * 
 * Input: POST JSON
 * - branch_id: int
 * - start: "YYYY-MM-DD HH:MM:SS"
 * - booking_type: 'service' | 'package' | 'costume'
 * - service_ids: [int, ...] hoặc
 * - package_id: int
 * - location_type: 'branch' | 'external'
 * - ext_lat: float (nếu external)
 * - ext_lng: float (nếu external)
 * - distance_km: float (nếu đã tính, tuỳ chọn)
 * 
 * Output: JSON
 * {
 *   "available": true/false,
 *   "reason": "Thông báo lý do",
 *   "end_time": "YYYY-MM-DD HH:MM:SS",
 *   "duration_min": int,
 *   "travel_buffer_min": int,
 *   "suggested_next_start": "YYYY-MM-DD HH:MM:SS" (nếu available=false)
 * }
 */

header('Content-Type: application/json');
session_start();

include '../../../database/config.php';
require_once '../../../vendor/autoload.php';

/**
 * KIỂM TRA TRANG PHỤC YÊU CẦU TRONG GÓI DỊCH VỤ
 * 
 * @param mysqli $conn - Database connection
 * @param int $packageId - ID gói dịch vụ
 * @param int $branchId - ID chi nhánh
 * @param DateTime $startTime - Thời gian bắt đầu
 * @param DateTime $endTime - Thời gian kết thúc
 * @return array ['available' => bool, 'reason' => string, 'unavailable_costumes' => array]
 * 
 * LOGIC:
 * 1. Lấy danh sách yêu cầu trang phục của gói (từ goi_trang_phuc_yeu_cau)
 * 2. Với mỗi yêu cầu, lấy trang phục được map cho chi nhánh (từ goi_yc_branch_trang_phuc)
 * 3. Kiểm tra từng trang phục có bị trùng lịch không (logic giống check_costume_availability.php)
 * 4. Nếu có trang phục nào unavailable → trả về false với thông báo rõ ràng
 */
function checkPackageCostumeRequirements($conn, $packageId, $branchId, $startTime, $endTime)
{
    $fromDt = $startTime->format('Y-m-d H:i:s');
    $toDt = $endTime->format('Y-m-d H:i:s');
    $cleaningBufferDays = 1; // Buffer vệ sinh sau khi trả trang phục
    
    // BƯỚC 1: Lấy danh sách yêu cầu trang phục qua bảng pivot goi_yeu_cau
    $requirements = [];
    
    // Check xem có bảng pivot không
    $pivotCheck = $conn->query("SHOW TABLES LIKE 'goi_yeu_cau'");
    $ycCheck = $conn->query("SHOW TABLES LIKE 'goi_trang_phuc_yeu_cau'");
    
    if (!$pivotCheck || $pivotCheck->num_rows === 0 || !$ycCheck || $ycCheck->num_rows === 0) {
        return ['available' => true, 'reason' => 'Bảng yêu cầu trang phục chưa tồn tại'];
    }
    
    // Query yêu cầu qua pivot table
    $sqlReq = "SELECT g.ID_YC, yc.ID_NHOM, yc.LOAI_ID, yc.LOAI_IDS_JSON, yc.SO_LUONG, yc.BAT_BUOC,
                      l.TEN_LOAI, n.TEN_NHOM
               FROM goi_yeu_cau g
               JOIN goi_trang_phuc_yeu_cau yc ON yc.ID_YC = g.ID_YC
               LEFT JOIN trang_phuc_loai l ON l.ID_LOAI = yc.LOAI_ID
               LEFT JOIN trang_phuc_nhom n ON n.ID_NHOM = yc.ID_NHOM
               WHERE g.ID_GOI = ?
               ORDER BY g.THU_TU ASC, g.ID_YC ASC";
    
    $stmtReq = $conn->prepare($sqlReq);
    if (!$stmtReq) {
        return ['available' => true, 'reason' => 'Không kiểm tra được yêu cầu trang phục (lỗi DB)'];
    }
    
    $stmtReq->bind_param('i', $packageId);
    if (!$stmtReq->execute()) {
        $stmtReq->close();
        return ['available' => true, 'reason' => 'Không kiểm tra được yêu cầu trang phục (lỗi query)'];
    }
    
    $result = $stmtReq->get_result();
    while ($row = $result->fetch_assoc()) {
        // Parse LOAI_IDS_JSON để lấy danh sách ID trang phục cụ thể
        $costumeIds = [];
        if (!empty($row['LOAI_IDS_JSON'])) {
            $decoded = json_decode($row['LOAI_IDS_JSON'], true);
            if (is_array($decoded)) {
                $costumeIds = array_filter(array_map('intval', $decoded));
            }
        }
        $row['costume_ids'] = $costumeIds;
        $requirements[] = $row;
    }
    $stmtReq->close();
    
    // Nếu không có yêu cầu trang phục → OK
    if (empty($requirements)) {
        return ['available' => true, 'reason' => 'Gói không yêu cầu trang phục'];
    }
    
    // BƯỚC 2: Kiểm tra từng yêu cầu
    $unavailableCostumes = [];
    
    foreach ($requirements as $req) {
        $reqId = (int)$req['ID_YC'];
        $reqMandatory = (int)($req['BAT_BUOC'] ?? 0);
        $reqName = $req['TEN_LOAI'] ?: $req['TEN_NHOM'] ?: ('Yêu cầu #' . $reqId);
        $costumeIds = $req['costume_ids'] ?? [];
        
        // Chỉ kiểm tra yêu cầu bắt buộc (tùy chọn có thể bỏ qua)
        if (!$reqMandatory) {
            continue;
        }
        
        // Kiểm tra có danh sách trang phục cụ thể không
        if (empty($costumeIds)) {
            $unavailableCostumes[] = [
                'requirement' => $reqName,
                'reason' => 'Chưa cấu hình trang phục cụ thể cho yêu cầu này'
            ];
            continue;
        }
        
        // BƯỚC 3: Kiểm tra từng trang phục có bị trùng lịch không
        foreach ($costumeIds as $costumeId) {
            $costumeId = (int)$costumeId;
            
            // Lấy thông tin trang phục
            $sqlCostume = "SELECT ID_TRANG_PHUC, TEN, TRANG_THAI, DELETED_AT 
                          FROM trang_phuc 
                          WHERE ID_TRANG_PHUC = ? AND ID_CN = ?";
            $stmtCostume = $conn->prepare($sqlCostume);
            if (!$stmtCostume) continue;
            
            $stmtCostume->bind_param('ii', $costumeId, $branchId);
            if (!$stmtCostume->execute()) {
                $stmtCostume->close();
                continue;
            }
            
            $resCostume = $stmtCostume->get_result();
            $costume = $resCostume->fetch_assoc();
            $stmtCostume->close();
            
            if (!$costume) {
                $unavailableCostumes[] = [
                    'requirement' => $reqName,
                    'costume_id' => $costumeId,
                    'reason' => 'Trang phục không tồn tại hoặc không thuộc chi nhánh này'
                ];
                continue;
            }
            
            $costumeName = $costume['TEN'];
            
            // Kiểm tra trang phục có bị xóa hoặc không available
            if ($costume['DELETED_AT'] !== null) {
                $unavailableCostumes[] = [
                    'costume_id' => $costumeId,
                    'costume_name' => $costumeName,
                    'requirement' => $reqName,
                    'reason' => 'Trang phục đã bị xóa'
                ];
                continue;
            }
            
            if (!in_array($costume['TRANG_THAI'], ['available', 'san_sang', 'AVAILABLE'])) {
                $unavailableCostumes[] = [
                    'costume_id' => $costumeId,
                    'costume_name' => $costumeName,
                    'requirement' => $reqName,
                    'reason' => 'Trang phục không ở trạng thái khả dụng'
                ];
                continue;
            }
            
            // Kiểm tra lịch trùng (giống logic check_costume_availability.php)
            $sqlConflict = "SELECT 
                dtt.ID_TTP, 
                dtt.NGAY_NHAN, 
                dtt.NGAY_TRA_DK,
                DATE_ADD(dtt.NGAY_TRA_DK, INTERVAL ? DAY) AS NGAY_KHA_DUNG,
                dtt.TRANG_THAI
            FROM don_thue_trang_phuc dtt
            JOIN don_thue_trang_phuc_ct ct ON ct.ID_TTP = dtt.ID_TTP
            WHERE ct.ID_TP = ?
              AND dtt.TRANG_THAI IN ('cho_duyet', 'da_duyet', 'dang_thue')
              AND (
                (? < DATE_ADD(dtt.NGAY_TRA_DK, INTERVAL ? DAY) AND ? > dtt.NGAY_NHAN)
              )
            ORDER BY dtt.NGAY_NHAN
            LIMIT 1";
            
            $stmtConflict = $conn->prepare($sqlConflict);
            if (!$stmtConflict) {
                continue;
            }
            
            $stmtConflict->bind_param('iisis', $cleaningBufferDays, $costumeId, $fromDt, $cleaningBufferDays, $toDt);
            if (!$stmtConflict->execute()) {
                $stmtConflict->close();
                continue;
            }
            
            $resConflict = $stmtConflict->get_result();
            $conflict = $resConflict->fetch_assoc();
            $stmtConflict->close();
            
            if ($conflict) {
                $nextAvailable = $conflict['NGAY_KHA_DUNG'];
                $unavailableCostumes[] = [
                    'costume_id' => $costumeId,
                    'costume_name' => $costumeName,
                    'requirement' => $reqName,
                    'reason' => 'Đã được đặt thuê trong khoảng thời gian này (bao gồm thời gian vệ sinh)',
                    'conflict_rental_id' => $conflict['ID_TTP'],
                    'next_available' => $nextAvailable,
                    'conflict_from' => $conflict['NGAY_NHAN'],
                    'conflict_to' => $conflict['NGAY_TRA_DK']
                ];
            }
        }
    }
    
    // BƯỚC 4: Trả kết quả
    if (empty($unavailableCostumes)) {
        return [
            'available' => true,
            'reason' => 'Tất cả trang phục yêu cầu đều khả dụng'
        ];
    }
    
    // Tạo thông báo chi tiết
    $reasons = [];
    foreach ($unavailableCostumes as $item) {
        if (isset($item['costume_name'])) {
            $msg = "• Trang phục \"{$item['costume_name']}\" ({$item['requirement']}): {$item['reason']}";
            if (isset($item['next_available'])) {
                $msg .= " - Có thể đặt từ: " . date('d/m/Y H:i', strtotime($item['next_available']));
            }
            $reasons[] = $msg;
        } else {
            // Trường hợp không có costume_name (costume không thuộc branch hoặc lỗi khác)
            $msg = "• {$item['requirement']}";
            if (isset($item['costume_id'])) {
                $msg .= " (ID: {$item['costume_id']})";
            }
            $msg .= ": {$item['reason']}";
            $reasons[] = $msg;
        }
    }
    
    $reasonText = "Một số trang phục yêu cầu không khả dụng:\n" . implode("\n", $reasons);
    
    return [
        'available' => false,
        'reason' => $reasonText,
        'unavailable_costumes' => $unavailableCostumes,
        'costume_check_failed' => true
    ];
}

// Lấy input từ POST (JSON hoặc form-data)
$input = json_decode(file_get_contents('php://input'), true);

if ((!$input || empty($input)) && PHP_SAPI === 'cli') {
    $stdin = stream_get_contents(STDIN);
    if ($stdin) {
        $decodedCliInput = json_decode($stdin, true);
        if (is_array($decodedCliInput)) {
            $input = $decodedCliInput;
        }
    }
}

if (!$input) {
    $input = $_POST;
}

error_log('[check_availability] Raw input: ' . json_encode($input));

/**
 * CHUẨN HÓA INPUT TỪ CLIENT
 * 
 * Nhận các tham số:
 * - branch_id: ID chi nhánh làm việc
 * - start: Thời gian bắt đầu (YYYY-MM-DD HH:MM:SS)
 * - booking_type: Loại đặt lịch (service/package/costume)
 * - service_ids: Mảng ID dịch vụ lẻ
 * - package_id: ID gói dịch vụ
 * - location_type: Loại địa điểm (branch/external)
 * - ext_lat, ext_lng: Tọa độ GPS nếu làm ngoài chi nhánh
 * - distance_km: Khoảng cách đến địa điểm (nếu đã tính)
 */
$branchId = $input['branch_id'] ?? null;
$startStr = $input['start'] ?? null;
$bookingType = $input['booking_type'] ?? 'service';
$serviceIdsRaw = $input['service_ids'] ?? [];
$packageId = $input['package_id'] ?? null;
$locationType = $input['location_type'] ?? 'branch';
$extLat = $input['ext_lat'] ?? null;
$extLng = $input['ext_lng'] ?? null;
$distanceKmRaw = $input['distance_km'] ?? null;

/**
 * SANITIZE VÀ CAST KIỂU DỮ LIỆU
 * Đảm bảo tất cả giá trị đều đúng kiểu để tránh SQL injection và lỗi logic
 */
$branchId = $branchId ? (int)$branchId : null;
$packageId = $packageId ? (int)$packageId : null;
$locationType = $locationType === 'external' ? 'external' : 'branch';
$extLat = is_numeric($extLat) ? (float)$extLat : null;
$extLng = is_numeric($extLng) ? (float)$extLng : null;
$distanceKm = is_numeric($distanceKmRaw) ? (float)$distanceKmRaw : null;

// Chuẩn hóa mảng service_ids: chỉ chấp nhận số nguyên dương
$serviceIds = [];
foreach ((array)$serviceIdsRaw as $sid) {
    if (ctype_digit((string)$sid)) {
        $serviceIds[] = (int)$sid;
    }
}

/**
 * PARSE THỜI GIAN BẮT ĐẦU
 * Hỗ trợ 2 format:
 * - Y-m-d H:i:s (ưu tiên)
 * - Y-m-d H:i (fallback)
 */
$startTime = null;
try {
    $startTime = DateTime::createFromFormat('Y-m-d H:i:s', $startStr);
    if (!$startTime) {
        $startTime = DateTime::createFromFormat('Y-m-d H:i', $startStr);
    }
} catch (Exception $e) {
    http_response_code(400);
    error_log('[check_availability] Invalid timestamp: ' . $startStr);
    echo json_encode([
        'available' => false,
        'reason' => 'Thời gian không hợp lệ: ' . $startStr
    ]);
    exit;
}

if (!$startTime) {
    http_response_code(400);
    error_log('[check_availability] Failed to parse timestamp: ' . $startStr);
    echo json_encode([
        'available' => false,
        'reason' => 'Thời gian không hợp lệ'
    ]);
    exit;
}

// Validate required fields
if (!$branchId) {
    http_response_code(400);
    error_log('[check_availability] Validation failed: Missing branchId. Input: ' . json_encode($input));
    echo json_encode([
        'available' => false,
        'reason' => 'Thiếu chi nhánh (branchId: ' . var_export($branchId, true) . ')'
    ]);
    exit;
}

if ($bookingType === 'service' && empty($serviceIds) && !$packageId) {
    http_response_code(400);
    error_log('[check_availability] Validation failed: Missing services/package. bookingType: ' . $bookingType . ', serviceIds: ' . json_encode($serviceIds) . ', packageId: ' . $packageId);
    echo json_encode([
        'available' => false,
        'reason' => 'Thiếu dịch vụ hoặc gói (serviceIds: ' . json_encode($serviceIds) . ', packageId: ' . $packageId . ')'
    ]);
    exit;
}

/**
 * =============================================
 * HELPER FUNCTIONS - CÁC HÀM HỖ TRỢ TÍNH TOÁN
 * =============================================
 */

/**
 * TÍNH TỔNG THỜI LƯỢNG DỊCH VỤ/GÓI (phút)
 * 
 * @param mysqli $conn - Database connection
 * @param string $bookingType - Loại đặt lịch ('service', 'package', 'costume')
 * @param mixed $serviceIdOrIds - ID dịch vụ đơn (int) hoặc mảng IDs (array)
 * @param int|null $packageId - ID gói dịch vụ
 * @return int Tổng thời lượng tính bằng phút
 * 
 * LOGIC:
 * 1. Nếu booking_type = 'service':
 *    - Query SUM(THOI_GIAN) từ bảng dich_vu cho tất cả service_ids
 *    - Fallback: DEFAULT_DURATION_MIN * số lượng dịch vụ
 * 
 * 2. Nếu booking_type = 'package':
 *    - Query SUM(THOI_GIAN) từ goi_dich_vu_chi_tiet JOIN dich_vu
 *    - Fallback: DEFAULT_REQUIRED_STAFF_PACKAGE * 30 phút
 * 
 * 3. Nếu booking_type = 'costume':
 *    - Return DEFAULT_DURATION_COSTUME (mặc định 30 phút)
 * 
 * ENV VARIABLES:
 * - DEFAULT_DURATION_MIN: Thời lượng mặc định cho 1 dịch vụ (60 phút)
 * - DEFAULT_REQUIRED_STAFF_PACKAGE: Số nhân viên cho gói (2)
 * - DEFAULT_DURATION_COSTUME: Thời lượng thuê trang phục (30 phút)
 */
function calcDurationMin(&$conn, $bookingType, $serviceIdOrIds, $packageId)
{
    $logFile = __DIR__ . '/../../check_availability.log';
    $defaultDuration = (int)getenv('DEFAULT_DURATION_MIN') ?: 60;
    
    file_put_contents($logFile, "[calcDurationMin] bookingType=$bookingType, serviceIdOrIds=" . json_encode($serviceIdOrIds) . ", packageId=$packageId\n", FILE_APPEND);
    
    if ($bookingType === 'service') {
        /**
         * XỬ LÝ DỊCH VỤ LẺ (MULTI-SERVICE SUPPORT)
         * Hỗ trợ cả ID đơn và mảng IDs để tính tổng thời lượng nhiều dịch vụ
         */
        $serviceIds = is_array($serviceIdOrIds) ? $serviceIdOrIds : ($serviceIdOrIds ? [$serviceIdOrIds] : []);
        
        file_put_contents($logFile, "[calcDurationMin] serviceIds after normalization: " . json_encode($serviceIds) . "\n", FILE_APPEND);
        
        if (!empty($serviceIds)) {
            // Tạo placeholders cho prepared statement (?,?,?...)
            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            // Query tổng thời gian từ DB
            $stmt = $conn->prepare("SELECT SUM(THOI_GIAN) as TOTAL_TIME FROM dich_vu WHERE ID_DV IN ($placeholders)");
            
            if ($stmt) {
                // Bind parameters
                $types = str_repeat('i', count($serviceIds));
                $stmt->bind_param($types, ...$serviceIds);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                
                file_put_contents($logFile, "[calcDurationMin] Query result: " . json_encode($row) . "\n", FILE_APPEND);
                
                if ($row && $row['TOTAL_TIME']) {
                    file_put_contents($logFile, "[calcDurationMin] Returning: " . $row['TOTAL_TIME'] . "\n", FILE_APPEND);
                    return (int)$row['TOTAL_TIME'];
                }
            }
            // Fallback: default per service if query fails
            $fallback = count($serviceIds) * $defaultDuration;
            file_put_contents($logFile, "[calcDurationMin] Returning fallback: $fallback\n", FILE_APPEND);
            return $fallback;
        }
    } elseif ($bookingType === 'package' && $packageId) {
        /**
         * XỬ LÝ GÓI DỊCH VỤ
         * Tính tổng thời lượng của TẤT CẢ dịch vụ trong gói
         * Query từ bảng goi_dich_vu_chi_tiet JOIN với dich_vu
         */
        $stmt = $conn->prepare(
            "SELECT SUM(d.THOI_GIAN) as TOTAL_TIME FROM goi_dich_vu_chi_tiet gdt 
             JOIN dich_vu d ON d.ID_DV = gdt.ID_DV 
             WHERE gdt.ID_GOI = ?"
        );
        if ($stmt) {
            $stmt->bind_param('i', $packageId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            if ($row && $row['TOTAL_TIME']) {
                return (int)$row['TOTAL_TIME'];
            }
        }
        return (int)getenv('DEFAULT_REQUIRED_STAFF_PACKAGE') ? 
            (int)getenv('DEFAULT_REQUIRED_STAFF_PACKAGE') * 30 : 120;
    } elseif ($bookingType === 'costume') {
        return (int)getenv('DEFAULT_DURATION_COSTUME') ?: 30;
    }
    
    return $defaultDuration;
}

/**
 * TÍNH THỜI GIAN DI CHUYỂN PHỤ (phút)
 * 
 * @param float $distanceKm - Khoảng cách tính bằng km
 * @return int Thời gian buffer tính bằng phút
 * 
 * CÔNG THỨC:
 * - Buffer tối thiểu: TRAVEL_BUFFER_MIN_BASE (15 phút)
 * - Buffer theo khoảng cách: distanceKm * TRAVEL_BUFFER_MIN_PER_KM (0.75 phút/km)
 * - Kết quả = max(baseMin, ceil(distanceKm * perKm))
 * 
 * VÍ DỤ:
 * - 5km: max(15, ceil(5 * 0.75)) = max(15, 4) = 15 phút
 * - 30km: max(15, ceil(30 * 0.75)) = max(15, 23) = 23 phút
 * 
 * MỤC ĐÍCH:
 * Dành thời gian cho nhân viên di chuyển đến địa điểm ngoài chi nhánh
 * (setup thiết bị, di chuyển, dọn dẹp)
 * 
 * ENV VARIABLES:
 * - TRAVEL_BUFFER_MIN_BASE: Thời gian tối thiểu (15)
 * - TRAVEL_BUFFER_MIN_PER_KM: Hệ số phút/km (0.75)
 */
function calcTravelBufferMin($distanceKm)
{
    if (!is_finite((float)$distanceKm) || $distanceKm <= 0) {
        return 0;
    }
    $baseMin = (int)getenv('TRAVEL_BUFFER_MIN_BASE') ?: 15;
    $perKm = (float)(getenv('TRAVEL_BUFFER_MIN_PER_KM') ?: 0.75);
    $buffer = (int)ceil(((float)$distanceKm * $perKm));
    return max($baseMin, $buffer);
}

/**
 * KIỂM TRA ĐỘC QUYỀN CHI NHÁNH (Branch Exclusivity)
 * 
 * @param mysqli $conn - Database connection
 * @param int $branchId - ID chi nhánh
 * @param DateTime $startTime - Thời gian bắt đầu muốn đặt
 * @param DateTime $endTime - Thời gian kết thúc dự kiến
 * @return array ['allowed' => bool, 'reason' => string, 'suggested_next_start' => string]
 * 
 * LOGIC:
 * Khi làm TẠI CHI NHÁNH (location_type='branch'), chi nhánh chỉ phục vụ
 * 1 lịch hẹn tại 1 thời điểm (độc quyền toàn bộ studio).
 * 
 * KIỂM TRA:
 * - Tìm các lịch hẹn khác tại cùng chi nhánh
 * - Có location_type = 'branch' (chỉ check lịch tại chỗ)
 * - Có trạng thái active ('Đang chờ', 'Xác nhận', 'Đã duyệt', 'Đã xác nhận')
 * - Có khoảng thời gian TRÙNG LẶP với slot muốn đặt
 * 
 * ĐIỀU KIỆN TRÙNG LẶP:
 * NOT (existing_end <= new_start OR existing_start >= new_end)
 * Tức là: existing_start < new_end AND existing_end > new_start
 * 
 * KẾT QUẢ:
 * - allowed=true: Slot trống, có thể đặt
 * - allowed=false: Đã có lịch trùng, gợi ý thời gian kế tiếp
 * 
 * ENV VARIABLES:
 * - ENABLE_BRANCH_EXCLUSIVITY: Bật/tắt kiểm tra (mặc định: 1)
 */
function checkBranchExclusivity(&$conn, $branchId, DateTime $startTime, DateTime $endTime)
{
    $enableCheck = (int)getenv('ENABLE_BRANCH_EXCLUSIVITY') ?: 1;
    if (!$enableCheck) {
        return ['allowed' => true, 'reason' => 'Kiểm tra độc quyền bị tắt'];
    }

    $startStr = $startTime->format('Y-m-d H:i:s');
    $endStr = $endTime->format('Y-m-d H:i:s');
    
    // Query tìm lịch hẹn trùng thời gian
    $sql = "SELECT ID_LICHHEN, THOI_GIAN_BAT_DAU, 
                   COALESCE(THOI_GIAN_KET_THUC, DATE_ADD(THOI_GIAN_BAT_DAU, INTERVAL 60 MINUTE)) as THOI_GIAN_KET_THUC
            FROM lich_hen 
            WHERE ID_CHINHANH = ? 
              AND LOCATION_TYPE = 'branch'
              AND TRANGTHAI IN ('Đang chờ', 'Xác nhận', 'Đã duyệt', 'Đã xác nhận')
              AND NOT (THOI_GIAN_KET_THUC <= ? OR THOI_GIAN_BAT_DAU >= ?)
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['allowed' => false, 'reason' => 'Lỗi kiểm tra dữ liệu'];
    }

    $stmt->bind_param('iss', $branchId, $startStr, $endStr);
    $stmt->execute();
    $result = $stmt->get_result();
    $conflictRow = $result->fetch_assoc();
    $stmt->close();

    if ($conflictRow) {
        return [
            'allowed' => false,
            'reason' => 'Khung giờ này đã có lịch khác tại chi nhánh.',
            'suggested_next_start' => $conflictRow['THOI_GIAN_KET_THUC']
        ];
    }

    return ['allowed' => true, 'reason' => 'Độc quyền chi nhánh thỏa'];
}

/**
 * KIỂM TRA NHÂN VIÊN RẢNH CHO LỊCH NGOÀI CHI NHÁNH
 * 
 * @param mysqli $conn - Database connection
 * @param int $branchId - ID chi nhánh (nguồn nhân viên)
 * @param DateTime $startTime - Thời gian bắt đầu
 * @param DateTime $endTime - Thời gian kết thúc
 * @param int $serviceCount - Số lượng dịch vụ
 * @param string $bookingType - Loại booking ('service', 'package', 'costume')
 * @return array ['allowed' => bool, 'reason' => string, 'staff_available' => int, 'staff_required' => int]
 * 
 * LOGIC:
 * Khi làm NGOÀI CHI NHÁNH (location_type='external'), không có độc quyền.
 * Nhưng cần đảm bảo đủ nhân viên rảnh để phục vụ.
 * 
 * TÍNH SỐ NHÂN VIÊN CẦN:
 * 1. Dịch vụ lẻ: serviceCount * DEFAULT_REQUIRED_STAFF_SERVICE
 *    (VD: 3 dịch vụ * 1 NV/dịch vụ = 3 NV)
 * 2. Gói dịch vụ: DEFAULT_REQUIRED_STAFF_PACKAGE (cố định, VD: 2 NV)
 * 3. Thuê trang phục: 1 NV
 * 
 * TÍNH SỐ NHÂN VIÊN RẢNH:
 * - Tổng NV: COUNT(*) FROM nhan_vien WHERE ID_CN = ? AND IS_DELETED = 0
 * - NV bận: COUNT(DISTINCT pcnv.ID_TK) FROM phan_cong_nhan_vien
 *   WHERE thời gian phân công TRÙNG với slot muốn đặt
 * - NV rảnh = Tổng NV - NV bận
 * 
 * ĐIỀU KIỆN PASS:
 * staff_available >= staff_required
 * 
 * ENV VARIABLES:
 * - ENABLE_EXTERNAL_STAFF_CHECK: Bật/tắt (mặc định: 1)
 * - DEFAULT_REQUIRED_STAFF_SERVICE: NV/dịch vụ (1)
 * - DEFAULT_REQUIRED_STAFF_PACKAGE: NV/gói (2)
 */
function checkExternalStaffAvailability(&$conn, $branchId, DateTime $startTime, DateTime $endTime, $serviceCount, $bookingType)
{
    $enableCheck = (int)getenv('ENABLE_EXTERNAL_STAFF_CHECK') ?: 1;
    if (!$enableCheck) {
        return ['allowed' => true, 'reason' => 'Kiểm tra nhân viên bị tắt'];
    }

    $defaultServiceStaff = (int)getenv('DEFAULT_REQUIRED_STAFF_SERVICE') ?: 1;
    $defaultPackageStaff = (int)getenv('DEFAULT_REQUIRED_STAFF_PACKAGE') ?: 2;
    
    // Tính số nhân viên cần thiết dựa trên loại booking
    $requiredStaff = 0;
    if ($bookingType === 'service') {
        $requiredStaff = $serviceCount * $defaultServiceStaff;
    } elseif ($bookingType === 'package') {
        $requiredStaff = $defaultPackageStaff;
    } elseif ($bookingType === 'costume') {
        $requiredStaff = 1;
    }

    $staffTotal = 0;
    $stmtTotal = $conn->prepare(
        "SELECT COUNT(*) as total FROM nhan_vien WHERE ID_CN = ? AND IS_DELETED = 0"
    );
    if (!$stmtTotal) {
        return ['allowed' => false, 'reason' => 'Lỗi kiểm tra nhân viên'];
    }
    $stmtTotal->bind_param('i', $branchId);
    $stmtTotal->execute();
    $stmtTotal->bind_result($staffTotal);
    $stmtTotal->fetch();
    $stmtTotal->close();

    // Đếm số nhân viên đang bận trong khoảng thời gian muốn đặt
    $staffBusy = 0;
    $startStr = $startTime->format('Y-m-d H:i:s');
    $endStr = $endTime->format('Y-m-d H:i:s');
    
    /**
     * QUERY NHÂN VIÊN BẬN:
     * - JOIN phan_cong_nhan_vien với nhan_vien
     * - Lọc theo chi nhánh và IS_DELETED = 0
     * - Tìm phân công có thời gian TRÙNG với slot muốn đặt
     * - COUNT(DISTINCT ID_TK) để tránh đếm trùng nếu 1 NV có nhiều phân công
     */
    $sqlBusy = "SELECT COUNT(DISTINCT pcnv.ID_TK) as busy 
                FROM phan_cong_nhan_vien pcnv
                JOIN nhan_vien nv ON nv.ID_TK = pcnv.ID_TK
                WHERE nv.ID_CN = ? 
                  AND nv.IS_DELETED = 0
                  AND NOT (pcnv.THOI_GIAN_KET_THUC <= ? OR pcnv.THOI_GIAN_BAT_DAU >= ?)"; // Điều kiện overlap
    
    $stmtBusy = $conn->prepare($sqlBusy);
    if (!$stmtBusy) {
        return ['allowed' => false, 'reason' => 'Lỗi kiểm tra nhân viên bận'];
    }
    $stmtBusy->bind_param('iss', $branchId, $startStr, $endStr);
    $stmtBusy->execute();
    $stmtBusy->bind_result($staffBusy);
    $stmtBusy->fetch();
    $stmtBusy->close();

    $staffAvailable = $staffTotal - $staffBusy;

    if ($staffAvailable < $requiredStaff) {
        return [
            'allowed' => false,
            'reason' => "Không đủ nhân viên rảnh. Cần $requiredStaff, còn lại $staffAvailable.",
            'staff_available' => $staffAvailable,
            'staff_required' => $requiredStaff
        ];
    }

    return [
        'allowed' => true,
        'reason' => 'Nhân viên rảnh đủ',
        'staff_available' => $staffAvailable,
        'staff_required' => $requiredStaff
    ];
}

/**
 * =============================================
 * LOGIC XỬ LÝ CHÍNH - MAIN AVAILABILITY CHECK
 * =============================================
 * 
 * WORKFLOW:
 * 1. Tính tổng thời lượng dịch vụ/gói (duration_min)
 * 2. Tính thời gian di chuyển (travel_buffer_min) nếu làm ngoài
 * 3. Tính thời gian kết thúc (end_time = start + duration + buffer)
 * 4. Kiểm tra end_time có vượt 21:00 (giờ đóng cửa) không
 * 5. Kiểm tra duration có vượt giới hạn ngày (MAX_DAILY_SERVICE_MIN) không
 * 6. Kiểm tra ràng buộc:
 *    - Nếu location_type='branch': Gọi checkBranchExclusivity()
 *    - Nếu location_type='external': Gọi checkExternalStaffAvailability()
 * 7. Trả về JSON response
 */
try {
    // BƯỚC 1: Tính tổng thời lượng
    $durationMin = calcDurationMin($conn, $bookingType, $serviceIds, $packageId);
    $maxDailyMinutes = (int)getenv('MAX_DAILY_SERVICE_MIN') ?: 600;
    
    // Sanity check: Cảnh báo nếu duration quá lớn (có thể là lỗi logic)
    if ($durationMin > 1000) {
        file_put_contents(__DIR__ . '/../../warning.log', 
            "[WARNING] Duration is very high: $durationMin minutes! bookingType=$bookingType, serviceIds=" . json_encode($serviceIds) . "\n",
            FILE_APPEND);
    }
    
    // [DEBUG LOG]
    error_log('[check_availability] DURATION CALCULATION: bookingType=' . $bookingType . 
              ', serviceIds=' . json_encode($serviceIds) . 
              ', packageId=' . $packageId . 
              ', calculatedDurationMin=' . $durationMin);
    
    // Tính travel buffer
    $travelBufferMin = 0;
    if ($locationType === 'external' && is_finite((float)$distanceKm)) {
        $travelBufferMin = calcTravelBufferMin($distanceKm);
    }
    
    // Tính end time
    $logFile = __DIR__ . '/../../check_availability.log';
    file_put_contents($logFile, "=== CALCULATING END TIME ===\n", FILE_APPEND);
    file_put_contents($logFile, "startTime: " . $startTime->format('Y-m-d H:i:s') . "\n", FILE_APPEND);
    file_put_contents($logFile, "durationMin: $durationMin (type: " . gettype($durationMin) . ")\n", FILE_APPEND);
    file_put_contents($logFile, "travelBufferMin: $travelBufferMin (type: " . gettype($travelBufferMin) . ")\n", FILE_APPEND);
    
    // BƯỚC 3: Tính thời gian kết thúc
    $endTime = (clone $startTime)->modify("+{$durationMin} minutes")->modify("+{$travelBufferMin} minutes");
    
    /**
     * BƯỚC 4: KIỂM TRA GIỜ ĐÓNG CỬA STUDIO (21:00)
     * 
     * Studio đóng cửa lúc 21:00, không thể đặt lịch kết thúc sau giờ này.
     * 
     * VÍ DỤ:
     * - Start: 20:00, Duration: 90 phút → End: 21:30 → KHÔNG HỢP LỆ
     * - Start: 19:00, Duration: 120 phút → End: 21:00 → HỢP LỆ (đúng giờ đóng cửa)
     * - Start: 19:30, Duration: 90 phút → End: 21:00 → HỢP LỆ
     */
    $endHour = (int)$endTime->format('H');
    $endMinute = (int)$endTime->format('i');
    $closingHour = 21;
    $closingMinute = 0;
    
    if ($endHour > $closingHour || ($endHour === $closingHour && $endMinute > $closingMinute)) {
        $closingResponse = [
            'available' => false,
            'reason' => "Thời gian kết thúc dự kiến (" . $endTime->format('H:i') . ") vượt quá giờ đóng cửa của studio (21:00). Vui lòng chọn khung giờ sớm hơn hoặc giảm số lượng dịch vụ.",
            'duration_min' => $durationMin,
            'travel_buffer_min' => $travelBufferMin,
            'end_time' => $endTime->format('Y-m-d H:i:s'),
            'closing_time_exceeded' => true,
            'studio_closing_time' => '21:00'
        ];
        error_log('[check_availability] End time exceeds closing time: ' . json_encode($closingResponse));
        http_response_code(200);
        echo json_encode($closingResponse);
        exit;
    }
    
    /**
     * BƯỚC 5: KIỂM TRA GIỚI HẠN THỜI LƯỢNG TRONG NGÀY
     * 
     * Một lịch hẹn không được vượt quá MAX_DAILY_SERVICE_MIN (mặc định: 600 phút = 10 giờ).
     * Mục đích: Tránh khách đặt quá nhiều dịch vụ cùng lúc, không thực tế.
     * 
     * ENV VARIABLE:
     * - MAX_DAILY_SERVICE_MIN: Giới hạn tối đa (600 phút)
     */
    if ($durationMin > $maxDailyMinutes) {
        $limitResponse = [
            'available' => false,
            'reason' => "Tổng thời lượng {$durationMin} phút vượt giới hạn {$maxDailyMinutes} phút trong một ngày. Vui lòng bỏ bớt dịch vụ.",
            'duration_min' => $durationMin,
            'travel_buffer_min' => $travelBufferMin,
            'end_time' => $endTime->format('Y-m-d H:i:s'),
            'limit_reason' => 'duration_exceeds_daily_limit',
            'max_duration_min' => $maxDailyMinutes
        ];
        error_log('[check_availability] Duration exceeded daily limit: ' . json_encode($limitResponse));
        http_response_code(200);
        echo json_encode($limitResponse);
        exit;
    }
    
    file_put_contents($logFile, "endTime: " . $endTime->format('Y-m-d H:i:s') . "\n", FILE_APPEND);
    file_put_contents($logFile, "=== END TIME CALCULATED ===\n\n", FILE_APPEND);
    
    /**
     * BƯỚC 6: KIỂM TRA RÀNG BUỘC THEO LOẠI ĐỊA ĐIỂM
     * 
     * 2 CHIẾN LƯỢC KHÁC NHAU:
     * 
     * A. LOCATION_TYPE = 'branch' (Làm tại chi nhánh):
     *    → Gọi checkBranchExclusivity()
     *    → Đảm bảo KHÔNG có lịch nào khác cùng thời gian tại chi nhánh
     *    → Chi nhánh chỉ phục vụ 1 lịch/slot (độc quyền toàn studio)
     * 
     * B. LOCATION_TYPE = 'external' (Làm ngoài chi nhánh):
     *    → Gọi checkExternalStaffAvailability()
     *    → Đảm bảo ĐỦ nhân viên rảnh để đi phục vụ
     *    → Chi nhánh có thể có NHIỀU lịch external cùng lúc (nếu đủ NV)
     */
    $response = [
        'available' => false,
        'reason' => 'Đã xảy ra lỗi',
        'duration_min' => $durationMin,
        'travel_buffer_min' => $travelBufferMin,
        'end_time' => $endTime->format('Y-m-d H:i:s')
    ];
    
    // BƯỚC 6A: Kiểm tra trang phục yêu cầu (nếu booking_type = 'package')
    if ($bookingType === 'package' && $packageId) {
        $costumeCheck = checkPackageCostumeRequirements($conn, $packageId, $branchId, $startTime, $endTime);
        
        if (!$costumeCheck['available']) {
            // Trang phục không khả dụng → trả về luôn
            $response['available'] = false;
            $response['reason'] = $costumeCheck['reason'];
            if (isset($costumeCheck['unavailable_costumes'])) {
                $response['unavailable_costumes'] = $costumeCheck['unavailable_costumes'];
            }
            if (isset($costumeCheck['costume_check_failed'])) {
                $response['costume_check_failed'] = true;
            }
            
            error_log('[check_availability] Costume check failed: ' . json_encode($costumeCheck));
            http_response_code(200);
            echo json_encode($response);
            exit;
        }
        
        error_log('[check_availability] Costume check passed: ' . $costumeCheck['reason']);
    }
    
    // BƯỚC 6B: Kiểm tra ràng buộc theo loại địa điểm (tiếp tục như cũ)
    if ($locationType === 'branch') {
        // STRATEGY A: Kiểm tra độc quyền chi nhánh
        $check = checkBranchExclusivity($conn, $branchId, $startTime, $endTime);
        $response['available'] = $check['allowed'];
        $response['reason'] = $check['reason'];
        if (isset($check['suggested_next_start'])) {
            $response['suggested_next_start'] = $check['suggested_next_start'];
        }
    } else {
        // STRATEGY B: Kiểm tra nhân viên rảnh
        $serviceCountForCheck = count($serviceIds) ?: 1;
        $check = checkExternalStaffAvailability($conn, $branchId, $startTime, $endTime, $serviceCountForCheck, $bookingType);
        $response['available'] = $check['allowed'];
        $response['reason'] = $check['reason'];
        if (isset($check['staff_available'])) {
            $response['staff_available'] = $check['staff_available'];
            $response['staff_required'] = $check['staff_required'];
        }
    }
    
    error_log('[check_availability] FINAL RESPONSE: ' . json_encode($response));
    error_log('[check_availability] FINAL RESPONSE end_time format: ' . $endTime->format('Y-m-d H:i:s'));
    error_log('[check_availability] FINAL RESPONSE end_time type check: ' . gettype($response['end_time']));
    
    /**
     * VALIDATE RESPONSE TRƯỚC KHI GỬI
     * Đảm bảo kiểu dữ liệu đúng để client xử lý
     */
    if (!is_string($response['end_time'])) {
        file_put_contents(__DIR__ . '/../../error.log', 
            "ERROR: end_time is not a string! Type: " . gettype($response['end_time']) . ", Value: " . var_export($response['end_time'], true) . "\n",
            FILE_APPEND);
    }
    
    if (!is_int($response['duration_min'])) {
        file_put_contents(__DIR__ . '/../../error.log', 
            "ERROR: duration_min is not an int! Type: " . gettype($response['duration_min']) . ", Value: " . var_export($response['duration_min'], true) . "\n",
            FILE_APPEND);
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("[check_availability] Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'available' => false,
        'reason' => 'Lỗi máy chủ: ' . $e->getMessage()
    ]);
}
