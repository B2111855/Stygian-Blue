<?php
// view_schedule_data.php
// Load authentication/session bootstrap before rendering schedule list
require_once __DIR__ . '/auth_state_boot.php';
require_once __DIR__ . '/../../helpers/assets.php';

// Ensure CSRF token exists for inline forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Block guests
if (empty($_SESSION['ID_TK'])) {
    header('Location: ../../Pages/Login/login.php');
    exit();
}
$userId = $_SESSION['ID_TK'];

// Handle cancel action before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = 'Phiên không hợp lệ. Vui lòng thử lại.';
        header('Location: xemLichhen.php');
        exit();
    }

    $lichHenId = $_POST['id_lichhen'] ?? null;
    if ($lichHenId && ctype_digit((string) $lichHenId)) {
        $sqlCancel = "
            UPDATE lich_hen
            SET TRANGTHAI = 'Đã hủy'
            WHERE ID_LICHHEN = ?
              AND ID_TK = ?
              AND TRANGTHAI = 'Đang chờ'
        ";
        $stmtCancel = $conn->prepare($sqlCancel);
        if ($stmtCancel) {
            $stmtCancel->bind_param('is', $lichHenId, $userId);
            $stmtCancel->execute();

            if ($stmtCancel->affected_rows > 0) {
                $_SESSION['success'] = "Đã hủy lịch hẹn #$lichHenId.";
            } else {
                $_SESSION['error'] = 'Không thể hủy lịch này (có thể đã được xác nhận hoặc không thuộc về bạn).';
            }
            $stmtCancel->close();
        } else {
            $_SESSION['error'] = 'Không thể hủy lịch lúc này.';
        }
    } else {
        $_SESSION['error'] = 'Yêu cầu không hợp lệ.';
    }

    header('Location: xemLichhen.php');
    exit();
}

$sqlMain = "
SELECT 
    lh.ID_LICHHEN,
    lh.THOI_GIAN_BAT_DAU,
    lh.DIA_CHI_HEN,
    lh.TRANGTHAI,
    lh.ID_DV,
    dv.TEN_DV,
    dv.thoi_gian AS THOI_GIAN_DV,
    cn.TEN_CN AS TEN_CHI_NHANH,
    hd.ID_HD,
    hd.TRANGTHAI_THANHTOAN,
    hd.TONG_TIEN,
    tt.TRANG_THAI AS VNPAY_TRANG_THAI,
    tt.MA_THAM_CHIEU AS VNPAY_MA_THAM_CHIEU,
    tt.CREATED_AT AS VNPAY_UPDATED_AT,
    ph.NOI_DUNG AS NOI_DUNG_PH,
    ph.XEP_HANG_DV,
    CASE WHEN ph.ID_TK IS NULL THEN 0 ELSE 1 END AS DA_GUI_PHAN_HOI
FROM lich_hen lh
JOIN dich_vu dv         ON lh.ID_DV = dv.ID_DV
LEFT JOIN chi_nhanh cn  ON lh.ID_CHINHANH = cn.ID_CN
LEFT JOIN hoa_don hd    ON hd.ID_LICHHEN = lh.ID_LICHHEN
LEFT JOIN (
    SELECT t1.ID_HD,
           t1.TRANG_THAI,
           t1.MA_THAM_CHIEU,
           t1.CREATED_AT
    FROM thanh_toan_truc_tuyen t1
    JOIN (
        SELECT ID_HD, MAX(CREATED_AT) AS latest_created
        FROM thanh_toan_truc_tuyen
        WHERE GATEWAY = 'vnpay'
        GROUP BY ID_HD
    ) latest ON latest.ID_HD = t1.ID_HD AND latest.latest_created = t1.CREATED_AT
    WHERE t1.GATEWAY = 'vnpay'
) tt ON tt.ID_HD = hd.ID_HD
LEFT JOIN phan_hoi_cua_khach_hang ph 
       ON ph.ID_TK = lh.ID_TK 
      AND ph.ID_DV = lh.ID_DV
WHERE lh.ID_TK = ?
ORDER BY lh.THOI_GIAN_BAT_DAU DESC
";

$stmtMain = $conn->prepare($sqlMain);
if (!$stmtMain) {
    die('Lỗi truy vấn lịch hẹn: ' . $conn->error);
}
$stmtMain->bind_param('s', $userId);
$stmtMain->execute();
$rsMain = $stmtMain->get_result();

$schedules = [];
$lichIds = [];

while ($row = $rsMain->fetch_assoc()) {
    $idLich = $row['ID_LICHHEN'];
    $schedules[$idLich] = $row;
    $lichIds[] = $idLich;
}
$stmtMain->close();

if (empty($lichIds)) {
    $staffMap = [];
    $deviceMap = [];
    $quoteMap  = [];
} else {
    $inClause = implode(',', array_map('intval', $lichIds));

    $sqlStaff = "
        SELECT pcnv.ID_LICHHEN,
               nv.ID_TK,
               nv.HO_TEN,
               nv.CHUYEN_MON
        FROM phan_cong_nhan_vien pcnv
        JOIN nhan_vien nv ON nv.ID_TK = pcnv.ID_TK
        WHERE pcnv.ID_LICHHEN IN ($inClause)
    ";
    $rsStaff = $conn->query($sqlStaff);

    $staffMap = [];
    if ($rsStaff) {
        while ($r = $rsStaff->fetch_assoc()) {
            $lid = $r['ID_LICHHEN'];
            if (!isset($staffMap[$lid])) {
                $staffMap[$lid] = [];
            }
            $staffMap[$lid][] = [
                'HO_TEN'      => $r['HO_TEN'] ?? $r['ID_TK'],
                'CHUYEN_MON'  => $r['CHUYEN_MON'] ?? ''
            ];
        }
    }

    $sqlDevice = "
        SELECT lhtb.ID_LICHHEN,
               ttb.ID_TB,
               ttb.TEN_TB,
               lhtb.SO_LUONG
        FROM lich_hen_thiet_bi lhtb
        JOIN trang_thiet_bi ttb ON ttb.ID_TB = lhtb.ID_TB
        WHERE lhtb.ID_LICHHEN IN ($inClause)
    ";
    $rsDevice = $conn->query($sqlDevice);

    $deviceMap = [];
    if ($rsDevice) {
        while ($r = $rsDevice->fetch_assoc()) {
            $lid = $r['ID_LICHHEN'];
            if (!isset($deviceMap[$lid])) {
                $deviceMap[$lid] = [];
            }
            $deviceMap[$lid][] = [
                'TEN_TB'   => $r['TEN_TB'],
                'SO_LUONG' => $r['SO_LUONG']
            ];
        }
    }

    $sqlQuote = "
        SELECT ID_LICHHEN, TONG_TAM_TINH, CHI_TIET_JSON
        FROM bao_gia_tam_tinh
        WHERE ID_LICHHEN IN ($inClause)
    ";
    $rsQuote = $conn->query($sqlQuote);

    $quoteMap = [];
    if ($rsQuote) {
        while ($r = $rsQuote->fetch_assoc()) {
            $quoteMap[$r['ID_LICHHEN']] = [
                'TONG_TAM_TINH' => $r['TONG_TAM_TINH'],
                'CHI_TIET_JSON' => $r['CHI_TIET_JSON']
            ];
        }
    }

    // Fallback: nếu không có báo giá tạm tính được lưu, ước tính theo đơn giá mới nhất và thời lượng lịch
    foreach ($schedules as $lid => $row) {
        $hasQuote = isset($quoteMap[$lid]) && ($quoteMap[$lid]['TONG_TAM_TINH'] ?? null);
        $total = (int)($hasQuote ? $quoteMap[$lid]['TONG_TAM_TINH'] : 0);
        if ($total > 0) continue;

        $idDv = (int)$row['ID_DV'];
        if ($idDv <= 0) continue;

        $unitPrice = 0;
        $stmtUp = $conn->prepare("SELECT DON_GIA FROM DON_GIA_DICH_VU WHERE ID_DV = ? ORDER BY NGAY_GIO DESC LIMIT 1");
        if ($stmtUp) {
            $stmtUp->bind_param('i', $idDv);
            if ($stmtUp->execute()) {
                $rsUp = $stmtUp->get_result();
                if ($rsUp && ($r = $rsUp->fetch_assoc())) {
                    $unitPrice = (int)($r['DON_GIA'] ?? 0);
                }
            }
            $stmtUp->close();
        }

        // Tính thời lượng giờ: ưu tiên trường thời_gian (phút) của dịch vụ nếu có, mặc định 1 giờ
        $hours = 1;
        if (!empty($row['THOI_GIAN_DV'])) {
            $minutes = (float)$row['THOI_GIAN_DV'];
            if (is_finite($minutes) && $minutes > 0) {
                $hours = (int)max(1, ceil($minutes / 60));
            }
        }

        $estimate = max(0, $unitPrice * $hours);
        if ($estimate > 0) {
            $quoteMap[$lid] = [
                'TONG_TAM_TINH' => $estimate,
                'CHI_TIET_JSON' => json_encode([
                    'items' => [
                        [ 'label' => 'Dịch vụ', 'gia' => $unitPrice, 'so_luong' => $hours, 'thanh_tien' => $estimate ],
                    ],
                    'note' => 'Ước tính từ đơn giá hiện tại và thời lượng lịch',
                ], JSON_UNESCAPED_UNICODE)
            ];
        }
    }
}

if (!function_exists('renderStatusBadge')) {
    function renderStatusBadge($status) {
        $map = [
            'Đang chờ'      => ['bg' => 'bg-amber-100 text-amber-700', 'label' => 'Đang chờ'],
            'Đã xác nhận'   => ['bg' => 'bg-blue-100 text-blue-700',  'label' => 'Đã xác nhận'],
            'Đã hoàn thành' => ['bg' => 'bg-green-100 text-green-700','label' => 'Hoàn thành'],
            'Đã hủy'        => ['bg' => 'bg-red-100 text-red-700',    'label' => 'Đã hủy'],
        ];
        $cfg = $map[$status] ?? ['bg' => 'bg-gray-100 text-gray-600', 'label' => ($status ?: 'Không rõ')];
        return "<span class=\"{$cfg['bg']} px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap\">{$cfg['label']}</span>";
    }
}

if (!function_exists('renderPaymentBadge')) {
    function renderPaymentBadge($paymentStatus, $gatewayStatus = null) {
        if (!$paymentStatus) {
            return '<span class="text-xs text-gray-500 italic whitespace-nowrap">Chờ xác nhận</span>';
        }
        if ($paymentStatus === 'Đã thanh toán') {
            return '<span class="bg-emerald-100 text-emerald-700 px-2 py-1 text-xs rounded-md font-medium whitespace-nowrap">Đã thanh toán</span>';
        }
        if ($gatewayStatus === 'pending') {
            return '<span class="bg-amber-100 text-amber-700 px-2 py-1 text-xs rounded-md font-medium whitespace-nowrap">Đang xử lý cổng</span>';
        }
        if ($gatewayStatus === 'failed') {
            return '<span class="bg-rose-100 text-rose-700 px-2 py-1 text-xs rounded-md font-medium whitespace-nowrap">Giao dịch lỗi</span>';
        }
        return '<span class="bg-rose-100 text-rose-700 px-2 py-1 text-xs rounded-md font-medium whitespace-nowrap">Chưa thanh toán</span>';
    }
}

if (!function_exists('jsSafe')) {
    function jsSafe($val) {
        return htmlspecialchars(json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

define('VIEW_SCHEDULE_DATA_READY', true);
