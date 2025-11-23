<?php
session_start();
include '../../../database/config.php';
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
$bookingType   = $_POST['booking_type'] ?? 'service';
$userId        = $_SESSION['ID_TK']     ?? null;
$selectedDevices = isset($_POST['thiet_bi_id']) ? $_POST['thiet_bi_id'] : [];
$selectedCostumesRaw = isset($_POST['costume_ids']) && is_array($_POST['costume_ids']) ? $_POST['costume_ids'] : [];
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
if ($bookingType === 'package' && !$packageId) {
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
// 3. TRANSACTION: tạo lịch hẹn + thiết bị
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
    // Kiểm tra cột mở rộng trong lich_hen
    $hasLocationCols = false;
    if ($rs2 = $conn->query("SHOW COLUMNS FROM LICH_HEN LIKE 'LOCATION_TYPE'")) {
        $hasLocationCols = $rs2->num_rows > 0; $rs2->free_result();
    }

    if ($hasLocationCols) {
        if ($bookingType === 'package') {
            $sqlInsertLich = "
                INSERT INTO lich_hen
                    (ID_TK, THOI_GIAN_BAT_DAU, DIA_CHI_HEN, ID_DV, ID_GOI, TRANGTHAI, ID_CHINHANH,
                     LOCATION_TYPE, LOCATION_ADDRESS, LOCATION_LAT, LOCATION_LNG, DISTANCE_KM, TRAVEL_FEE)
                VALUES (?, ?, ?, ?, ?, 'Đang chờ', ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $conn->prepare($sqlInsertLich);
        } elseif ($bookingType === 'costume') {
            $sqlInsertLich = "
                INSERT INTO lich_hen
                    (ID_TK, THOI_GIAN_BAT_DAU, DIA_CHI_HEN, ID_DV, ID_GOI, TRANGTHAI, ID_CHINHANH,
                     LOCATION_TYPE, LOCATION_ADDRESS, LOCATION_LAT, LOCATION_LNG, DISTANCE_KM, TRAVEL_FEE)
                VALUES (?, ?, ?, NULL, NULL, 'Đang chờ', ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $conn->prepare($sqlInsertLich);
        } else {
            $sqlInsertLich = "
                INSERT INTO lich_hen
                    (ID_TK, THOI_GIAN_BAT_DAU, DIA_CHI_HEN, ID_DV, ID_GOI, TRANGTHAI, ID_CHINHANH,
                     LOCATION_TYPE, LOCATION_ADDRESS, LOCATION_LAT, LOCATION_LNG, DISTANCE_KM, TRAVEL_FEE)
                VALUES (?, ?, ?, ?, NULL, 'Đang chờ', ?, ?, ?, ?, ?, ?, ?)
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

    if ($hasLocationCols) {
        // Chuẩn hoá giá trị lưu
        $locAddr = $locationType === 'external' ? ($address ?? '') : ("Chi nhánh " . (string)$branchId);
        $locLat  = $locationType === 'external' && is_finite((float)$extLat) ? (float)$extLat : null;
        $locLng  = $locationType === 'external' && is_finite((float)$extLng) ? (float)$extLng : null;
        $distVal = $distanceKm !== null ? (float)$distanceKm : null;
        $feeVal  = (int)$travelFee;

        if ($bookingType === 'package') {
            $stmt->bind_param(
                "sssiiissdddi",
                $userId, $startTimeMySQL, $address, $serviceId, $packageId, $branchId,
                $locationType, $locAddr, $locLat, $locLng, $distVal, $feeVal
            );
        } elseif ($bookingType === 'costume') {
            $stmt->bind_param(
                "sssissdddi",
                $userId, $startTimeMySQL, $address, $branchId,
                $locationType, $locAddr, $locLat, $locLng, $distVal, $feeVal
            );
        } else {
            $stmt->bind_param(
                "sssiissdddi",
                $userId, $startTimeMySQL, $address, $serviceId, $branchId,
                $locationType, $locAddr, $locLat, $locLng, $distVal, $feeVal
            );
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

        if ($hasBookingItem && $bookingType === 'package' && $packageId) {
            // Lấy giá gói
            $packagePrice = 0;
            $stmtPkg = $conn->prepare("SELECT TONG_GIA_GOI FROM v_goi_dich_vu_tong_tien WHERE ID_GOI = ? LIMIT 1");
            if ($stmtPkg) {
                $stmtPkg->bind_param('i', $packageId);
                $stmtPkg->execute();
                $resPkg = $stmtPkg->get_result();
                $rowPkg = $resPkg ? $resPkg->fetch_assoc() : null;
                $packagePrice = $rowPkg && isset($rowPkg['TONG_GIA_GOI']) ? (int)$rowPkg['TONG_GIA_GOI'] : 0;
                $stmtPkg->close();
            }

            $stmtItem = $conn->prepare("INSERT INTO BOOKING_ITEM (ID_LICHHEN, ITEM_TYPE, REF_ID, DON_GIA, SO_LUONG, DISCOUNT_PERCENT, NOTE) VALUES (?, 'package', ?, ?, 1, 0, 'Gói dịch vụ')");
            if ($stmtItem) {
                $stmtItem->bind_param('iii', $lichHenId, $packageId, $packagePrice);
                $stmtItem->execute();
                $stmtItem->close();
            }

            $costumeMap = [];
            $stmtC = $conn->prepare("SELECT tp.ID_TRANG_PHUC, tp.TEN, tp.GIA_THUE, gtp.DISCOUNT_PERCENT, gtp.BAT_BUOC
                                       FROM GOI_TRANG_PHUC gtp
                                       JOIN TRANG_PHUC tp ON tp.ID_TRANG_PHUC = gtp.ID_TRANG_PHUC
                                       WHERE gtp.ID_GOI = ?");
            if ($stmtC) {
                $stmtC->bind_param('i', $packageId);
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
        }
    }

    $conn->commit();

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

// Helpers
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
