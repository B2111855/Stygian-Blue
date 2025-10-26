<?php
session_start();
include '../../../database/config.php';

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
$branchId   = $_POST['branch_id']    ?? null;
$ngayHen    = $_POST['ngayHen']      ?? null; // yyyy-mm-dd
$gioHen     = $_POST['gioHen']       ?? null; // HH:mm hoặc HH:mm:ss
$address    = $_POST['address']      ?? '';
$serviceId  = $_POST['service_id']   ?? null;
$userId     = $_SESSION['ID_TK']     ?? null;
$selectedDevices = isset($_POST['thiet_bi_id']) ? $_POST['thiet_bi_id'] : [];

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
if (!$serviceId)  $errors[] = "Thiếu dịch vụ.";
if (!$startTime)  $errors[] = "Thiếu thời gian hẹn.";
if (trim($address) === '') $errors[] = "Vui lòng nhập địa điểm.";

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
    // Bạn có thể đổi 'lienhe.php' thành trang form đặt lịch thực tế
    header("Location: ../lienhe.php");
    exit();
}


// =====================================================
// 3. TRANSACTION: tạo lịch hẹn + thiết bị
// =====================================================
$conn->begin_transaction();

try {

    // 3.1 Thêm lịch hẹn
    $sqlInsertLich = "
        INSERT INTO lich_hen 
            (ID_TK, THOI_GIAN_BAT_DAU, DIA_CHI_HEN, ID_DV, TRANGTHAI, ID_CHINHANH)
        VALUES (?, ?, ?, ?, 'Đang chờ', ?)
    ";
    $stmt = $conn->prepare($sqlInsertLich);
    if ($stmt === false) {
        throw new Exception("Lỗi chuẩn bị truy vấn lịch hẹn: " . $conn->error);
    }

    // Ép thời gian về định dạng MySQL chuẩn Y-m-d H:i:s
    $startTimeMySQL = $selectedDateTime->format('Y-m-d H:i:s');

    $stmt->bind_param("sssii", $userId, $startTimeMySQL, $address, $serviceId, $branchId);

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
    $conn->commit();

    $_SESSION['message'] = "Đặt lịch hẹn thành công!";
    $_SESSION['message_type'] = "success";

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

    // bạn có thể đổi trang này sang form đặt lịch của bạn
    header("Location: ../lienhe.php");
    exit();
}
