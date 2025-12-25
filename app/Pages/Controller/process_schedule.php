<?php
session_start();
include '../../../database/config.php';
require_once '../../../vendor/autoload.php';

use App\Services\AppointmentNotificationService;

$cliTestMode = defined('CLI_TEST_MODE') && constant('CLI_TEST_MODE') === true;
$__processScheduleCliResult = null;

/**
 * process_schedule.php (bản ổn định theo môi trường hiện tại)
 *
 * - Giữ nguyên kiểu insert vào lich_hen / lich_hen_thiet_bi giống file cũ (để chắc chắn chạy)
 * - Bổ sung validate thời gian (không quá khứ, không quá 6 tháng)
* - Bổ sung transaction cho tính toàn vẹn
 * - Bổ sung rollback + error_log thay vì die()
 * - Bổ sung SO_LUONG = 1 khi lưu thiết bị (ổn hơn cho tương lai)
 * - Đồng bộ khách hàng từ tai_khoan -> khach_hang (giữ nguyên logic bạn đang dùng)
 *
 * Lưu ý:
 *  - Bạn có thể bật kiểm tra đăng nhập ở dưới (khối CHECK LOGIN).
 *  - Bạn có thể thêm CSRF khi form sẵn sàng.
 */


// =====================================================
// 0. (TÙY CHỌN) CHECK LOGIN
// =====================================================
// if (!isset($_SESSION['ID_TK'])) {
//     $_SESSION['message'] = "Vui lòng đăng nhập.";
//     $_SESSION['message_type'] = "error";
//     header("Location: ../../Pages/Login/login.php");
//     exit();
// }


// =====================================================
// 1. LẤY INPUT TỪ FORM
// =====================================================
$branchId      = $_POST['branch_id']    ?? null;
$ngayHen       = $_POST['ngayHen']      ?? null; // yyyy-mm-dd
$gioHen        = $_POST['gioHen']       ?? null; // HH:mm hoặc HH:mm:ss
$address       = $_POST['address']      ?? '';
$locationType  = $_POST['location_type'] ?? 'branch';
$extLatRaw     = $_POST['ext_lat']       ?? '';
$extLngRaw     = $_POST['ext_lng']       ?? '';
$serviceId     = $_POST['service_id']   ?? null;
$packageId     = $_POST['package_id']   ?? null;
$packageIdsRaw = isset($_POST['package_ids']) && is_array($_POST['package_ids']) ? $_POST['package_ids'] : [];
$serviceIdsRaw = isset($_POST['service_ids']) && is_array($_POST['service_ids']) ? $_POST['service_ids'] : [];
$bookingType   = $_POST['booking_type'] ?? 'service';
$userId        = $_SESSION['ID_TK']     ?? null;
$selectedDevices = isset($_POST['thiet_bi_id']) ? $_POST['thiet_bi_id'] : [];
$selectedCostumesRaw = isset($_POST['costume_ids']) && is_array($_POST['costume_ids']) ? $_POST['costume_ids'] : [];

// Debug multi-service
error_log("[process_schedule] Received service_ids: " . json_encode($serviceIdsRaw));
error_log("[process_schedule] Received service_id: " . ($serviceId ?? 'NULL'));

$selectedCostumes = [];
foreach ($selectedCostumesRaw as $cid) {
    if (ctype_digit((string)$cid)) {
        $selectedCostumes[] = (int)$cid;
    }
}
$selectedCostumes = array_values(array_unique($selectedCostumes));

if (!$userId && isset($_SESSION['user']['ID_TK'])) {
    $userId = $_SESSION['user']['ID_TK'];
}

$bookingType = in_array($bookingType, ['package', 'costume'], true) ? $bookingType : 'service';
if ($bookingType === 'costume') {
    $serviceId = null;
    $packageId = null;
}
$locationType = $locationType === 'external' ? 'external' : 'branch';
$branchId    = $branchId !== null ? (int)$branchId : null;
$serviceId   = ctype_digit((string)$serviceId) ? (int)$serviceId : null;
$packageId   = ctype_digit((string)$packageId) ? (int)$packageId : null;
$extLat      = is_numeric($extLatRaw) ? (float)$extLatRaw : null;
$extLng      = is_numeric($extLngRaw) ? (float)$extLngRaw : null;

// Danh sách dịch vụ bổ sung (nếu có)
$serviceIds = [];
foreach ($serviceIdsRaw as $sid) {
    if (ctype_digit((string)$sid)) {
        $serviceIds[] = (int)$sid;
    }
}
$serviceIds = array_values(array_unique($serviceIds));
error_log("[process_schedule] Parsed serviceIds: " . json_encode($serviceIds));
error_log("[process_schedule] serviceId after parse: " . ($serviceId ?? 'NULL'));

$packageIds = [];
foreach ($packageIdsRaw as $pid) {
    if (ctype_digit((string)$pid)) {
        $packageIds[] = (int)$pid;
    }
}
$packageIds = array_values(array_unique($packageIds));
if (empty($packageIds) && $packageId) {
    $packageIds = [$packageId];
}
error_log("[process_schedule] Parsed packageIds: " . json_encode($packageIds));
if (!$packageId && !empty($packageIds)) {
    $packageId = $packageIds[0];
    error_log("[process_schedule] Set packageId to first from array: " . $packageId);
}

if ($bookingType === 'service' && !$serviceId && !empty($serviceIds)) {
    $serviceId = $serviceIds[0];
    error_log("[process_schedule] Set serviceId to first from array: " . $serviceId);
}

// Chuẩn hoá giờ HH:mm -> HH:mm:00
if ($gioHen && preg_match('/^\d{2}:\d{2}$/', $gioHen)) {
    $gioHen .= ':00';
}

$startTime  = $ngayHen && $gioHen ? ($ngayHen . ' ' . $gioHen) : null;


// =====================================================
// 2. VALIDATE INPUT CƠ BẢN
// =====================================================
$errors = [];

if (!$userId)     $errors[] = "Không xác định được tài khoản người dùng.";
if (!$branchId)   $errors[] = "Thiếu chi nhánh.";
if (!$startTime)  $errors[] = "Thiếu thời gian hẹn.";
// Nếu chọn địa điểm ngoài studio thì yêu cầu toạ độ
if ($locationType === 'external') {
    if (!is_finite((float)$extLat) || !is_finite((float)$extLng)) {
        $errors[] = "Vui lòng chọn vị trí hợp lệ trên bản đồ.";
    }
}
if ($bookingType === 'service' && !$serviceId) {
    $errors[] = "Thiếu dịch vụ.";
}
if ($bookingType === 'package' && empty($packageIds)) {
    $errors[] = "Thiếu gói dịch vụ.";
}
if ($bookingType === 'costume' && empty($selectedCostumes)) {
    $errors[] = "Chọn ít nhất một trang phục.";
}

$currentDateTime  = new DateTime('now');
$selectedDateTime = $startTime
    ? DateTime::createFromFormat('Y-m-d H:i:s', $startTime)
    : null;

// fallback nếu giờ gửi kiểu HH:mm mà createFromFormat('H:i:s') fail
if (!$selectedDateTime && $startTime) {
    $selectedDateTime = DateTime::createFromFormat('Y-m-d H:i', $startTime);
}

// Nếu vẫn fail parse
if (!$selectedDateTime) {
    $errors[] = "Thời gian không hợp lệ.";
} else {
    // Không cho đặt quá khứ
    if ($selectedDateTime < $currentDateTime) {
        $errors[] = "Thời gian không được ở quá khứ. Vui lòng chọn thời gian hợp lệ.";
    }

    // Không cho đặt quá xa (> 180 ngày)
    $limitFuture = (clone $currentDateTime)->modify('+180 days');
    if ($selectedDateTime > $limitFuture) {
        $errors[] = "Bạn đang đặt quá xa. Vui lòng chọn trong 6 tháng tới.";
    }
}

// Nếu có lỗi -> quay về trang trước
if (!empty($errors)) {
    $_SESSION['message'] = implode("<br>", $errors);
    $_SESSION['message_type'] = "error";
    if ($cliTestMode) {
        $__processScheduleCliResult = [
            'status' => 'error',
            'messages' => $errors,
        ];
        return $__processScheduleCliResult;
    }
    header("Location: ../Views/lienhe.php");
    exit();
}


if ($bookingType === 'package' && $packageId) {
    $stmtPackage = $conn->prepare(
        "SELECT ID_DV FROM goi_dich_vu_chi_tiet WHERE ID_GOI = ? ORDER BY COALESCE(THU_TU,1), ID_DV LIMIT 1"
    );
    if ($stmtPackage === false) {
        $_SESSION['message'] = "Không xác định được dịch vụ đại diện cho gói đã chọn.";
        $_SESSION['message_type'] = "error";
        if ($cliTestMode) {
            $__processScheduleCliResult = ['status' => 'error', 'messages' => ["Không xác định được dịch vụ đại diện cho gói đã chọn."]];
            return $__processScheduleCliResult;
        }
        header("Location: ../Views/lienhe.php");
        exit();
    }
    $stmtPackage->bind_param('i', $packageId);
    $stmtPackage->execute();
    $stmtPackage->bind_result($serviceFromPackage);
    if ($stmtPackage->fetch()) {
        $serviceId = (int)$serviceFromPackage;
    }
    $stmtPackage->close();

    if (!$serviceId) {
        $_SESSION['message'] = "Gói dịch vụ chưa được cấu hình chi tiết. Vui lòng chọn gói khác.";
        $_SESSION['message_type'] = "error";
        if ($cliTestMode) {
            $__processScheduleCliResult = ['status' => 'error', 'messages' => ["Gói dịch vụ chưa được cấu hình chi tiết. Vui lòng chọn gói khác."]];
            return $__processScheduleCliResult;
        }
        header("Location: ../Views/lienhe.php");
        exit();
    }
}


// =====================================================
// 3. TRANSACTION: tạo lịch hẹn 
// =====================================================
$conn->begin_transaction();

try {

    // Tính phụ phí di chuyển (nếu có)
    $travelFee = 0;
    $distanceKm = null;
    if ($locationType === 'external' && is_finite((float)$extLat) && is_finite((float)$extLng)) {
        // Kiểm tra cột LAT/LNG của chi nhánh có tồn tại không
        $hasBranchCoords = false;
        if ($rs = $conn->query("SHOW COLUMNS FROM CHI_NHANH LIKE 'LATITUDE'")) {
            $hasBranchCoords = $rs->num_rows > 0; $rs->free_result();
        }
        if ($hasBranchCoords) {
            $stmtB = $conn->prepare('SELECT LATITUDE, LONGITUDE FROM CHI_NHANH WHERE ID_CN = ? LIMIT 1');
            if ($stmtB) {
                $stmtB->bind_param('i', $branchId);
                if ($stmtB->execute()) {
                    $stmtB->bind_result($bLat, $bLng);
                    if ($stmtB->fetch() && $bLat !== null && $bLng !== null) {
                        $distanceKm = haversineKm((float)$bLat, (float)$bLng, (float)$extLat, (float)$extLng);
                        $travelFee  = calcTravelFee($distanceKm);
                    }
                }
                $stmtB->close();
            }
        }
    }

    // 3.1 Thêm lịch hẹn
    // [NEW] Tính thời lượng, end time, và kiểm tra ràng buộc
    $durationMin = calcDurationMin($conn, $bookingType, !empty($serviceIds) ? $serviceIds : $serviceId, !empty($packageIds) ? $packageIds : $packageId);
    $travelBufferMin = 0;
    if ($locationType === 'external' && is_finite((float)$distanceKm)) {
        $travelBufferMin = calcTravelBufferMin($distanceKm);
    }
    $endTime = (clone $selectedDateTime)->modify("+{$durationMin} minutes")->modify("+{$travelBufferMin} minutes");
    
    // [NEW] Check if end time exceeds studio closing time (21:00)
    $endHour = (int)$endTime->format('H');
    $endMinute = (int)$endTime->format('i');
    $closingHour = 21;
    $closingMinute = 0;
    
    if ($endHour > $closingHour || ($endHour === $closingHour && $endMinute > $closingMinute)) {
        throw new Exception("Thời gian kết thúc dự kiến (" . $endTime->format('H:i') . ") vượt quá giờ đóng cửa của studio (21:00). Vui lòng chọn khung giờ sớm hơn hoặc giảm số lượng dịch vụ.");
    }
    
    // [NEW] Áp dụng kiểm tra ràng buộc
    if ($locationType === 'branch') {
        // Lấy tên chi nhánh để ghi vào địa chỉ hẹn
        $stmtBranchName = $conn->prepare('SELECT TEN_CN FROM CHI_NHANH WHERE ID_CN = ? LIMIT 1');
        if ($stmtBranchName) {
            $stmtBranchName->bind_param('i', $branchId);
            if ($stmtBranchName->execute()) {
                $stmtBranchName->bind_result($branchName);
                if ($stmtBranchName->fetch() && $branchName) {
                    $address = $branchName;
                } else {
                    $address = "Chi nhánh " . $branchId;
                }
            }
            $stmtBranchName->close();
        }
        
        // Kiểm tra độc quyền tại chi nhánh
        $checkBranch = checkBranchExclusivity($conn, $branchId, $selectedDateTime, $endTime);
        if (!$checkBranch['allowed']) {
            throw new Exception($checkBranch['reason']);
        }
        error_log("[process_schedule] Branch exclusivity check passed");
    } else {
        // Kiểm tra nhân viên rảnh cho lịch ngoài
        $serviceCountForStaff = 1; // mặc định, có thể tính lại từ service_ids
        if (!empty($serviceIds)) {
            $serviceCountForStaff = count($serviceIds);
        }
        $checkStaff = checkExternalStaffAvailability($conn, $branchId, $selectedDateTime, $endTime, $serviceCountForStaff, $bookingType);
        if (!$checkStaff['allowed']) {
            throw new Exception($checkStaff['reason']);
        }
        error_log("[process_schedule] External staff check passed: available={$checkStaff['staff_available']}, required={$checkStaff['staff_required']}");
    }
    
    // Kiểm tra cột mở rộng địa điểm hẹn trong lich_hen
    $hasLocationCols = false;
    if ($rs2 = $conn->query("SHOW COLUMNS FROM LICH_HEN LIKE 'LOCATION_TYPE'")) {
        $hasLocationCols = $rs2->num_rows > 0; $rs2->free_result();
    }

    // [NEW] Kiểm tra cột duration/endtime
    $hasDurationCols = false;
    if ($rs3 = $conn->query("SHOW COLUMNS FROM LICH_HEN LIKE 'DURATION_MIN'")) {
        $hasDurationCols = $rs3->num_rows > 0; $rs3->free_result();
    }

    if ($hasLocationCols) {
        if ($bookingType === 'package') {
            $sqlInsertLich = "
                INSERT INTO lich_hen
                    (ID_TK, THOI_GIAN_BAT_DAU, DIA_CHI_HEN, ID_DV, ID_GOI, TRANGTHAI, ID_CHINHANH,
                     LOCATION_TYPE, LOCATION_ADDRESS, LOCATION_LAT, LOCATION_LNG, DISTANCE_KM, TRAVEL_FEE" . ($hasDurationCols ? ", DURATION_MIN, THOI_GIAN_KET_THUC" : "") . ")
                VALUES (?, ?, ?, ?, ?, 'Đang chờ', ?, ?, ?, ?, ?, ?, ?" . ($hasDurationCols ? ", ?, ?" : "") . ")
            ";
            $stmt = $conn->prepare($sqlInsertLich);
        } elseif ($bookingType === 'costume') {
            $sqlInsertLich = "
                INSERT INTO lich_hen
                    (ID_TK, THOI_GIAN_BAT_DAU, DIA_CHI_HEN, ID_DV, ID_GOI, TRANGTHAI, ID_CHINHANH,
                     LOCATION_TYPE, LOCATION_ADDRESS, LOCATION_LAT, LOCATION_LNG, DISTANCE_KM, TRAVEL_FEE" . ($hasDurationCols ? ", DURATION_MIN, THOI_GIAN_KET_THUC" : "") . ")
                VALUES (?, ?, ?, NULL, NULL, 'Đang chờ', ?, ?, ?, ?, ?, ?, ?" . ($hasDurationCols ? ", ?, ?" : "") . ")
            ";
            $stmt = $conn->prepare($sqlInsertLich);
        } else {
            $sqlInsertLich = "
                INSERT INTO lich_hen
                    (ID_TK, THOI_GIAN_BAT_DAU, DIA_CHI_HEN, ID_DV, ID_GOI, TRANGTHAI, ID_CHINHANH,
                     LOCATION_TYPE, LOCATION_ADDRESS, LOCATION_LAT, LOCATION_LNG, DISTANCE_KM, TRAVEL_FEE" . ($hasDurationCols ? ", DURATION_MIN, THOI_GIAN_KET_THUC" : "") . ")
                VALUES (?, ?, ?, ?, NULL, 'Đang chờ', ?, ?, ?, ?, ?, ?, ?" . ($hasDurationCols ? ", ?, ?" : "") . ")
            ";
            $stmt = $conn->prepare($sqlInsertLich);
        }
    } else {
        if ($bookingType === 'package') {
            $sqlInsertLich = "
                INSERT INTO lich_hen
                    (ID_TK, THOI_GIAN_BAT_DAU, DIA_CHI_HEN, ID_DV, ID_GOI, TRANGTHAI, ID_CHINHANH)
                VALUES (?, ?, ?, ?, ?, 'Đang chờ', ?)
            ";
            $stmt = $conn->prepare($sqlInsertLich);
        } elseif ($bookingType === 'costume') {
            $sqlInsertLich = "
                INSERT INTO lich_hen
                    (ID_TK, THOI_GIAN_BAT_DAU, DIA_CHI_HEN, ID_DV, ID_GOI, TRANGTHAI, ID_CHINHANH)
                VALUES (?, ?, ?, NULL, NULL, 'Đang chờ', ?)
            ";
            $stmt = $conn->prepare($sqlInsertLich);
        } else {
            $sqlInsertLich = "
                INSERT INTO lich_hen
                    (ID_TK, THOI_GIAN_BAT_DAU, DIA_CHI_HEN, ID_DV, ID_GOI, TRANGTHAI, ID_CHINHANH)
                VALUES (?, ?, ?, ?, NULL, 'Đang chờ', ?)
            ";
            $stmt = $conn->prepare($sqlInsertLich);
        }
    }
    if ($stmt === false) {
        throw new Exception("Lỗi chuẩn bị truy vấn lịch hẹn: " . $conn->error);
    }

    // Ép thời gian về định dạng MySQL chuẩn Y-m-d H:i:s
    $startTimeMySQL = $selectedDateTime->format('Y-m-d H:i:s');
    $endTimeMySQL = $endTime->format('Y-m-d H:i:s');

    if ($hasLocationCols) {
        // Chuẩn hoá giá trị lưu
        $locAddr = $locationType === 'external' ? ($address ?? '') : ("Chi nhánh " . (string)$branchId);
        $locLat  = $locationType === 'external' && is_finite((float)$extLat) ? (float)$extLat : null;
        $locLng  = $locationType === 'external' && is_finite((float)$extLng) ? (float)$extLng : null;
        $distVal = $distanceKm !== null ? (float)$distanceKm : null;
        $feeVal  = (int)$travelFee;

        if ($bookingType === 'package') {
            if ($hasDurationCols) {
                $stmt->bind_param(
                    "sssiiissdddis",
                    $userId, $startTimeMySQL, $address, $serviceId, $packageId, $branchId,
                    $locationType, $locAddr, $locLat, $locLng, $distVal, $feeVal, $durationMin, $endTimeMySQL
                );
            } else {
                $stmt->bind_param(
                    "sssiiissdddi",
                    $userId, $startTimeMySQL, $address, $serviceId, $packageId, $branchId,
                    $locationType, $locAddr, $locLat, $locLng, $distVal, $feeVal
                );
            }
        } elseif ($bookingType === 'costume') {
            if ($hasDurationCols) {
                $stmt->bind_param(
                    "sssissddis",
                    $userId, $startTimeMySQL, $address, $branchId,
                    $locationType, $locAddr, $locLat, $locLng, $distVal, $feeVal, $durationMin, $endTimeMySQL
                );
            } else {
                $stmt->bind_param(
                    "sssissdddi",
                    $userId, $startTimeMySQL, $address, $branchId,
                    $locationType, $locAddr, $locLat, $locLng, $distVal, $feeVal
                );
            }
        } else {
            if ($hasDurationCols) {
                $stmt->bind_param(
                    "sssiissdddiis",
                    $userId, $startTimeMySQL, $address, $serviceId, $branchId,
                    $locationType, $locAddr, $locLat, $locLng, $distVal, $feeVal, $durationMin, $endTimeMySQL
                );
            } else {
                $stmt->bind_param(
                    "sssiissdddi",
                    $userId, $startTimeMySQL, $address, $serviceId, $branchId,
                    $locationType, $locAddr, $locLat, $locLng, $distVal, $feeVal
                );
            }
        }
    } else {
        if ($bookingType === 'package') {
            $stmt->bind_param("sssiii", $userId, $startTimeMySQL, $address, $serviceId, $packageId, $branchId);
        } elseif ($bookingType === 'costume') {
            $stmt->bind_param("sssi", $userId, $startTimeMySQL, $address, $branchId);
        } else {
            $stmt->bind_param("sssii", $userId, $startTimeMySQL, $address, $serviceId, $branchId);
        }
    }

    if (!$stmt->execute()) {
        throw new Exception("Lỗi khi đặt lịch: " . $stmt->error);
    }

    $lichHenId = $stmt->insert_id;
    $stmt->close();

    // 3.2 Ghi thiết bị kèm theo nếu có chọn
    // (nâng cấp: thêm SO_LUONG = 1 để future-proof)
    if (!empty($selectedDevices)) {
        $sqlDevice = "
            INSERT INTO lich_hen_thiet_bi (ID_LICHHEN, ID_TB, SO_LUONG)
            VALUES (?, ?, 1)
        ";
        $stmtDevice = $conn->prepare($sqlDevice);
        if ($stmtDevice === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn thiết bị: " . $conn->error);
        }

        foreach ($selectedDevices as $deviceIdRaw) {
            // chặn input không phải số
            if (!ctype_digit((string)$deviceIdRaw)) continue;
            $deviceId = (int)$deviceIdRaw;

            $stmtDevice->bind_param("ii", $lichHenId, $deviceId);
            if (!$stmtDevice->execute()) {
                throw new Exception("Lỗi ghi thiết bị: " . $stmtDevice->error);
            }
        }
        $stmtDevice->close();
    }


    // 3.3 Đồng bộ khách hàng từ bảng tài khoản -> khach_hang
    // (giữ nguyên y chang logic của bạn để tương thích DB hiện tại)
    $sqlInsertCustomer = "
        INSERT INTO khach_hang (ID_TK, ID_QUYEN, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT, MAT_KHAU)
        SELECT ID_TK, ID_QUYEN, HO_TEN, NGAY_SINH, DIA_CHI, EMAIL, SDT, MAT_KHAU 
        FROM tai_khoan 
        WHERE ID_TK = ?
        ON DUPLICATE KEY UPDATE
            ID_QUYEN = VALUES(ID_QUYEN),
            HO_TEN   = VALUES(HO_TEN),
            NGAY_SINH= VALUES(NGAY_SINH),
            DIA_CHI  = VALUES(DIA_CHI),
            EMAIL    = VALUES(EMAIL),
            SDT      = VALUES(SDT),
            MAT_KHAU = VALUES(MAT_KHAU)
    ";
    $stmtInsertCustomer = $conn->prepare($sqlInsertCustomer);
    if ($stmtInsertCustomer === false) {
        throw new Exception("Lỗi chuẩn bị truy vấn KH: " . $conn->error);
    }

    $stmtInsertCustomer->bind_param("s", $userId);
    if (!$stmtInsertCustomer->execute()) {
        throw new Exception("Lỗi ghi khách hàng: " . $stmtInsertCustomer->error);
    }
    $stmtInsertCustomer->close();


    // 3.4 Commit nếu tất cả ok
    // 3.3.1 Ghi line items khi tính năng trang phục bật
    $enablePackageCostume = getenv('ENABLE_PACKAGE_COSTUME');
    $enablePackageCostume = $enablePackageCostume === false ? '1' : $enablePackageCostume;

    if ($enablePackageCostume !== '0') {
        $tblCheck = $conn->query("SHOW TABLES LIKE 'BOOKING_ITEM'");
        $hasBookingItem = $tblCheck && $tblCheck->num_rows > 0;
        if ($tblCheck) { $tblCheck->close(); }

        if ($hasBookingItem && $bookingType === 'package' && !empty($packageIds)) {
            $effectivePackageIds = !empty($packageIds) ? $packageIds : ($packageId ? [$packageId] : []);

            foreach ($effectivePackageIds as $pkgId) {
                $pkgPrice = 0; $basePrice = 0; $promoPrice = null;
                $stmtPkg = $conn->prepare("SELECT vt.TONG_GIA_GOI AS base_price, vp.GIA_KM AS promo_price
                                              FROM v_goi_dich_vu_tong_tien vt
                                              LEFT JOIN v_goi_dich_vu_gia_khuyen_mai vp ON vt.ID_GOI = vp.ID_GOI
                                              WHERE vt.ID_GOI = ? LIMIT 1");
                if ($stmtPkg) {
                    $stmtPkg->bind_param('i', $pkgId);
                    $stmtPkg->execute();
                    $resPkg = $stmtPkg->get_result();
                    $rowPkg = $resPkg ? $resPkg->fetch_assoc() : null;
                    if ($rowPkg) {
                        $basePrice = isset($rowPkg['base_price']) ? (int)$rowPkg['base_price'] : 0;
                        $promoPrice = isset($rowPkg['promo_price']) ? (int)$rowPkg['promo_price'] : null;
                        $pkgPrice = $promoPrice ?? $basePrice;
                    }
                    $stmtPkg->close();
                }

                $stmtItem = $conn->prepare("INSERT INTO BOOKING_ITEM (ID_LICHHEN, ITEM_TYPE, REF_ID, DON_GIA, SO_LUONG, DISCOUNT_PERCENT, NOTE) VALUES (?, 'package', ?, ?, 1, 0, 'Gói dịch vụ')");
                if ($stmtItem) {
                    $stmtItem->bind_param('iii', $lichHenId, $pkgId, $pkgPrice);
                    $stmtItem->execute();
                    $stmtItem->close();
                }
            }

            // Trang phục: gắn theo gói chính (packageId đã set từ phần đầu)
            $primaryPkgId = $packageId ?: $effectivePackageIds[0];
            $costumeMap = [];
            $stmtC = $conn->prepare("SELECT tp.ID_TRANG_PHUC, tp.TEN, tp.GIA_THUE, gtp.DISCOUNT_PERCENT, gtp.BAT_BUOC
                                       FROM GOI_TRANG_PHUC gtp
                                       JOIN TRANG_PHUC tp ON tp.ID_TRANG_PHUC = gtp.ID_TRANG_PHUC
                                       WHERE gtp.ID_GOI = ?");
            if ($stmtC) {
                $stmtC->bind_param('i', $primaryPkgId);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                while ($r = $resC->fetch_assoc()) {
                    $costumeMap[(int)$r['ID_TRANG_PHUC']] = $r;
                }
                $stmtC->close();
            }

            $selectedCostumesFiltered = [];
            foreach ($selectedCostumes as $cid) {
                if (isset($costumeMap[$cid]) && (int)$costumeMap[$cid]['BAT_BUOC'] === 0) {
                    $selectedCostumesFiltered[] = $cid;
                }
            }

            foreach ($costumeMap as $cid => $info) {
                $isMandatory = (int)$info['BAT_BUOC'] === 1;
                if (!$isMandatory) continue;
                $stmtItem = $conn->prepare("INSERT INTO BOOKING_ITEM (ID_LICHHEN, ITEM_TYPE, REF_ID, DON_GIA, SO_LUONG, DISCOUNT_PERCENT, NOTE) VALUES (?, 'costume', ?, 0, 1, 100, 'Trang phục bao gồm')");
                if ($stmtItem) {
                    $stmtItem->bind_param('ii', $lichHenId, $cid);
                    $stmtItem->execute();
                    $stmtItem->close();
                }
            }

            foreach ($selectedCostumesFiltered as $cid) {
                $info = $costumeMap[$cid];
                $base = (int)$info['GIA_THUE'];
                $discountPercent = (int)$info['DISCOUNT_PERCENT'];
                $finalPrice = $base;
                if ($discountPercent > 0) {
                    $finalPrice = (int)round($base * (1 - $discountPercent / 100));
                }
                $stmtItem = $conn->prepare("INSERT INTO BOOKING_ITEM (ID_LICHHEN, ITEM_TYPE, REF_ID, DON_GIA, SO_LUONG, DISCOUNT_PERCENT, NOTE) VALUES (?, 'costume', ?, ?, 1, ?, 'Trang phục tuỳ chọn')");
                if ($stmtItem) {
                    $stmtItem->bind_param('iiii', $lichHenId, $cid, $finalPrice, $discountPercent);
                    $stmtItem->execute();
                    $stmtItem->close();
                }
            }
        } elseif ($hasBookingItem && $bookingType === 'costume' && !empty($selectedCostumes)) {
            $placeholders = implode(',', array_fill(0, count($selectedCostumes), '?'));
            $types = str_repeat('i', count($selectedCostumes));
            $sqlCostumes = "SELECT ID_TRANG_PHUC, TEN, GIA_THUE FROM TRANG_PHUC WHERE ID_TRANG_PHUC IN ($placeholders)";
            $stmtCostume = $conn->prepare($sqlCostumes);
            $costumeRows = [];
            if ($stmtCostume) {
                $stmtCostume->bind_param($types, ...$selectedCostumes);
                $stmtCostume->execute();
                $res = $stmtCostume->get_result();
                $costumeRows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                $stmtCostume->close();
            }

            foreach ($costumeRows as $row) {
                $cid = (int)$row['ID_TRANG_PHUC'];
                $price = (int)$row['GIA_THUE'];
                $stmtItem = $conn->prepare("INSERT INTO BOOKING_ITEM (ID_LICHHEN, ITEM_TYPE, REF_ID, DON_GIA, SO_LUONG, DISCOUNT_PERCENT, NOTE) VALUES (?, 'costume', ?, ?, 1, 0, 'Thuê trang phục lẻ')");
                if ($stmtItem) {
                    $stmtItem->bind_param('iii', $lichHenId, $cid, $price);
                    $stmtItem->execute();
                    $stmtItem->close();
                }
            }
        } elseif ($hasBookingItem && $bookingType === 'service' && !empty($serviceIds)) {
            error_log("[process_schedule] Inserting BOOKING_ITEM for service with serviceIds: " . json_encode($serviceIds));
            $uniqueServiceIds = $serviceIds;
            if ($serviceId && !in_array($serviceId, $uniqueServiceIds, true)) {
                array_unshift($uniqueServiceIds, $serviceId);
            }
            error_log("[process_schedule] uniqueServiceIds after dedup: " . json_encode($uniqueServiceIds));
            
            $priceStmt = $conn->prepare("SELECT DON_GIA FROM DON_GIA_DICH_VU WHERE ID_DV = ? ORDER BY NGAY_GIO DESC LIMIT 1");
            foreach ($uniqueServiceIds as $idx => $sid) {
                $price = 0;
                if ($priceStmt) {
                    $priceStmt->bind_param('i', $sid);
                    if ($priceStmt->execute()) {
                        $resP = $priceStmt->get_result();
                        $rowP = $resP ? $resP->fetch_assoc() : null;
                        $price = $rowP && isset($rowP['DON_GIA']) ? (int)$rowP['DON_GIA'] : 0;
                    }
                }
                $note = ($sid === $serviceId && $idx === 0) ? 'Dịch vụ chính' : 'Dịch vụ bổ sung';
                error_log("[process_schedule] Inserting BOOKING_ITEM: ID_LICHHEN=$lichHenId, ID_DV=$sid, DON_GIA=$price, NOTE=$note");
                
                $stmtItem = $conn->prepare("INSERT INTO BOOKING_ITEM (ID_LICHHEN, ITEM_TYPE, REF_ID, DON_GIA, SO_LUONG, DISCOUNT_PERCENT, NOTE) VALUES (?, 'service', ?, ?, 1, 0, ?)");
                if ($stmtItem) {
                    $stmtItem->bind_param('iiis', $lichHenId, $sid, $price, $note);
                    $stmtItem->execute();
                    $stmtItem->close();
                }
            }
            if ($priceStmt) { $priceStmt->close(); }
        }
    }

    $conn->commit();

    // Send confirmation email AFTER committing and after BOOKING_ITEM is created
    try {
        $notificationService = new AppointmentNotificationService($conn);
        $emailResult = $notificationService->sendConfirmationEmail($lichHenId);
        if (!$emailResult) {
            error_log("Gửi email xác nhận thất bại (kết quả false)");
        }
    } catch (Exception $e) {
        error_log("Lỗi gửi email xác nhận: " . $e->getMessage());
    }

    $_SESSION['message'] = "Đặt lịch hẹn thành công!";
    $_SESSION['message_type'] = "success";

    if ($cliTestMode) {
        $__processScheduleCliResult = [
            'status' => 'success',
            'booking_id' => $lichHenId,
            'travel_fee' => $travelFee,
            'distance_km' => $distanceKm,
        ];
        return $__processScheduleCliResult;
    }

    // Điều hướng đến trang xem lịch hẹn (giống file cũ của bạn)
    header("Location: ../../Pages/Views/xemLichhen.php");
    exit();

} catch (Exception $e) {
    // Có lỗi -> rollback để không tạo lịch dở dang
    $conn->rollback();

    // Ghi log nội bộ cho dev
    error_log("[process_schedule] transaction failed: " . $e->getMessage());

    // Thông báo người dùng
    $_SESSION['message'] = "Đã xảy ra lỗi khi đặt lịch. Vui lòng thử lại.";
    $_SESSION['message_type'] = "error";

    if ($cliTestMode) {
        $__processScheduleCliResult = [
            'status' => 'error',
            'message' => $e->getMessage(),
        ];
        return $__processScheduleCliResult;
    }

    // bạn có thể đổi trang này sang form đặt lịch của bạn
    header("Location: ../lienhe.php");
    exit();
}

// =====================================================
// HELPERS & SCHEDULING LOGIC
// =====================================================

function haversineKm($lat1, $lng1, $lat2, $lng2)
{
    $R = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

function calcTravelFee($distanceKm)
{
    if (!is_finite((float)$distanceKm)) return 0;
    if ($distanceKm <= 20) return 0;
    return (int)ceil($distanceKm - 20) * 5000;
}

/**
 * Tính thời lượng dịch vụ từ DB (phút)
 * - Nếu có service: lấy từ dich_vu.THOI_GIAN
 * - Nếu có package: lấy service đại diện hoặc default
 * - Nếu không có: return default 60 phút
 */
function calcDurationMin(&$conn, $bookingType, $serviceIdOrIds, $packageIdOrIds)
{
    $defaultDuration = (int)getenv('DEFAULT_DURATION_MIN') ?: 60;
    
    if ($bookingType === 'service') {
        // Support both single ID and array of IDs
        $serviceIds = is_array($serviceIdOrIds) ? $serviceIdOrIds : ($serviceIdOrIds ? [$serviceIdOrIds] : []);
        
        if (!empty($serviceIds)) {
            // Sum durations for all selected services
            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $stmt = $conn->prepare("SELECT SUM(THOI_GIAN) as TOTAL_TIME FROM dich_vu WHERE ID_DV IN ($placeholders)");
            
            if ($stmt) {
                // Bind parameters
                $types = str_repeat('i', count($serviceIds));
                $stmt->bind_param($types, ...$serviceIds);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                
                if ($row && $row['TOTAL_TIME']) {
                    return (int)$row['TOTAL_TIME'];
                }
            }
            // Fallback: default per service if query fails
            return count($serviceIds) * $defaultDuration;
        }
    } elseif ($bookingType === 'package' && $packageIdOrIds) {
        $pkgIds = is_array($packageIdOrIds) ? $packageIdOrIds : [$packageIdOrIds];
        $pkgIds = array_values(array_filter($pkgIds, fn($v) => ctype_digit((string)$v) || is_int($v)));
        if (!empty($pkgIds)) {
            $placeholders = implode(',', array_fill(0, count($pkgIds), '?'));
            $stmt = $conn->prepare(
                "SELECT SUM(d.THOI_GIAN) as TOTAL_TIME FROM goi_dich_vu_chi_tiet gdt 
                 JOIN dich_vu d ON d.ID_DV = gdt.ID_DV 
                 WHERE gdt.ID_GOI IN ($placeholders)"
            );
            if ($stmt) {
                $types = str_repeat('i', count($pkgIds));
                $stmt->bind_param($types, ...$pkgIds);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                if ($row && $row['TOTAL_TIME']) {
                    return (int)$row['TOTAL_TIME'];
                }
            }
        }
        // Nếu package không có dịch vụ, dùng default cộng thêm
        return (int)getenv('DEFAULT_REQUIRED_STAFF_PACKAGE') ? 
            (int)getenv('DEFAULT_REQUIRED_STAFF_PACKAGE') * 30 : 120;
    } elseif ($bookingType === 'costume') {
        return (int)getenv('DEFAULT_DURATION_COSTUME') ?: 30;
    }
    
    return $defaultDuration;
}

/**
 * Tính buffer di chuyển dựa trên khoảng cách (phút)
 * Công thức: ceil(distanceKm / 20) * 15 phút
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
 * Kiểm tra độc quyền tại chi nhánh: không có lịch khác overlap trong khoảng
 * Return: ['allowed' => true/false, 'reason' => string, 'suggested_next_start' => datetime|null]
 */
function checkBranchExclusivity(&$conn, $branchId, DateTime $startTime, DateTime $endTime, $excludeLichHenId = null)
{
    $enableCheck = (int)getenv('ENABLE_BRANCH_EXCLUSIVITY') ?: 1;
    if (!$enableCheck) {
        return ['allowed' => true, 'reason' => 'Kiểm tra độc quyền bị tắt'];
    }

    $startStr = $startTime->format('Y-m-d H:i:s');
    $endStr = $endTime->format('Y-m-d H:i:s');
    
    // Kiểm tra lịch tại chi nhánh (LOCATION_TYPE='branch') có overlap không
    $sql = "SELECT ID_LICHHEN, THOI_GIAN_BAT_DAU, 
                   COALESCE(THOI_GIAN_KET_THUC, DATE_ADD(THOI_GIAN_BAT_DAU, INTERVAL 60 MINUTE)) as THOI_GIAN_KET_THUC
            FROM lich_hen 
            WHERE ID_CHINHANH = ? 
              AND LOCATION_TYPE = 'branch'
              AND TRANGTHAI IN ('Đang chờ', 'Xác nhận', 'Đã duyệt', 'Đã xác nhận')
              " . ($excludeLichHenId ? "AND ID_LICHHEN != ? " : "") . "
              AND NOT (THOI_GIAN_KET_THUC <= ? OR THOI_GIAN_BAT_DAU >= ?)
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("[checkBranchExclusivity] Prepare failed: " . $conn->error);
        return ['allowed' => false, 'reason' => 'Lỗi kiểm tra dữ liệu'];
    }

    if ($excludeLichHenId) {
        $stmt->bind_param('iiss', $branchId, $excludeLichHenId, $startStr, $endStr);
    } else {
        $stmt->bind_param('iss', $branchId, $startStr, $endStr);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $conflictRow = $result->fetch_assoc();
    $stmt->close();

    if ($conflictRow) {
        error_log("[checkBranchExclusivity] Conflict found: ID_LICHHEN=" . $conflictRow['ID_LICHHEN']);
        return [
            'allowed' => false,
            'reason' => 'Khung giờ này đã có lịch khác tại chi nhánh. Vui lòng chọn khung giờ khác.',
            'suggested_next_start' => $conflictRow['THOI_GIAN_KET_THUC']
        ];
    }

    return ['allowed' => true, 'reason' => 'Độc quyền chi nhánh thỏa'];
}

/**
 * Kiểm tra nhân viên rảnh cho lịch ngoài chi nhánh
 * Return: ['allowed' => true/false, 'reason' => string, 'staff_available' => int, 'staff_required' => int]
 */
function checkExternalStaffAvailability(&$conn, $branchId, DateTime $startTime, DateTime $endTime, $serviceCount, $bookingType)
{
    $enableCheck = (int)getenv('ENABLE_EXTERNAL_STAFF_CHECK') ?: 1;
    if (!$enableCheck) {
        return ['allowed' => true, 'reason' => 'Kiểm tra nhân viên bị tắt'];
    }

    // Tính nhân viên cần
    $defaultServiceStaff = (int)getenv('DEFAULT_REQUIRED_STAFF_SERVICE') ?: 1;
    $defaultPackageStaff = (int)getenv('DEFAULT_REQUIRED_STAFF_PACKAGE') ?: 2;
    
    $requiredStaff = 0;
    if ($bookingType === 'service') {
        $requiredStaff = $serviceCount * $defaultServiceStaff;
    } elseif ($bookingType === 'package') {
        $requiredStaff = $defaultPackageStaff;
    } elseif ($bookingType === 'costume') {
        $requiredStaff = 1; // Trang phục thường cần ít nhân viên
    }

    // Tính tổng nhân viên của chi nhánh
    $staffTotal = 0;
    $stmtTotal = $conn->prepare(
        "SELECT COUNT(*) as total FROM nhan_vien WHERE ID_CN = ? AND IS_DELETED = 0"
    );
    if (!$stmtTotal) {
        error_log("[checkExternalStaffAvailability] Prepare failed (total): " . $conn->error);
        return ['allowed' => false, 'reason' => 'Lỗi kiểm tra nhân viên'];
    }
    $stmtTotal->bind_param('i', $branchId);
    $stmtTotal->execute();
    $stmtTotal->bind_result($staffTotal);
    $stmtTotal->fetch();
    $stmtTotal->close();

    // Tính nhân viên bận trong khoảng
    $staffBusy = 0;
    $startStr = $startTime->format('Y-m-d H:i:s');
    $endStr = $endTime->format('Y-m-d H:i:s');
    
    $sqlBusy = "SELECT COUNT(DISTINCT pcnv.ID_TK) as busy 
                FROM phan_cong_nhan_vien pcnv
                JOIN nhan_vien nv ON nv.ID_TK = pcnv.ID_TK
                WHERE nv.ID_CN = ? 
                  AND nv.IS_DELETED = 0
                  AND NOT (pcnv.THOI_GIAN_KET_THUC <= ? OR pcnv.THOI_GIAN_BAT_DAU >= ?)";
    
    $stmtBusy = $conn->prepare($sqlBusy);
    if (!$stmtBusy) {
        error_log("[checkExternalStaffAvailability] Prepare failed (busy): " . $conn->error);
        return ['allowed' => false, 'reason' => 'Lỗi kiểm tra nhân viên bận'];
    }
    $stmtBusy->bind_param('iss', $branchId, $startStr, $endStr);
    $stmtBusy->execute();
    $stmtBusy->bind_result($staffBusy);
    $stmtBusy->fetch();
    $stmtBusy->close();

    $staffAvailable = $staffTotal - $staffBusy;

    if ($staffAvailable < $requiredStaff) {
        error_log("[checkExternalStaffAvailability] Insufficient staff: available=$staffAvailable, required=$requiredStaff");
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
