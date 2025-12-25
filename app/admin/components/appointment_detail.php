<?php
include '../../database/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';

// Set timezone for consistent datetime handling
date_default_timezone_set('Asia/Ho_Chi_Minh');

$errors = [];
$successMessage = '';
$cssPath = '../../assets/css/appointment-ui-enhancements.css';
$statusTransitions = [
    'Đang chờ' => ['Đang chờ', 'Đã xác nhận', 'Đã hủy'],
    // Không cho phép hủy nếu đã xác nhận: chỉ cho hoàn thành
    'Đã xác nhận' => ['Đã xác nhận', 'Đã hoàn thành'],
    'Đã hoàn thành' => ['Đã hoàn thành'],
    'Đã hủy' => ['Đã hủy'],
    'Không đến' => ['Không đến']
];
$statusOptions = ['Đang chờ', 'Đã xác nhận', 'Đã hoàn thành', 'Đã hủy', 'Không đến'];

// Audit logging helper function
function logAudit($appointmentId, $action, $oldValue, $newValue, $details = '')
{
    global $conn;
    $userId = $_SESSION['ID_TK'] ?? null;
    $userName = $_SESSION['HO_TEN'] ?? 'Unknown';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $query = "INSERT INTO nhat_ky_he_thong (entity_type, entity_id, user_id, user_name, action, old_value, new_value, details, ip_address, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        $entityType = 'appointment';
        mysqli_stmt_bind_param($stmt, 'sisssssss', $entityType, $appointmentId, $userId, $userName, $action, $oldValue, $newValue, $details, $ipAddress);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        error_log('Audit log prepare failed: ' . mysqli_error($conn));
    }
}


function tableExists(string $tableName): bool
{
    global $conn;

    $table = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");

    if (!$result) {
        error_log("SHOW TABLES failed for {$tableName}: ".mysqli_error($conn));
        return false;
    }

    $exists = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);

    return $exists;
}

function getNoShowStatus($idLichHen)
{
    global $conn;
    $query = "SELECT ID_LOG, KHACH_XUA_HIEN, LI_DO_KHONG_DEN, THOI_GIAN_KIEM_TRA_KHONG_DEN 
              FROM lich_hen_no_show 
              WHERE ID_LICHHEN = ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $idLichHen);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    if ($result) {
        mysqli_free_result($result);
    }
    return $data;
}

function recordNoShow($idLichHen, $khachXuaHien, $liDo = null)
{
    global $conn;
    $existingRecord = getNoShowStatus($idLichHen);
    
    if ($existingRecord) {
        $query = "UPDATE lich_hen_no_show 
                  SET KHACH_XUA_HIEN = ?, 
                      LI_DO_KHONG_DEN = ?,
                      THOI_GIAN_KIEM_TRA_KHONG_DEN = NOW()
                  WHERE ID_LICHHEN = ?";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            error_log("Update prepare failed: " . mysqli_error($conn));
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'isi', $khachXuaHien, $liDo, $idLichHen);
    } else {
        $query = "INSERT INTO lich_hen_no_show 
                  (ID_LICHHEN, KHACH_XUA_HIEN, LI_DO_KHONG_DEN, THOI_GIAN_KIEM_TRA_KHONG_DEN, CREATED_AT)
                  VALUES (?, ?, ?, NOW(), NOW())";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            error_log("Insert prepare failed: " . mysqli_error($conn));
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iss', $idLichHen, $khachXuaHien, $liDo);
    }
    
    $success = mysqli_stmt_execute($stmt);
    if (!$success) {
        error_log("Execute failed: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    
    // Update lich_hen status to "Không đến" if marking as no-show
    if ($success && $khachXuaHien) {
        $updateStatusQuery = "UPDATE lich_hen SET TRANGTHAI = 'Không đến' WHERE ID_LICHHEN = ?";
        $statusStmt = mysqli_prepare($conn, $updateStatusQuery);
        if ($statusStmt) {
            mysqli_stmt_bind_param($statusStmt, 'i', $idLichHen);
            mysqli_stmt_execute($statusStmt);
            mysqli_stmt_close($statusStmt);
        }
    }
    
    // Remove no-show status if unchecking
    if ($success && !$khachXuaHien && $existingRecord) {
        $deleteNoShowQuery = "DELETE FROM lich_hen_no_show WHERE ID_LICHHEN = ?";
        $deleteStmt = mysqli_prepare($conn, $deleteNoShowQuery);
        if ($deleteStmt) {
            mysqli_stmt_bind_param($deleteStmt, 'i', $idLichHen);
            mysqli_stmt_execute($deleteStmt);
            mysqli_stmt_close($deleteStmt);
        }
    }
    
    return $success;
}

function getAppointmentDetail($id)
{
    global $conn;
    
    // Try the main query with all joins first
    $sql = "SELECT lh.*, tk.HO_TEN, tk.EMAIL, tk.SDT, tk.DIA_CHI, tk.NGAY_SINH,
                   dv.TEN_DV,
                   cn.TEN_CN, cn.DIA_CHI_CN, cn.SDT_CN,
                   multi.SERVICE_LIST AS MULTI_SERVICE_LIST,
                   multi.SERVICE_COUNT AS MULTI_SERVICE_COUNT,
                   COALESCE(lh.KHACH_XAC_NHAN, 0) AS KHACH_XAC_NHAN,
                   lh.THOI_GIAN_KHACH_XAC_NHAN,
                   lh.EMAIL_XAC_NHAN_SENT
            FROM lich_hen lh
            JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
            JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
            LEFT JOIN chi_nhanh cn ON lh.ID_CHINHANH = cn.ID_CN
            LEFT JOIN (
                SELECT bi.ID_LICHHEN,
                       GROUP_CONCAT(dv2.TEN_DV ORDER BY bi.ID_ITEM SEPARATOR ', ') AS SERVICE_LIST,
                       COUNT(DISTINCT bi.REF_ID) AS SERVICE_COUNT
                FROM BOOKING_ITEM bi
                JOIN DICH_VU dv2 ON dv2.ID_DV = bi.REF_ID
                WHERE bi.ITEM_TYPE = 'service'
                GROUP BY bi.ID_LICHHEN
            ) multi ON multi.ID_LICHHEN = lh.ID_LICHHEN
            WHERE lh.ID_LICHHEN = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('Prepare statement failed in getAppointmentDetail (full query): ' . mysqli_error($conn));
        // Fall back to simpler query without BOOKING_ITEM
        error_log('Falling back to simpler query without multi-service join');
        return getAppointmentDetailSimple($id);
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('Execute failed in getAppointmentDetail (full query): ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        // Fall back to simpler query
        return getAppointmentDetailSimple($id);
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $data = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    if ($result) {
        mysqli_free_result($result);
    }
    return $data;
}

function getAppointmentDetailSimple($id)
{
    global $conn;
    // Fallback query without BOOKING_ITEM join
    $sql = "SELECT lh.*, tk.HO_TEN, tk.EMAIL, tk.SDT, tk.DIA_CHI, tk.NGAY_SINH,
                   dv.TEN_DV,
                   cn.TEN_CN, cn.DIA_CHI_CN, cn.SDT_CN,
                   NULL AS MULTI_SERVICE_LIST,
                   NULL AS MULTI_SERVICE_COUNT,
                   COALESCE(lh.KHACH_XAC_NHAN, 0) AS KHACH_XAC_NHAN,
                   lh.THOI_GIAN_KHACH_XAC_NHAN,
                   lh.EMAIL_XAC_NHAN_SENT
            FROM lich_hen lh
            JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
            JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
            LEFT JOIN chi_nhanh cn ON lh.ID_CHINHANH = cn.ID_CN
            WHERE lh.ID_LICHHEN = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('Prepare statement failed in getAppointmentDetailSimple: ' . mysqli_error($conn));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('Execute failed in getAppointmentDetailSimple: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return null;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $data = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    if ($result) {
        mysqli_free_result($result);
    }
    return $data;
}

function updateAppointment($data, $currentVersion = 1)
{
    global $conn;
    
    // Begin transaction for data consistency
    mysqli_begin_transaction($conn);
    
    try {
        // Update without version checking since VERSION column doesn't exist in lich_hen table
        // NOTE: Chỉ cập nhật 3 trường cơ bản. Các trường LOCATION_TYPE, TRAVEL_FEE, DISTANCE_KM, 
        // LOCATION_ADDRESS được giữ nguyên và chỉ được set lúc tạo lịch hẹn trong process_schedule.php
        $query = "UPDATE lich_hen 
                  SET THOI_GIAN_BAT_DAU = ?, 
                      DIA_CHI_HEN = ?, 
                      TRANGTHAI = ? 
                  WHERE ID_LICHHEN = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception('Prepare statement failed: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, 'sssi', $data['THOI_GIAN_BAT_DAU'], $data['DIA_CHI_HEN'], $data['TRANGTHAI'], $data['ID_LICHHEN']);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Execute failed: ' . mysqli_stmt_error($stmt));
        }
        
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        // Check if update was successful
        if ($affected === 0) {
            throw new Exception('Failed to update appointment. Please try again.');
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Log the update
        logAudit(
            $data['ID_LICHHEN'],
            'UPDATE',
            json_encode(['status' => $data['TRANGTHAI']]),
            json_encode(['time' => $data['THOI_GIAN_BAT_DAU'], 'address' => $data['DIA_CHI_HEN']]),
            'Updated appointment details'
        );
        
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log('Update appointment error: ' . $e->getMessage());
        throw $e;
    }
}

function deleteAppointment($id)
{
    global $conn;
    
    mysqli_begin_transaction($conn);
    
    try {
        $stmt = mysqli_prepare($conn, "DELETE FROM lich_hen WHERE ID_LICHHEN = ?");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, 'i', $id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Execute failed: ' . mysqli_stmt_error($stmt));
        }
        
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        mysqli_commit($conn);
        
        logAudit($id, 'DELETE', 'appointment', 'deleted', 'Appointment permanently deleted');
        
        return $affected > 0;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log('Delete appointment error: ' . $e->getMessage());
        return false;
    }
}

function getInvoicesByAppointment($id)
{
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT ID_HD, NGAY_GIO, TONG_TIEN, TRANGTHAI_THANHTOAN, PHUONGTHUC_THANHTOAN FROM hoa_don WHERE ID_LICHHEN = ? ORDER BY NGAY_GIO DESC");
    if (!$stmt) {
        error_log('Prepare statement failed in getInvoicesByAppointment: ' . mysqli_error($conn));
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('Execute failed in getInvoicesByAppointment: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return [];
    }
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    if ($result) {
        mysqli_free_result($result);
    }
    return $rows;
}

function calculateTotalPrice($idLichHen)
{
    global $conn;
    $tongTienDV = 0;
    $tongTienTB = 0;
    $travelFee  = 0;

    // Ưu tiên lấy tổng từ BOOKING_ITEM (đa dịch vụ)
    $stmtBI = mysqli_prepare($conn, "SELECT DON_GIA, SO_LUONG FROM BOOKING_ITEM WHERE ID_LICHHEN = ? AND ITEM_TYPE = 'service'");
    if ($stmtBI) {
        mysqli_stmt_bind_param($stmtBI, 'i', $idLichHen);
        if (mysqli_stmt_execute($stmtBI)) {
            $resBI = mysqli_stmt_get_result($stmtBI);
            while ($resBI && ($bi = mysqli_fetch_assoc($resBI))) {
                $donGia = isset($bi['DON_GIA']) ? (float)$bi['DON_GIA'] : 0;
                $soLuong = isset($bi['SO_LUONG']) ? (int)$bi['SO_LUONG'] : 1;
                $tongTienDV += $donGia * max($soLuong, 1);
            }
            if ($resBI) { mysqli_free_result($resBI); }
        }
        mysqli_stmt_close($stmtBI);
    }

    // Fallback: nếu không có dòng dịch vụ trong BOOKING_ITEM, dùng dịch vụ chính
    if ($tongTienDV <= 0) {
        $queryDV = "
            SELECT (
                SELECT dg.DON_GIA
                FROM don_gia_dich_vu dg
                WHERE dg.ID_DV = dv.ID_DV
                ORDER BY dg.NGAY_GIO DESC
                LIMIT 1
            ) AS DON_GIA
            FROM lich_hen lh
            JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
            WHERE lh.ID_LICHHEN = ?
        ";

        $stmtDV = mysqli_prepare($conn, $queryDV);
        if ($stmtDV) {
            mysqli_stmt_bind_param($stmtDV, 'i', $idLichHen);
            if (mysqli_stmt_execute($stmtDV)) {
                $resultDV = mysqli_stmt_get_result($stmtDV);
                if ($resultDV && ($rowDV = mysqli_fetch_assoc($resultDV))) {
                    $donGia = isset($rowDV['DON_GIA']) ? (float)$rowDV['DON_GIA'] : 0;
                    $tongTienDV = $donGia;
                }
                if ($resultDV) { mysqli_free_result($resultDV); }
            } else {
                error_log('Không thể thực thi truy vấn giá dịch vụ: '.mysqli_stmt_error($stmtDV));
            }
            mysqli_stmt_close($stmtDV);
        } else {
            error_log('Không thể chuẩn bị truy vấn giá dịch vụ: '.mysqli_error($conn));
        }
    }

    // KHÔNG TÍNH GIÁ THIẾT BỊ - Đã comment out
    /*
    $hasEquipmentTable = tableExists('lich_hen_thiet_bi');
    $hasEquipmentPrice = $hasEquipmentTable && tableExists('don_gia_trang_thiet_bi');

    if ($hasEquipmentPrice) {
        $queryTB = "
            SELECT dgtb.DON_GIA, COALESCE(lhtb.SO_LUONG, 1) AS SO_LUONG
            FROM lich_hen_thiet_bi lhtb
            JOIN trang_thiet_bi tb ON lhtb.ID_TB = tb.ID_TB
            JOIN (
                SELECT ID_TB, DON_GIA
                FROM don_gia_trang_thiet_bi dgtb1
                WHERE NGAY_GIO = (
                    SELECT MAX(NGAY_GIO)
                    FROM don_gia_trang_thiet_bi dgtb2
                    WHERE dgtb2.ID_TB = dgtb1.ID_TB
                )
            ) dgtb ON tb.ID_TB = dgtb.ID_TB
            WHERE lhtb.ID_LICHHEN = ?
        ";

        $stmtTB = mysqli_prepare($conn, $queryTB);
        if ($stmtTB) {
            mysqli_stmt_bind_param($stmtTB, 'i', $idLichHen);
            if (mysqli_stmt_execute($stmtTB)) {
                $resultTB = mysqli_stmt_get_result($stmtTB);
                if ($resultTB) {
                    while ($rowTB = mysqli_fetch_assoc($resultTB)) {
                        $soLuong = isset($rowTB['SO_LUONG']) ? (int)$rowTB['SO_LUONG'] : 1;
                        $donGiaTB = isset($rowTB['DON_GIA']) ? (float)$rowTB['DON_GIA'] : 0;
                        $tongTienTB += $donGiaTB * max($soLuong, 1);
                    }
                    mysqli_free_result($resultTB);
                }
            } else {
                error_log('Không thể thực thi truy vấn giá thiết bị: '.mysqli_stmt_error($stmtTB));
            }
            mysqli_stmt_close($stmtTB);
        } else {
            error_log('Không thể chuẩn bị truy vấn giá thiết bị: '.mysqli_error($conn));
        }
    }
    */

    // Cộng phụ phí di chuyển nếu có cột và dữ liệu
    $hasTravelFee = false;
    if ($rs = mysqli_query($conn, "SHOW COLUMNS FROM lich_hen LIKE 'TRAVEL_FEE'")) {
        $hasTravelFee = mysqli_num_rows($rs) > 0; mysqli_free_result($rs);
    }
    if ($hasTravelFee) {
        $stmtTF = mysqli_prepare($conn, "SELECT COALESCE(TRAVEL_FEE,0) FROM lich_hen WHERE ID_LICHHEN = ?");
        if ($stmtTF) {
            mysqli_stmt_bind_param($stmtTF, 'i', $idLichHen);
            if (mysqli_stmt_execute($stmtTF)) {
                mysqli_stmt_bind_result($stmtTF, $tf); if (mysqli_stmt_fetch($stmtTF)) { $travelFee = (float)$tf; }
            }
            mysqli_stmt_close($stmtTF);
        }
    }

    return $tongTienDV + $tongTienTB + $travelFee;
}

if (!isset($_GET['ID_LICHHEN'])) {
    echo "<div class='text-red-600 font-bold'>Không tìm thấy mã lịch hẹn.</div>";
    exit;
}

$id = (int)$_GET['ID_LICHHEN'];
$appointment = getAppointmentDetail($id);

if (!$appointment) {
    // Debug: try simpler query to check if appointment exists
    $debugStmt = mysqli_prepare($conn, "SELECT ID_LICHHEN, TRANGTHAI FROM lich_hen WHERE ID_LICHHEN = ?");
    if ($debugStmt) {
        mysqli_stmt_bind_param($debugStmt, 'i', $id);
        mysqli_stmt_execute($debugStmt);
        $debugResult = mysqli_stmt_get_result($debugStmt);
        $debugRow = $debugResult ? mysqli_fetch_assoc($debugResult) : null;
        mysqli_stmt_close($debugStmt);
        
        if (!$debugRow) {
            echo "<div class='text-red-600 font-bold'>Không tìm thấy lịch hẹn với ID: {$id}</div>";
        } else {
            echo "<div class='text-red-600 font-bold'>Lỗi truy vấn chi tiết lịch hẹn. ID tồn tại: {$id}<br>Chi tiết lỗi đã được ghi nhật ký.</div>";
        }
    } else {
        echo "<div class='text-red-600 font-bold'>Không tìm thấy thông tin lịch hẹn.</div>";
    }
    exit;
}

$invoices = getInvoicesByAppointment($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment'])) {
    $currentStatus = $appointment['TRANGTHAI'];
    $newStatus = $_POST['TRANGTHAI'] ?? $currentStatus;
    $thoiGianInput = trim($_POST['THOI_GIAN_BAT_DAU'] ?? '');
    $diaChiInput = trim($_POST['DIA_CHI_HEN'] ?? '');
    $cancellationReason = trim($_POST['cancellation_reason'] ?? '');
    
    // Input sanitization
    $diaChiInput = filter_var($diaChiInput, FILTER_SANITIZE_STRING);
    $cancellationReason = filter_var($cancellationReason, FILTER_SANITIZE_STRING);
    
    $thoiGianDate = $thoiGianInput !== '' ? DateTime::createFromFormat('Y-m-d\TH:i', $thoiGianInput) : null;
    $now = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    $allowedTargets = $statusTransitions[$currentStatus] ?? [$currentStatus];

    // Validation
    if (!in_array($newStatus, $statusOptions, true)) {
        $errors[] = 'Trạng thái được gửi không hợp lệ.';
    } elseif (!in_array($newStatus, $allowedTargets, true)) {
        $errors[] = "Không thể chuyển từ {$currentStatus} sang {$newStatus}.";
    }

    // Hard block: Không cho phép hủy lịch đã xác nhận
    if ($currentStatus === 'Đã xác nhận' && $newStatus === 'Đã hủy') {
        $errors[] = "Không cho phép hủy lịch đã xác nhận. Vui lòng chọn 'Đổi lịch', đánh dấu 'Không đến' hoặc xử lý theo chính sách trễ/no-show.";
    }

    // Hard block: Không cho phép xác nhận hoàn thành trước giờ bắt đầu
    $appointmentStartTs = strtotime($appointment['THOI_GIAN_BAT_DAU'] ?? '');
    if ($newStatus === 'Đã hoàn thành' && $appointmentStartTs && time() < $appointmentStartTs) {
        $errors[] = "Chưa đến giờ bắt đầu, không thể xác nhận 'Đã hoàn thành'.";
    }

    if (in_array($currentStatus, ['Đã hoàn thành', 'Đã hủy'], true) && $newStatus !== $currentStatus) {
        $errors[] = "Lịch hẹn đã {$currentStatus}, không thể chỉnh sửa thêm.";
    }

    // Check if appointment is overdue and not yet marked as no-show/completed/cancelled
    $appointmentTime = strtotime($appointment['THOI_GIAN_BAT_DAU']);
    $isAppointmentOverdue = $appointmentTime < time();
    $isOverdueNotMarked = $isAppointmentOverdue && !in_array($currentStatus, ['Không đến', 'Đã hoàn thành', 'Đã hủy']);

    // If overdue and trying to change time/address (not just status), show error
    if ($isOverdueNotMarked && ($thoiGianInput !== date('Y-m-d H:i', strtotime($appointment['THOI_GIAN_BAT_DAU'])) || $diaChiInput !== $appointment['DIA_CHI_HEN'])) {
        $errors[] = "Lịch hẹn đã quá hạn. Bạn không thể chỉnh sửa thời gian và địa chỉ, nhưng vẫn có thể đánh dấu trạng thái 'Không đến' hoặc 'Đã hoàn thành'.";
    }

    if ($thoiGianInput === '') {
        $errors[] = 'Thời gian bắt đầu là bắt buộc.';
    } elseif (!$thoiGianDate) {
        $errors[] = 'Định dạng thời gian bắt đầu không hợp lệ.';
    } elseif (in_array($currentStatus, ['Đang chờ', 'Đã xác nhận'], true) && $thoiGianDate < $now) {
        $errors[] = 'Vui lòng chọn thời gian bắt đầu lớn hơn thời điểm hiện tại.';
    } else {
        // Validate minimum time gap (at least 1 hour from now)
        $minTimeGap = clone $now;
        $minTimeGap->modify('+1 hour');
        if ($thoiGianDate < $minTimeGap && in_array($currentStatus, ['Đang chờ', 'Đã xác nhận'], true)) {
            $errors[] = 'Thời gian bắt đầu phải cách hiện tại ít nhất 1 giờ.';
        }
    }

    if ($diaChiInput === '') {
        $errors[] = 'Địa chỉ hẹn không được để trống.';
    }

    if (strlen($diaChiInput) > 500) {
        $errors[] = 'Địa chỉ hẹn không được vượt quá 500 ký tự.';
    }

    if (empty($errors)) {
        // If overdue, only update status, not time/address
        if ($isOverdueNotMarked) {
            $payload = [
                'ID_LICHHEN' => $id,
                'THOI_GIAN_BAT_DAU' => $appointment['THOI_GIAN_BAT_DAU'],
                'DIA_CHI_HEN' => $appointment['DIA_CHI_HEN'],
                'TRANGTHAI' => $newStatus
            ];
        } else {
            $payload = [
                'ID_LICHHEN' => $id,
                'THOI_GIAN_BAT_DAU' => $thoiGianDate ? $thoiGianDate->format('Y-m-d H:i:s') : $appointment['THOI_GIAN_BAT_DAU'],
                'DIA_CHI_HEN' => $diaChiInput,
                'TRANGTHAI' => $newStatus
            ];
        }

        try {
            if (updateAppointment($payload, 1)) {
            if ($currentStatus !== $newStatus && $newStatus === 'Đã xác nhận') {
                $checkStmt = mysqli_prepare($conn, "SELECT 1 FROM hoa_don WHERE ID_LICHHEN = ?");
                mysqli_stmt_bind_param($checkStmt, 'i', $id);
                mysqli_stmt_execute($checkStmt);
                mysqli_stmt_store_result($checkStmt);

                if (mysqli_stmt_num_rows($checkStmt) === 0) {
                    $tongTien = calculateTotalPrice($id);
                    $stmtHD = mysqli_prepare($conn, "INSERT INTO hoa_don (ID_LICHHEN, NGAY_GIO, TONG_TIEN, TRANGTHAI_THANHTOAN) VALUES (?, NOW(), ?, 'Chưa thanh toán')");
                    mysqli_stmt_bind_param($stmtHD, 'id', $id, $tongTien);
                    if (!mysqli_stmt_execute($stmtHD)) {
                        error_log('Tạo hóa đơn thất bại: ' . mysqli_stmt_error($stmtHD));
                    }
                    $newInvoiceId = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmtHD);

                    // Thêm dòng phụ phí di chuyển nếu tồn tại trong lich_hen
                    $hasTravelFee = false;
                    if ($rsTF = mysqli_query($conn, "SHOW COLUMNS FROM lich_hen LIKE 'TRAVEL_FEE'")) {
                        $hasTravelFee = mysqli_num_rows($rsTF) > 0; mysqli_free_result($rsTF);
                    }
                    if ($hasTravelFee && $newInvoiceId) {
                        $tfVal = 0;
                        $stmtTF = mysqli_prepare($conn, "SELECT COALESCE(TRAVEL_FEE,0) FROM lich_hen WHERE ID_LICHHEN = ?");
                        if ($stmtTF) {
                            mysqli_stmt_bind_param($stmtTF, 'i', $id);
                            if (mysqli_stmt_execute($stmtTF)) {
                                mysqli_stmt_bind_result($stmtTF, $tfVal);
                                mysqli_stmt_fetch($stmtTF);
                            }
                            mysqli_stmt_close($stmtTF);
                        }
                        if ((int)$tfVal > 0) {
                            $stmtCT = mysqli_prepare($conn, "INSERT INTO chi_tiet_hoa_don (ID_HD, LOAI, ID_THAM_CHIEU, TEN_MUC, DON_GIA) VALUES (?, 'travel_fee', 0, 'Phụ phí di chuyển', ?)");
                            if ($stmtCT) {
                                $tfInt = (int)$tfVal;
                                mysqli_stmt_bind_param($stmtCT, 'ii', $newInvoiceId, $tfInt);
                                if (!mysqli_stmt_execute($stmtCT)) {
                                    error_log('Chèn chi tiết phụ phí thất bại: ' . mysqli_stmt_error($stmtCT));
                                }
                                mysqli_stmt_close($stmtCT);
                            }
                        }
                    }
                }
                mysqli_stmt_close($checkStmt);

                $stmtInfo = mysqli_prepare($conn, "
                    SELECT tk.EMAIL, tk.HO_TEN, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, dv.TEN_DV, hd.TONG_TIEN
                    FROM lich_hen lh
                    JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
                    JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
                    JOIN hoa_don hd ON lh.ID_LICHHEN = hd.ID_LICHHEN
                    WHERE lh.ID_LICHHEN = ?
                ");
                mysqli_stmt_bind_param($stmtInfo, 'i', $id);
                mysqli_stmt_execute($stmtInfo);
                mysqli_stmt_bind_result($stmtInfo, $email, $hoTen, $thoiGian, $diaChi, $tenDV, $tongTien);
                mysqli_stmt_fetch($stmtInfo);
                mysqli_stmt_close($stmtInfo);

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'trongnghiann4911@gmail.com';
                    $mail->Password = 'boyw rfke ahjp trlx';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('trongnghiann4911@gmail.com', 'Stygian Blue Studio');
                    $mail->addAddress($email, $hoTen);
                    $mail->CharSet = 'UTF-8';
                    $mail->isHTML(true);
                    $mail->Subject = '✓ Lịch hẹn của bạn đã được xác nhận - Stygian Blue Studio';

                    $formattedTime = date('d/m/Y H:i', strtotime($thoiGian));
                    $formattedDate = date('d/m/Y', strtotime($thoiGian));
                    $formattedHour = date('H:i', strtotime($thoiGian));
                    
                    $mail->Body = "
<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='font-family: Segoe UI, Arial, sans-serif; color: #333; line-height: 1.6;'>
    <div style='max-width: 600px; margin: 0 auto; background: #ffffff;'>
        <!-- Header -->
        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;'>
            <h1 style='margin: 0; color: white; font-size: 28px; font-weight: 600;'>✓ Lịch hẹn đã xác nhận</h1>
            <p style='margin: 10px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;'>Stygian Blue Studio</p>
        </div>

        <!-- Main Content -->
        <div style='padding: 40px 30px; background: #f8f9fa;'>
            <p style='font-size: 16px; color: #333; margin: 0 0 20px 0;'>
                Xin chào <strong>$hoTen</strong>,
            </p>
            
            <p style='font-size: 14px; color: #666; margin: 0 0 25px 0;'>
                Lịch hẹn của bạn đã được <strong>xác nhận</strong> thành công. Chúng tôi rất vui được phục vụ bạn!
            </p>

            <!-- Appointment Details Box -->
            <div style='background: #ffffff; border: 2px solid #667eea; border-radius: 8px; padding: 25px; margin: 25px 0;'>
                <h2 style='color: #667eea; font-size: 18px; margin: 0 0 20px 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 15px;'>Chi tiết lịch hẹn</h2>
                
                <div style='margin: 15px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Dịch vụ</p>
                    <p style='margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #333;'>$tenDV</p>
                </div>

                <div style='margin: 20px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Ngày thực hiện</p>
                    <p style='margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #333;'>$formattedDate</p>
                </div>

                <div style='margin: 20px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Thời gian</p>
                    <p style='margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #333;'>$formattedHour</p>
                </div>

                <div style='margin: 20px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Địa điểm thực hiện</p>
                    <p style='margin: 5px 0 0 0; font-size: 14px; font-weight: 600; color: #333;'>$diaChi</p>
                </div>

                <div style='margin: 20px 0; padding-top: 20px; border-top: 1px solid #e0e0e0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Tổng tiền dịch vụ</p>
                    <p style='margin: 5px 0 0 0; font-size: 20px; font-weight: 700; color: #667eea;'>" . number_format($tongTien, 0, ',', '.') . " VND</p>
                </div>
            </div>

            <!-- Invoice Created Note -->
            <div style='background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 25px 0; border-radius: 4px;'>
                <p style='margin: 0; font-size: 13px; color: #155724;'>
                    <strong>Hóa đơn đã được tạo</strong> trong hệ thống của chúng tôi.
                </p>
            </div>

            <!-- What Happens Next -->
            <div style='background: #e7f3ff; border-left: 4px solid #0066cc; padding: 15px; margin: 25px 0; border-radius: 4px;'>
                <p style='margin: 0 0 10px 0; font-size: 13px; color: #004085; font-weight: 600;'>
                    Bước tiếp theo
                </p>
                <ul style='margin: 10px 0 0 0; padding-left: 20px; font-size: 12px; color: #004085;'>
                    <li style='margin: 5px 0;'>Chúng tôi sẽ chuẩn bị mọi thứ trước lịch hẹn của bạn</li>
                    <li style='margin: 5px 0;'>Bạn sẽ nhận được thông báo nhắc nhở 24 giờ trước</li>
                    <li style='margin: 5px 0;'>Vui lòng đến đúng giờ để tránh làm gián đoạn</li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div style='background: #f0f4ff; padding: 15px; margin: 25px 0; border-radius: 6px; border-left: 4px solid #667eea;'>
                <p style='margin: 0 0 10px 0; font-size: 13px; color: #333; font-weight: 600;'>Cần giúp đỡ?</p>
                <p style='margin: 5px 0; font-size: 12px; color: #666;'>Gọi chúng tôi: <strong>0123 456 789</strong></p>
                <p style='margin: 5px 0; font-size: 12px; color: #666;'>Email: <strong>support@stygianblue.vn</strong></p>
                <p style='margin: 5px 0; font-size: 12px; color: #666;'>Website: <strong>stygianblue.vn</strong></p>
            </div>
        </div>

        <!-- Footer -->
        <div style='background: #2c3e50; color: #ecf0f1; padding: 30px; text-align: center; border-top: 3px solid #667eea;'>
            <p style='margin: 0 0 10px 0; font-size: 14px; font-weight: 600;'>Stygian Blue Studio</p>
            <p style='margin: 0 0 15px 0; font-size: 12px; color: #bdc3c7;'>Chuyên cung cấp dịch vụ chụp ảnh và quần áo phục vụ sự kiện</p>
            
            <hr style='border: none; border-top: 1px solid #34495e; margin: 15px 0;'>
            
            <p style='margin: 0; font-size: 11px; color: #7f8c8d;'>
                © " . date('Y') . " Stygian Blue Studio. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
                    ";
                    $mail->send();
                } catch (Exception $e) {

                // Ghi doanh thu vào tai_chinh ở trạng thái chờ thanh toán (Option A)
                if (isset($newInvoiceId) && (int)$newInvoiceId > 0) {
                    $branchId = (int)($appointment['ID_CHINHANH'] ?? 0);
                    // Tránh ghi trùng nếu đã tồn tại
                    $revCheck = mysqli_prepare($conn, "SELECT 1 FROM tai_chinh WHERE ID_HD = ? AND LOAI_GIAO_DICH = 'doanh thu' LIMIT 1");
                    if ($revCheck) {
                        mysqli_stmt_bind_param($revCheck, 'i', $newInvoiceId);
                        mysqli_stmt_execute($revCheck);
                        mysqli_stmt_store_result($revCheck);
                        $exists = mysqli_stmt_num_rows($revCheck) > 0;
                        mysqli_stmt_close($revCheck);
                        if (!$exists) {
                            $insRev = mysqli_prepare($conn, "INSERT INTO tai_chinh (ID_HD, NGAY_GIAO_DICH, SO_TIEN, LOAI_GIAO_DICH, LOAI_CHI_TIET, ID_CN, TRANG_THAI) VALUES (?, NOW(), ?, 'doanh thu', 'Dịch vụ chụp ảnh', ?, 'chờ thanh toán')");
                            if ($insRev) {
                                mysqli_stmt_bind_param($insRev, 'idi', $newInvoiceId, $tongTien, $branchId);
                                if (!mysqli_stmt_execute($insRev)) {
                                    error_log('Ghi doanh thu (pending) thất bại: ' . mysqli_stmt_error($insRev));
                                }
                                mysqli_stmt_close($insRev);
                            }
                        }
                    }
                }
                    error_log("Gửi email xác nhận thất bại: {$mail->ErrorInfo}");
                }
            }

            // Gửi email hủy lịch
            if ($currentStatus !== $newStatus && $newStatus === 'Đã hủy') {
                try {
                    $notificationService = new \App\Services\AppointmentNotificationService($conn);
                    $notificationService->sendCancellationEmail($id, $cancellationReason);
                    
                    logAudit($id, 'CANCEL', $currentStatus, $newStatus, $cancellationReason);
                } catch (Exception $e) {
                    error_log("Gửi email hủy lịch thất bại: {$e->getMessage()}");
                }
            }

            // Gửi email thay đổi lịch (nếu thời gian hoặc địa chỉ thay đổi)
            if (($thoiGianInput !== '' && $appointment['THOI_GIAN_BAT_DAU'] !== ($thoiGianDate ? $thoiGianDate->format('Y-m-d H:i:s') : $appointment['THOI_GIAN_BAT_DAU']))
                || ($diaChiInput !== '' && $appointment['DIA_CHI_HEN'] !== $diaChiInput)) {
                try {
                    $notificationService = new \App\Services\AppointmentNotificationService($conn);
                    $changes = [];
                    if ($thoiGianInput !== '' && $appointment['THOI_GIAN_BAT_DAU'] !== ($thoiGianDate ? $thoiGianDate->format('Y-m-d H:i:s') : $appointment['THOI_GIAN_BAT_DAU'])) {
                        $changes['time'] = [
                            'old' => date('d/m/Y H:i', strtotime($appointment['THOI_GIAN_BAT_DAU'])),
                            'new' => $thoiGianDate ? $thoiGianDate->format('d/m/Y H:i') : date('d/m/Y H:i', strtotime($appointment['THOI_GIAN_BAT_DAU']))
                        ];
                    }
                    if ($diaChiInput !== '' && $appointment['DIA_CHI_HEN'] !== $diaChiInput) {
                        $changes['address'] = [
                            'old' => $appointment['DIA_CHI_HEN'] ?? 'Chưa cập nhật',
                            'new' => $diaChiInput
                        ];
                    }
                    if (!empty($changes)) {
                        $notificationService->sendRescheduleEmail($id, $changes);
                        
                        logAudit($id, 'RESCHEDULE', json_encode($changes['old'] ?? []), json_encode($changes['new'] ?? []), 'Appointment rescheduled');
                    }
                } catch (Exception $e) {
                    error_log("Gửi email thay đổi lịch thất bại: {$e->getMessage()}");
                }
            }

            $successMessage = 'Cập nhật lịch hẹn thành công.';
            $appointment = getAppointmentDetail($id);
            $invoices = getInvoicesByAppointment($id);
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_no_show'])) {
    $khachXuaHien = isset($_POST['khach_xua_hien']) ? 1 : 0;
    $liDo = trim($_POST['li_do_khong_den'] ?? '');
    
    if (recordNoShow($id, $khachXuaHien, $liDo ?: null)) {
        $successMessage = 'Ghi nhận trạng thái khách hàng thành công.';
    } else {
        $errors[] = 'Không thể ghi nhận trạng thái. Vui lòng thử lại.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_appointment'])) {
    if (deleteAppointment($id)) {
        echo "<script>alert('Xóa lịch hẹn thành công!'); window.location.href='admin_dashboard.php?page=appointments';</script>";
        exit;
    } else {
        echo "<script>alert('Lỗi: Không thể xóa lịch hẹn.');</script>";
    }
}
?>

<?php
$currentStatus = $appointment['TRANGTHAI'];
$statusBadgeClass = match ($currentStatus) {
    'Đang chờ' => 'bg-yellow-100 text-yellow-800',
    'Đã xác nhận' => 'bg-blue-100 text-blue-800',
    'Đã hoàn thành' => 'bg-green-100 text-green-800',
    'Đã hủy' => 'bg-red-100 text-red-800',
    'Không đến' => 'bg-orange-100 text-orange-800',
    default => 'bg-gray-100 text-gray-800',
};
$currentAllowed = $statusTransitions[$currentStatus] ?? [$currentStatus];

// Không hiển thị tùy chọn 'Đã hoàn thành' nếu chưa đến giờ bắt đầu
$appointmentTime = strtotime($appointment['THOI_GIAN_BAT_DAU']);
$nowTs = time();
if ($appointmentTime && $nowTs < $appointmentTime) {
    $filtered = [];
    foreach ($currentAllowed as $opt) {
        if ($opt === 'Đã hoàn thành') { continue; }
        $filtered[] = $opt;
    }
    $currentAllowed = $filtered;
}
?>

<div class="max-w-6xl mx-auto">
    <div class="bg-white p-8 rounded-lg shadow-lg border-t-4 border-t-indigo-600">
        <h2 class="text-4xl font-black text-gray-900 mb-2 text-center">CHI TIẾT LỊCH HẸN</h2>
        <p class="text-center text-gray-500 text-sm mb-6">Quản lý và cập nhật thông tin lịch hẹn của khách hàng</p>

        <?php if ($successMessage): ?>
            <div class="mb-6 border-l-4 border-l-green-600 border border-green-200 bg-green-50 text-green-800 px-4 py-4 rounded text-sm font-medium animate-fadeIn">
                <span class="inline-block font-bold text-green-700 mr-2 text-base">[THÀNH CÔNG]</span>
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="mb-6 border-l-4 border-l-red-600 border border-red-200 bg-red-50 text-red-800 px-4 py-4 rounded text-sm">
                <p class="font-bold text-red-700 mb-2 text-base">[LỖI]</p>
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="grid gap-6 lg:grid-cols-3">
            <?php 
            // Check if appointment is overdue (has passed)
            $appointmentTime = strtotime($appointment['THOI_GIAN_BAT_DAU']);
            $isOverdue = $appointmentTime < time();
            
            // Lock COMPLETELY if completed/cancelled
            // Lock PARTIALLY (time/address only) if overdue and not yet marked as no-show/completed/cancelled
            $isLockedCompletely = in_array($currentStatus, ['Đã hoàn thành', 'Đã hủy'], true);
            $isLockedPartially = $isOverdue && !in_array($currentStatus, ['Không đến', 'Đã hoàn thành', 'Đã hủy']);
            $isLocked = $isLockedCompletely;
            ?>
            <form method="POST" class="bg-white border-l-4 border-l-indigo-600 border border-gray-100 rounded-lg p-6 space-y-6 lg:col-span-2 shadow-sm <?= $isLocked ? 'opacity-70' : '' ?>">
                <input type="hidden" name="ID_LICHHEN" value="<?= $appointment['ID_LICHHEN'] ?>">

                <?php if ($isLocked): ?>
                    <div class="mb-4 border-l-4 border-l-yellow-600 border border-yellow-200 bg-yellow-50 text-yellow-800 px-4 py-3 rounded text-sm font-medium">
                        [CẢNH BÁO] Lịch hẹn đã <?= htmlspecialchars(strtolower($currentStatus)) ?>, không thể chỉnh sửa.
                    </div>
                <?php elseif ($isLockedPartially): ?>
                    <div class="mb-4 border-l-4 border-l-orange-600 border border-orange-200 bg-orange-50 text-orange-800 px-4 py-3 rounded text-sm font-medium">
                        [CẢNH BÁO] Lịch hẹn này đã quá hạn (<?= $appointment['THOI_GIAN_BAT_DAU'] ?>). 
                        Bạn không thể chỉnh sửa thời gian và địa chỉ, nhưng vẫn có thể đánh dấu trạng thái "Không đến" hoặc "Đã hoàn thành".
                    </div>
                <?php endif; ?>

                <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b-2 border-gray-200">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Mã lịch hẹn</p>
                        <p class="text-3xl font-black text-indigo-600 mt-1">#<?= $appointment['ID_LICHHEN'] ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Trạng thái hiện tại</p>
                        <span class="inline-block px-4 py-2 rounded-lg text-sm font-bold uppercase tracking-wide whitespace-nowrap <?= $statusBadgeClass ?>">
                            <?= htmlspecialchars($currentStatus) ?>
                        </span>
                    </div>
                </div>

                <!-- Trạng thái xác nhận khách hàng -->
                <?php 
                    $customerConfirmed = (int)($appointment['KHACH_XAC_NHAN'] ?? 0);
                    $confirmTime = $appointment['THOI_GIAN_KHACH_XAC_NHAN'];
                    $emailSent = (int)($appointment['EMAIL_XAC_NHAN_SENT'] ?? 0);
                ?>
                <?php if ($customerConfirmed): ?>
                    <div class="mt-6 p-4 rounded-lg border-l-4 border-l-green-600 border border-green-200 bg-green-50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <p class="text-sm font-bold text-green-800 uppercase tracking-wide">
                                    [ĐÃ XÁC NHẬN] Khách hàng xác nhận lịch hẹn
                                </p>
                                <?php if ($confirmTime): ?>
                                    <p class="text-xs text-green-700 mt-2">
                                        Thời gian xác nhận: <strong><?= date('d/m/Y H:i:s', strtotime($confirmTime)) ?></strong>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <span class="inline-flex items-center justify-center min-w-10 px-3 py-1 rounded-full text-sm font-bold ml-4 bg-green-200 text-green-800">
                                OK
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mt-6 p-4 rounded-lg border-l-4 border-l-blue-500 border border-blue-200 bg-blue-50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <p class="text-sm font-bold text-blue-800 uppercase tracking-wide">
                                    [XÁC NHẬN] Email <?= $emailSent ? 'đã gửi' : 'chưa gửi' ?>
                                </p>
                            </div>
                            <span class="inline-flex items-center justify-center min-w-10 px-3 py-1 rounded-full text-sm font-bold ml-4 bg-blue-200 text-blue-800">
                                CHỜ
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Ghi nhận khách không đến -->
                <?php 
                    $noShowRecord = getNoShowStatus($id);
                    $isNoShow = $noShowRecord && (int)($noShowRecord['KHACH_XUA_HIEN'] ?? 0);
                    $appointmentTime = new DateTime($appointment['THOI_GIAN_BAT_DAU']);
                    $appointmentTime->modify('+1 hour');
                    $now = new DateTime('now');
                    $canMarkNoShow = $now >= $appointmentTime;
                    $isAutoMarked = $noShowRecord && strpos($noShowRecord['LI_DO_KHONG_DEN'] ?? '', 'Tu dong') !== false;
                ?>
                <?php if ($canMarkNoShow): ?>
                    <?php if ($isNoShow): ?>
                        <div class="mt-6 p-4 rounded-lg border-l-4 border-l-red-600 border border-red-200 bg-red-50">
                    <?php else: ?>
                        <div class="mt-6 p-4 rounded-lg border-l-4 border-l-gray-400 border border-gray-200 bg-gray-50">
                    <?php endif; ?>
                        <?php if ($isAutoMarked): ?>
                            <div class="mb-4 p-3 bg-orange-100 border-l-4 border-l-orange-600 border border-orange-300 rounded text-orange-800 text-sm">
                                <strong>[THÔNG BÁO]</strong> Lịch hẹn này đã tự động được đánh dấu là không đến do quá hạn 1 giờ.
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="khach_xua_hien" value="1" <?= $isNoShow ? 'checked' : '' ?> class="w-4 h-4 text-red-600 rounded focus:ring-red-500">
                                    <span class="text-sm font-semibold <?= $isNoShow ? 'text-red-800' : 'text-gray-700' ?>">
                                        Khách hàng không đến
                                    </span>
                                </label>
                            </div>

                            <?php if ($isNoShow || isset($_POST['khach_xua_hien'])): ?>
                                <div>
                                    <label for="li_do" class="block text-sm font-medium text-gray-700 mb-1">Lý do</label>
                                    <textarea name="li_do_khong_den" id="li_do" rows="2" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                        placeholder="Nhập lý do khách không đến (tùy chọn)"><?= htmlspecialchars($noShowRecord['LI_DO_KHONG_DEN'] ?? '') ?></textarea>
                                </div>
                            <?php endif; ?>

                            <button type="submit" name="record_no_show" 
                                class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition font-semibold text-sm">
                                Lưu trạng thái
                            </button>

                            <?php if ($noShowRecord && $noShowRecord['THOI_GIAN_KIEM_TRA_KHONG_DEN']): ?>
                                <p class="text-xs text-gray-600 mt-2">
                                    Cập nhật lần cuối: <?= date('d/m/Y H:i:s', strtotime($noShowRecord['THOI_GIAN_KIEM_TRA_KHONG_DEN'])) ?>
                                </p>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="mt-6 p-4 rounded-lg border-l-4 border-l-yellow-600 border border-yellow-200 bg-yellow-50">
                        <p class="text-sm text-yellow-800">
                            <strong>[THÔNG TIN]</strong> Thời gian chốt nhận: <strong><?= $appointmentTime->format('d/m/Y H:i:s') ?></strong>
                            <br><small class="text-yellow-700 block mt-1">(1 giờ sau thời gian bắt đầu lịch hẹn)</small>
                        </p>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dịch vụ</label>
                    <?php
                        $multiListRaw = $appointment['MULTI_SERVICE_LIST'] ?? '';
                        $multiCount = (int)($appointment['MULTI_SERVICE_COUNT'] ?? 0);
                        $displayList = $multiListRaw ?: ($appointment['TEN_DV'] ?? '');
                        $serviceTokens = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', (string)$displayList))));
                        $hasMultiServices = $multiCount > 1 && count($serviceTokens) > 1;
                    ?>
                    <?php if ($hasMultiServices): ?>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($serviceTokens as $idx => $svcName): ?>
                                <?php $badgeClass = $idx === 0
                                    ? 'border-indigo-200 bg-indigo-50 text-indigo-700'
                                    : 'border-slate-200 bg-slate-50 text-slate-700'; ?>
                                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= $badgeClass ?>">
                                    <?= htmlspecialchars($svcName) ?>
                                    <?php if ($idx === 0): ?>
                                        <span class="ml-1 text-[10px] font-normal uppercase tracking-wide text-indigo-500">Chính</span>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Đây là toàn bộ các dịch vụ khách hàng đã chọn cho lịch hẹn này.</p>
                    <?php else: ?>
                        <input type="text" value="<?= htmlspecialchars($serviceTokens[0] ?? '—') ?>"
                            class="bg-white border border-gray-200 rounded-lg w-full px-4 py-2 shadow-sm" readonly>
                    <?php endif; ?>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Chi nhánh thực hiện</label>
                        <input type="text" value="<?= htmlspecialchars($appointment['TEN_CN'] ?? 'Đang cập nhật') ?>"
                            class="bg-white border border-gray-200 rounded-lg w-full px-4 py-2 shadow-sm" readonly>
                        <?php if (!empty($appointment['DIA_CHI_CN'])): ?>
                            <p class="text-xs text-gray-500 mt-1">Địa chỉ: <?= htmlspecialchars($appointment['DIA_CHI_CN']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại chi nhánh</label>
                        <input type="text" value="<?= htmlspecialchars($appointment['SDT_CN'] ?? 'Chưa cập nhật') ?>"
                            class="bg-white border border-gray-200 rounded-lg w-full px-4 py-2 shadow-sm" readonly>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Thời gian bắt đầu *</label>
                    <input type="datetime-local" name="THOI_GIAN_BAT_DAU"
                        value="<?= date('Y-m-d\TH:i', strtotime($appointment['THOI_GIAN_BAT_DAU'])) ?>"
                        class="border border-gray-300 rounded-lg w-full px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
                        <?= ($isLocked || $isLockedPartially) ? 'disabled' : '' ?> required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Địa chỉ hẹn *</label>
                    <textarea name="DIA_CHI_HEN" rows="3"
                        class="border border-gray-300 rounded-lg w-full px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
                        <?= ($isLocked || $isLockedPartially) ? 'disabled' : '' ?> required><?= htmlspecialchars($appointment['DIA_CHI_HEN']) ?></textarea>
                </div>

                <!-- Thông tin phụ phí di chuyển -->
                <?php 
                    $locationType = $appointment['LOCATION_TYPE'] ?? null;
                    $travelFee = isset($appointment['TRAVEL_FEE']) ? (float)$appointment['TRAVEL_FEE'] : 0;
                    $distanceKm = isset($appointment['DISTANCE_KM']) ? (float)$appointment['DISTANCE_KM'] : 0;
                    $locationAddr = $appointment['LOCATION_ADDRESS'] ?? null;
                ?>
                <?php if ($locationType): ?>
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="text-sm font-bold text-gray-800 mb-3 uppercase tracking-wide">Thông tin địa điểm & phí di chuyển</h4>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Loại địa điểm</p>
                                <?php if ($locationType === 'branch'): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                        📍 Tại chi nhánh
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800">
                                        🚗 Địa điểm ngoài
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($locationType === 'external' && $distanceKm > 0): ?>
                                <div>
                                    <p class="text-xs text-gray-600 mb-1">Khoảng cách</p>
                                    <p class="text-sm font-bold text-gray-900"><?= number_format($distanceKm, 2) ?> km</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($locationAddr): ?>
                            <div class="mt-3 pt-3 border-t border-blue-200">
                                <p class="text-xs text-gray-600 mb-1">Địa chỉ cụ thể</p>
                                <p class="text-sm text-gray-800"><?= htmlspecialchars($locationAddr) ?></p>
                            </div>
                        <?php endif; ?>
                        <div class="mt-3 pt-3 border-t border-blue-200">
                            <p class="text-xs text-gray-600 mb-1">Phụ phí di chuyển</p>
                            <p class="text-lg font-bold <?= $travelFee > 0 ? 'text-indigo-600' : 'text-gray-500' ?>">
                                <?= $travelFee > 0 ? number_format($travelFee, 0, ',', '.') . ' VND' : 'Không có phí' ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái (chỉ tiến về phía trước)</label>
                    <div class="flex flex-wrap gap-3">
                        <?php foreach ($statusOptions as $option):
                            $isActive = $option === $currentStatus;
                            $isAllowed = in_array($option, $currentAllowed, true);
                            $baseClass = 'px-4 py-2 rounded-lg border text-sm font-semibold';
                            $stateClass = $isActive
                                ? 'bg-indigo-600 text-white border-indigo-600'
                                : ($isAllowed && !$isLocked ? 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400'
                                    : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed opacity-60');
                        ?>
                            <label class="inline-flex items-center gap-2 <?= $baseClass ?> <?= $stateClass ?>">
                                <input type="radio" name="TRANGTHAI" value="<?= htmlspecialchars($option) ?>"
                                    class="sr-only"
                                    <?= $isActive ? 'checked' : '' ?>
                                    <?= (!$isAllowed || $isLocked) && !$isActive ? 'disabled' : '' ?>>
                                <span><?= htmlspecialchars($option) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Đang chờ → Đã xác nhận → Đã hoàn thành. Có thể hủy khi chưa hoàn tất.</p>
                </div>

                <!-- Lý do hủy lịch -->
                <?php 
                    $showCancellationReason = in_array('Đã hủy', array_values($statusTransitions[$currentStatus] ?? [])) && $currentStatus !== 'Đã hủy';
                ?>
                <?php if ($showCancellationReason): ?>
                    <div class="mt-4">
                        <label for="cancellation_reason" class="block text-sm font-medium text-gray-700 mb-2">
                            Lý do hủy lịch (sẽ gửi email cho khách)
                        </label>
                        <textarea name="cancellation_reason" id="cancellation_reason" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                            placeholder="Nhập lý do hủy lịch hẹn (tùy chọn)..."></textarea>
                        <p class="text-xs text-gray-500 mt-1">Email thông báo hủy sẽ được gửi đến khách hàng kèm theo lý do này.</p>
                    </div>
                <?php endif; ?>

                <div class="flex flex-wrap items-center gap-4 pt-2">
                    <button type="submit" name="update_appointment"
                        <?= $isLocked ? 'disabled' : '' ?>
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-semibold shadow transition <?= $isLocked ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        Lưu thay đổi
                    </button>

                    <button type="submit" name="delete_appointment"
                        onclick="return confirm('Bạn có chắc muốn xóa lịch hẹn này?');"
                        <?= $isLocked ? 'disabled' : '' ?>
                        class="bg-red-500 hover:bg-red-600 text-white px-5 py-2 rounded-lg font-semibold shadow transition <?= $isLocked ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        Xóa lịch hẹn
                    </button>

                    <?php
                    $quayLaiURL = "#";
                    if (isset($_SESSION['ID_QUYEN'])) {
                        if ($_SESSION['ID_QUYEN'] == 1) {
                            // Admin
                            $quayLaiURL = "http://localhost:8080/StygianBlue/app/admin/admin_dashboard.php?page=appointments";
                        } elseif ($_SESSION['ID_QUYEN'] == 2) {
                            // Staff/Employee
                            $staffType = $_SESSION['STAFF_TYPE'] ?? '';
                            if ($staffType === 'quan_ly') {
                                // Manager
                                $quayLaiURL = "http://localhost:8080/StygianBlue/app/admin/manager_dashboard.php?page=appointments";
                            } else {
                                // Other staff
                                $quayLaiURL = "http://localhost:8080/StygianBlue/app/admin/staff_dashboard.php?page=staff";
                            }
                        }
                    }
                    ?>
                    <a href="<?= $quayLaiURL ?>"
                        class="ml-auto bg-gray-200 hover:bg-gray-300 text-gray-900 px-5 py-2 rounded-lg font-semibold shadow transition">
                        Quay lại danh sách
                    </a>
                </div>
            </form>

            <div class="space-y-6">
                <div class="bg-white border-l-4 border-l-indigo-600 border border-gray-100 rounded-lg p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-gray-900 mb-5 uppercase tracking-wide">Thông tin người đặt</h3>
                    <dl class="space-y-4 text-sm">
                        <div class="grid grid-cols-1 gap-2 pb-3 border-b border-gray-200">
                            <dt class="text-xs font-semibold text-gray-500 uppercase">Họ tên</dt>
                            <dd class="font-medium text-gray-900 text-base"><?= htmlspecialchars($appointment['HO_TEN']) ?></dd>
                        </div>
                        <div class="grid grid-cols-1 gap-2 pb-3 border-b border-gray-200">
                            <dt class="text-xs font-semibold text-gray-500 uppercase">Email</dt>
                            <dd class="font-medium text-gray-900 break-all"><?= htmlspecialchars($appointment['EMAIL'] ?? 'Chưa cập nhật') ?></dd>
                        </div>
                        <div class="grid grid-cols-1 gap-2 pb-3 border-b border-gray-200">
                            <dt class="text-xs font-semibold text-gray-500 uppercase">Số điện thoại</dt>
                            <dd class="font-medium text-gray-900"><?= htmlspecialchars($appointment['SDT'] ?? 'Chưa cập nhật') ?></dd>
                        </div>
                        <div class="grid grid-cols-1 gap-2 pb-3 border-b border-gray-200">
                            <dt class="text-xs font-semibold text-gray-500 uppercase">Ngày sinh</dt>
                            <dd class="font-medium text-gray-900">
                                <?= isset($appointment['NGAY_SINH']) && $appointment['NGAY_SINH'] ? date('d/m/Y', strtotime($appointment['NGAY_SINH'])) : 'Chưa cập nhật' ?>
                            </dd>
                        </div>
                        <div class="grid grid-cols-1 gap-2">
                            <dt class="text-xs font-semibold text-gray-500 uppercase">Địa chỉ</dt>
                            <dd class="font-medium text-gray-900 leading-relaxed"><?= htmlspecialchars($appointment['DIA_CHI'] ?? 'Chưa cập nhật') ?></dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white border-l-4 border-l-green-600 border border-gray-100 rounded-lg p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-gray-900 mb-5 uppercase tracking-wide">Hóa đơn liên quan</h3>
                    <?php if (empty($invoices)): ?>
                        <div class="text-center py-8">
                            <p class="text-sm text-gray-500">Chưa có hóa đơn nào cho lịch hẹn này</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-left">
                                <thead>
                                    <tr class="bg-gray-100 text-gray-700 uppercase text-xs font-bold border-b-2 border-gray-200">
                                        <th class="py-3 px-3">Mã HĐ</th>
                                        <th class="py-3 px-3">Ngày tạo</th>
                                        <th class="py-3 px-3 text-right">Tổng tiền</th>
                                        <th class="py-3 px-3">Thanh toán</th>
                                        <th class="py-3 px-3">Phương thức</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $invoiceIndex = 0; foreach ($invoices as $invoice): $invoiceIndex++; ?>
                                        <tr class="<?= $invoiceIndex % 2 === 0 ? 'bg-gray-50' : 'bg-white' ?> border-b border-gray-200 hover:bg-blue-50 transition">
                                            <td class="py-3 px-3 font-bold text-indigo-600">#<?= $invoice['ID_HD'] ?></td>
                                            <td class="py-3 px-3 text-gray-700 text-xs">
                                                <?= $invoice['NGAY_GIO'] ? date('d/m/Y H:i', strtotime($invoice['NGAY_GIO'])) : '-' ?>
                                            </td>
                                            <td class="py-3 px-3 text-right font-bold text-gray-900 text-base">
                                                <?= number_format((float)$invoice['TONG_TIEN'], 0, ',', '.') ?> VND
                                            </td>
                                            <td class="py-3 px-3">
                                                <span class="inline-flex px-2 py-1 rounded text-xs font-semibold <?= strpos($invoice['TRANGTHAI_THANHTOAN'], 'Đã') !== false ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                                    <?= htmlspecialchars($invoice['TRANGTHAI_THANHTOAN']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-3 text-sm text-gray-700"><?= htmlspecialchars($invoice['PHUONGTHUC_THANHTOAN']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Loading Spinner Animation */
    .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        margin-right: 8px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        vertical-align: middle;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .btn-loading {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-loading .loading-spinner {
        margin-right: 0;
    }

    /* Loading overlay */
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9998;
        justify-content: center;
        align-items: center;
    }

    .loading-overlay.active {
        display: flex;
    }

    .loading-modal {
        background: white;
        border-radius: 12px;
        padding: 40px;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        z-index: 9999;
    }

    .loading-modal .spinner {
        width: 50px;
        height: 50px;
        margin: 0 auto 20px;
        border: 4px solid #e5e7eb;
        border-top-color: #4f46e5;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    .loading-modal p {
        color: #6b7280;
        font-size: 16px;
        font-weight: 500;
    }
</style>

<script>
    // Add loading animation to appointment update form
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form[method="POST"]');
        if (!form) return;

        const updateBtn = form.querySelector('button[name="update_appointment"]');
        const recordNoShowBtn = form.querySelector('button[name="record_no_show"]');
        const deleteBtn = form.querySelector('button[name="delete_appointment"]');

        function showLoadingOverlay() {
            let overlay = document.getElementById('loadingOverlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'loadingOverlay';
                overlay.className = 'loading-overlay';
                overlay.innerHTML = `
                    <div class="loading-modal">
                        <div class="spinner"></div>
                        <p>Đang xử lý...</p>
                    </div>
                `;
                document.body.appendChild(overlay);
            }
            overlay.classList.add('active');
        }

        function hideLoadingOverlay() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.remove('active');
        }

        // Handle update appointment button
        if (updateBtn) {
            updateBtn.addEventListener('click', function(e) {
                if (!form.checkValidity()) return;
                showLoadingOverlay();
            });
        }

        // Handle record no-show button
        if (recordNoShowBtn) {
            recordNoShowBtn.addEventListener('click', function(e) {
                showLoadingOverlay();
            });
        }

        // Handle form submission
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                hideLoadingOverlay();
                return;
            }
            showLoadingOverlay();
        });

        // Hide loading on page load (form was submitted successfully)
        window.addEventListener('load', hideLoadingOverlay);
    });
</script>