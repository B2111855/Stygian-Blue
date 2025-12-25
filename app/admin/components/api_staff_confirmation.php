<?php
/**
 * API Handler cho AJAX requests từ staff assignments
 * Xử lý: Xác nhận hoàn thành, Hủy yêu cầu đổi lịch, v.v
 */
include '../../../database/config.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => false,
    'message' => 'Có lỗi không xác định',
    'data' => null
];

$staffId = $_SESSION['ID_TK'] ?? null;
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if (!$staffId) {
    http_response_code(401);
    $response['message'] = 'Chưa xác thực';
    echo json_encode($response);
    exit;
}

try {
    switch ($action) {
        case 'confirm_completion':
            handleConfirmCompletion();
            break;

        case 'cancel_request':
            handleCancelRequest();
            break;

        case 'mark_no_show':
            handleMarkNoShow();
            break;

        default:
            http_response_code(400);
            $response['message'] = 'Action không hợp lệ';
    }
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Lỗi server: ' . $e->getMessage();
}

echo json_encode($response);

/**
 * Xác nhận hoàn thành lịch hẹn
 */
function handleConfirmCompletion()
{
    global $conn, $staffId, $response;

    $idLichHen = (int)($_POST['id_lichhen'] ?? 0);

    if (!$idLichHen) {
        http_response_code(400);
        $response['message'] = 'ID lịch hẹn không hợp lệ';
        return;
    }

    // Kiểm tra nhân viên có thực sự được phân công lịch này không
    $checkStmt = $conn->prepare(
        "SELECT lh.THOI_GIAN_BAT_DAU, lh.TRANGTHAI 
         FROM phan_cong_nhan_vien pc 
         JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN 
         WHERE pc.ID_TK = ? AND pc.ID_LICHHEN = ? 
         LIMIT 1"
    );

    if (!$checkStmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $checkStmt->bind_param("si", $staffId, $idLichHen);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if (!($row = $result->fetch_assoc())) {
        http_response_code(403);
        $response['message'] = 'Bạn không có quyền xác nhận lịch hẹn này';
        $checkStmt->close();
        return;
    }

    $startTime = $row['THOI_GIAN_BAT_DAU'];
    $trangThai = $row['TRANGTHAI'];
    $checkStmt->close();

    // Kiểm tra điều kiện xác nhận
    $now = new DateTime();
    $startDate = null;

    try {
        $startDate = new DateTime($startTime);
    } catch (Exception $e) {
        $startDate = null;
    }

    // Xác nhận chỉ được phép nếu: lịch chưa hoàn thành/hủy VÀ đã đến thời gian bắt đầu
    if (!$startDate || $now < $startDate) {
        http_response_code(400);
        $response['message'] = 'Chưa đến thời gian bắt đầu lịch hẹn';
        return;
    }

    if ($trangThai === 'Đã hoàn thành') {
        http_response_code(400);
        $response['message'] = 'Lịch hẹn này đã được xác nhận hoàn thành trước đó';
        return;
    }

    if ($trangThai === 'Đã hủy') {
        http_response_code(400);
        $response['message'] = 'Lịch hẹn này đã bị hủy, không thể xác nhận hoàn thành';
        return;
    }

    // Cập nhật trạng thái
    $conn->begin_transaction();

    try {
        // Bước 1: Cập nhật trạng thái lịch hẹn
        $updateStmt = $conn->prepare("UPDATE lich_hen SET TRANGTHAI = 'Đã hoàn thành' WHERE ID_LICHHEN = ?");
        if (!$updateStmt) throw new Exception('Prepare update failed: ' . $conn->error);
        $updateStmt->bind_param("i", $idLichHen);

        if (!$updateStmt->execute()) {
            throw new Exception('Update failed: ' . $updateStmt->error);
        }
        $updateStmt->close();

        // Bước 2: Ghi nhận xác nhận hoàn thành
        $insertCompletionStmt = $conn->prepare("INSERT INTO xac_nhan_hoan_thanh (ID_LICHHEN, ID_TK) VALUES (?, ?)");
        if (!$insertCompletionStmt) throw new Exception('Prepare insert completion failed: ' . $conn->error);
        $insertCompletionStmt->bind_param("is", $idLichHen, $staffId);

        if (!$insertCompletionStmt->execute()) {
            throw new Exception('Insert completion failed: ' . $insertCompletionStmt->error);
        }
        $insertCompletionStmt->close();

        // Bước 3: Lấy thông tin hóa đơn và chi nhánh để ghi doanh thu
        $infoStmt = $conn->prepare(
            "SELECT hd.ID_HD, hd.TONG_TIEN, lh.ID_CHINHANH 
             FROM hoa_don hd 
             JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN 
             WHERE lh.ID_LICHHEN = ? 
             LIMIT 1"
        );
        if (!$infoStmt) throw new Exception('Prepare info failed: ' . $conn->error);
        $infoStmt->bind_param("i", $idLichHen);
        $infoStmt->execute();
        $infoResult = $infoStmt->get_result();
        $infoRow = $infoResult->fetch_assoc();
        $infoStmt->close();

        // Bước 4: Ghi doanh thu nếu chưa ghi (theo chuẩn kế toán, ghi khi hoàn thành)
        if ($infoRow) {
            $idHd = $infoRow['ID_HD'];
            $tongTien = $infoRow['TONG_TIEN'];
            $idChinhanh = $infoRow['ID_CHINHANH'];

            // Kiểm tra xem doanh thu đã được ghi chưa
            $checkRevenueStmt = $conn->prepare(
                "SELECT ID_TC FROM tai_chinh 
                 WHERE ID_HD = ? AND LOAI_GIAO_DICH = 'doanh thu' 
                 LIMIT 1"
            );
            if (!$checkRevenueStmt) throw new Exception('Prepare check revenue failed: ' . $conn->error);
            $checkRevenueStmt->bind_param("i", $idHd);
            $checkRevenueStmt->execute();
            $revenueResult = $checkRevenueStmt->get_result();
            $hasRevenue = $revenueResult->num_rows > 0;
            $checkRevenueStmt->close();

            // Nếu chưa ghi doanh thu, ghi vào với trạng thái tương ứng với hóa đơn
            if (!$hasRevenue) {
                // 🔧 FIX: Kiểm tra trạng thái hóa đơn để ghi doanh thu đúng trạng thái
                // (Nếu khách thanh toán trước khi nhân viên xác nhận)
                $checkInvoiceStmt = $conn->prepare(
                    "SELECT TRANGTHAI_THANHTOAN FROM hoa_don WHERE ID_HD = ? LIMIT 1"
                );
                if (!$checkInvoiceStmt) throw new Exception('Prepare check invoice failed: ' . $conn->error);
                $checkInvoiceStmt->bind_param("i", $idHd);
                $checkInvoiceStmt->execute();
                $invoiceResult = $checkInvoiceStmt->get_result();
                $invoiceRow = $invoiceResult->fetch_assoc();
                $checkInvoiceStmt->close();

                // Xác định trạng thái doanh thu dựa trên hóa đơn
                $revenueStatus = (isset($invoiceRow['TRANGTHAI_THANHTOAN']) && 
                                 $invoiceRow['TRANGTHAI_THANHTOAN'] === 'Đã thanh toán')
                                 ? 'đã thanh toán'
                                 : 'chờ thanh toán';

                $insertRevenueStmt = $conn->prepare(
                    "INSERT INTO tai_chinh (ID_HD, NGAY_GIAO_DICH, SO_TIEN, LOAI_GIAO_DICH, LOAI_CHI_TIET, ID_CN, TRANG_THAI) 
                     VALUES (?, NOW(), ?, 'doanh thu', 'Dịch vụ chụp ảnh', ?, ?)"
                );
                if (!$insertRevenueStmt) throw new Exception('Prepare insert revenue failed: ' . $conn->error);
                $insertRevenueStmt->bind_param("idis", $idHd, $tongTien, $idChinhanh, $revenueStatus);

                if (!$insertRevenueStmt->execute()) {
                    throw new Exception('Insert revenue failed: ' . $insertRevenueStmt->error);
                }
                $insertRevenueStmt->close();
            }
        }

        // Bước 5: Tính toán lương bonus dựa trên hoàn thành công việc
        updateStaffSalaryOnCompletion($conn, $staffId, $idLichHen, $idHd);

        $conn->commit();

        http_response_code(200);
        $response['success'] = true;
        $response['message'] = "Đã xác nhận hoàn thành lịch hẹn #$idLichHen. Doanh thu được ghi nhận và lương được tính.";
        $response['data'] = ['id_lichhen' => $idLichHen];

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        $response['message'] = 'Lỗi cập nhật: ' . $e->getMessage();
    }
}

/**
 * Tính toán và cập nhật lương bonus cho nhân viên dựa trên hoàn thành
 */
function updateStaffSalaryOnCompletion($conn, $staffId, $idLichHen, $idHd)
{
    $baseSalary = 5000000; // 5 triệu VND
    $bonusRate = 0.15; // 15%

    // Lấy tháng/năm hiện tại
    $month = (int)date('m');
    $year = (int)date('Y');

    // Tính tổng bonus từ tất cả công việc hoàn thành trong tháng
    $bonusSql = "
        SELECT COALESCE(SUM(dgdv.DON_GIA * ?), 0) AS tong_thuong
        FROM phan_cong_nhan_vien pc
        JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
        JOIN hoa_don hd ON hd.ID_LICHHEN = lh.ID_LICHHEN
        JOIN don_gia_dich_vu dgdv ON lh.ID_DV = dgdv.ID_DV
        WHERE pc.ID_TK = ?
          AND lh.TRANGTHAI = 'Đã hoàn thành'
          AND MONTH(hd.NGAY_GIO) = ?
          AND YEAR(hd.NGAY_GIO) = ?
    ";

    $bonusStmt = $conn->prepare($bonusSql);
    if (!$bonusStmt) {
        return false; // Lỗi nhưng không cần interrupt main flow
    }

    $bonusStmt->bind_param("dsii", $bonusRate, $staffId, $month, $year);
    $bonusStmt->execute();
    $bonusResult = $bonusStmt->get_result();
    $bonusRow = $bonusResult ? $bonusResult->fetch_assoc() : null;
    $bonusAmount = (int)($bonusRow['tong_thuong'] ?? 0);
    $bonusStmt->close();

    $totalSalary = $baseSalary + $bonusAmount;

    // Kiểm tra xem đã có bản ghi lương tháng này chưa
    $checkStmt = $conn->prepare(
        "SELECT ID_LUONG FROM luong_nhan_vien 
         WHERE ID_TK = ? AND THANG = ? AND NAM = ? LIMIT 1"
    );
    if (!$checkStmt) return false;

    $checkStmt->bind_param("sii", $staffId, $month, $year);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    // INSERT hoặc UPDATE lương
    if ($existing) {
        $updateStmt = $conn->prepare(
            "UPDATE luong_nhan_vien 
             SET LUONG_CO_BAN = ?, PHAN_TRAM_THUONG = ?, TONG_TIEN_THUONG = ?, TONG_LUONG = ?, NGAY_TINH = CURDATE()
             WHERE ID_TK = ? AND THANG = ? AND NAM = ?"
        );
        if (!$updateStmt) return false;

        $updateStmt->bind_param("idiisii", $baseSalary, $bonusRate, $bonusAmount, $totalSalary, $staffId, $month, $year);
        $result = $updateStmt->execute();
        $updateStmt->close();
        return $result;
    } else {
        $insertStmt = $conn->prepare(
            "INSERT INTO luong_nhan_vien (ID_TK, THANG, NAM, LUONG_CO_BAN, PHAN_TRAM_THUONG, TONG_TIEN_THUONG, TONG_LUONG, NGAY_TINH) 
             VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())"
        );
        if (!$insertStmt) return false;

        $insertStmt->bind_param("siiidii", $staffId, $month, $year, $baseSalary, $bonusRate, $bonusAmount, $totalSalary);
        $result = $insertStmt->execute();
        $insertStmt->close();
        return $result;
    }
}

/**
 * Đánh dấu lịch hẹn khách không đến (staff self-service)
 */
function handleMarkNoShow()
{
    global $conn, $staffId, $response;

    $idLichHen = (int)($_POST['id_lichhen'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if (!$idLichHen) {
        http_response_code(400);
        $response['message'] = 'ID lịch hẹn không hợp lệ';
        return;
    }

    $assignmentStmt = $conn->prepare(
        "SELECT lh.THOI_GIAN_BAT_DAU, lh.TRANGTHAI 
         FROM phan_cong_nhan_vien pc
         JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
         WHERE pc.ID_TK = ? AND pc.ID_LICHHEN = ?
         LIMIT 1"
    );

    if (!$assignmentStmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $assignmentStmt->bind_param('si', $staffId, $idLichHen);
    $assignmentStmt->execute();
    $assignmentResult = $assignmentStmt->get_result();

    if (!($row = $assignmentResult->fetch_assoc())) {
        http_response_code(403);
        $response['message'] = 'Bạn không có quyền cập nhật lịch này';
        $assignmentStmt->close();
        return;
    }

    $assignmentStmt->close();

    $startTime = $row['THOI_GIAN_BAT_DAU'] ?? null;
    $status = $row['TRANGTHAI'] ?? '';

    if (in_array($status, ['Đã hoàn thành', 'Đã hủy'], true)) {
        http_response_code(400);
        $response['message'] = 'Lịch này đã kết thúc, không thể báo khách không đến.';
        return;
    }

    if (!$startTime || $startTime === '0000-00-00 00:00:00') {
        http_response_code(400);
        $response['message'] = 'Lịch hẹn chưa có thời gian cụ thể, không thể xác nhận.';
        return;
    }

    try {
        $startDate = new DateTime($startTime);
    } catch (Exception $e) {
        http_response_code(400);
        $response['message'] = 'Không thể xác định thời gian lịch hẹn.';
        return;
    }

    $readyAt = clone $startDate;
    $readyAt->modify('+1 hour');
    $now = new DateTime();

    if ($now < $readyAt) {
        http_response_code(400);
        $response['message'] = 'Chỉ báo khách không đến sau ít nhất 1 giờ kể từ giờ bắt đầu.';
        return;
    }

    if (function_exists('mb_strlen')) {
        if (mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 500);
        }
    } elseif (strlen($reason) > 500) {
        $reason = substr($reason, 0, 500);
    }

    $conn->begin_transaction();

    try {
        $existingStmt = $conn->prepare("SELECT ID_LOG FROM lich_hen_no_show WHERE ID_LICHHEN = ? LIMIT 1");
        if (!$existingStmt) {
            throw new Exception('Prepare existing failed: ' . $conn->error);
        }
        $existingStmt->bind_param('i', $idLichHen);
        $existingStmt->execute();
        $existingResult = $existingStmt->get_result();
        $existingRow = $existingResult->fetch_assoc();
        $existingStmt->close();

        if ($existingRow) {
            $updateStmt = $conn->prepare(
                "UPDATE lich_hen_no_show 
                 SET KHACH_XUA_HIEN = 1, LI_DO_KHONG_DEN = ?, THOI_GIAN_KIEM_TRA_KHONG_DEN = NOW()
                 WHERE ID_LOG = ?"
            );
            if (!$updateStmt) {
                throw new Exception('Prepare update no-show failed: ' . $conn->error);
            }
            $idLog = (int)$existingRow['ID_LOG'];
            $updateStmt->bind_param('si', $reason, $idLog);
            if (!$updateStmt->execute()) {
                throw new Exception('Update no-show failed: ' . $updateStmt->error);
            }
            $updateStmt->close();
        } else {
            $insertStmt = $conn->prepare(
                "INSERT INTO lich_hen_no_show (ID_LICHHEN, KHACH_XUA_HIEN, LI_DO_KHONG_DEN, THOI_GIAN_KIEM_TRA_KHONG_DEN, CREATED_AT)
                 VALUES (?, 1, ?, NOW(), NOW())"
            );
            if (!$insertStmt) {
                throw new Exception('Prepare insert no-show failed: ' . $conn->error);
            }
            $insertStmt->bind_param('is', $idLichHen, $reason);
            if (!$insertStmt->execute()) {
                throw new Exception('Insert no-show failed: ' . $insertStmt->error);
            }
            $insertStmt->close();
        }

        $statusStmt = $conn->prepare("UPDATE lich_hen SET TRANGTHAI = 'Không đến' WHERE ID_LICHHEN = ?");
        if (!$statusStmt) {
            throw new Exception('Prepare update status failed: ' . $conn->error);
        }
        $statusStmt->bind_param('i', $idLichHen);
        if (!$statusStmt->execute()) {
            throw new Exception('Update status failed: ' . $statusStmt->error);
        }
        $statusStmt->close();

        $conn->commit();

        http_response_code(200);
        $response['success'] = true;
        $response['message'] = 'Đã ghi nhận khách không đến.';
        $response['data'] = [
            'id_lichhen' => $idLichHen,
            'reason' => $reason,
            'recorded_at' => (new DateTime())->format('d/m/Y H:i')
        ];
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        $response['message'] = 'Không thể cập nhật: ' . $e->getMessage();
    }
}

/**
 * Hủy yêu cầu đổi lịch
 */
function handleCancelRequest()
{
    global $conn, $staffId, $response;

    $idLichHen = (int)($_POST['id_lichhen'] ?? 0);

    if (!$idLichHen) {
        http_response_code(400);
        $response['message'] = 'ID lịch hẹn không hợp lệ';
        return;
    }

    // Kiểm tra yêu cầu tồn tại và thuộc về nhân viên này
    $checkStmt = $conn->prepare(
        "SELECT ID_YEUCAN FROM yeu_cau_thay_doi_lich 
         WHERE ID_LICHHEN = ? AND ID_TK = ? AND TRANGTHAI = 'Chờ duyệt' 
         ORDER BY NGAY_GUI DESC LIMIT 1"
    );

    if (!$checkStmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $checkStmt->bind_param("is", $idLichHen, $staffId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if (!($row = $result->fetch_assoc())) {
        http_response_code(404);
        $response['message'] = 'Không tìm thấy yêu cầu đang chờ duyệt';
        $checkStmt->close();
        return;
    }

    $idYeuCau = $row['ID_YEUCAN'];
    $checkStmt->close();

    // Hủy yêu cầu
    $deleteStmt = $conn->prepare("DELETE FROM yeu_cau_thay_doi_lich WHERE ID_YEUCAN = ?");
    if (!$deleteStmt) {
        throw new Exception('Prepare delete failed: ' . $conn->error);
    }

    $deleteStmt->bind_param("i", $idYeuCau);

    if (!$deleteStmt->execute()) {
        http_response_code(500);
        $response['message'] = 'Lỗi hủy yêu cầu: ' . $deleteStmt->error;
        $deleteStmt->close();
        return;
    }

    $deleteStmt->close();

    http_response_code(200);
    $response['success'] = true;
    $response['message'] = 'Đã hủy yêu cầu đổi lịch';
    $response['data'] = ['id_lichhen' => $idLichHen];
}
