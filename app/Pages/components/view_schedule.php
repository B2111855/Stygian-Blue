<?php
// view_schedule.php
require_once '../../../database/config.php';

// Tạo CSRF token nếu chưa có (dùng cho tất cả form trong trang)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// 1. CHẶN KHÁCH CHƯA LOGIN
if (!isset($_SESSION['ID_TK'])) {
    header("Location: ../../Pages/Login/login.php");
    exit();
}
$userId = $_SESSION['ID_TK'];

// 2. XỬ LÝ HỦY LỊCH (POST ngay trên trang)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {

    // CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Phiên không hợp lệ. Vui lòng thử lại.";
        header("Location: xemLichhen.php");
        exit();
    }

    $lichHenId = $_POST['id_lichhen'] ?? null;

    if ($lichHenId && ctype_digit((string)$lichHenId)) {
        $sqlCancel = "
            UPDATE lich_hen
            SET TRANGTHAI = 'Đã hủy'
            WHERE ID_LICHHEN = ?
              AND ID_TK = ?
              AND TRANGTHAI = 'Đang chờ'
        ";
        $stmtCancel = $conn->prepare($sqlCancel);
        if ($stmtCancel) {
            $stmtCancel->bind_param("is", $lichHenId, $userId);
            $stmtCancel->execute();

            if ($stmtCancel->affected_rows > 0) {
                $_SESSION['success'] = "Đã hủy lịch hẹn #$lichHenId.";
            } else {
                $_SESSION['error'] = "Không thể hủy lịch này (có thể đã được xác nhận hoặc không thuộc về bạn).";
            }
            $stmtCancel->close();
        } else {
            $_SESSION['error'] = "Không thể hủy lịch lúc này.";
        }
    } else {
        $_SESSION['error'] = "Yêu cầu không hợp lệ.";
    }

    header("Location: xemLichhen.php");
    exit();
}

/*
3. TRUY VẤN LỊCH HẸN CHÍNH
- THÔNG TIN LỊCH
- DỊCH VỤ
- CHI NHÁNH
- HÓA ĐƠN
- FEEDBACK
*/
$sqlMain = "
SELECT 
    lh.ID_LICHHEN,
    lh.THOI_GIAN_BAT_DAU,
    lh.DIA_CHI_HEN,
    lh.TRANGTHAI,
    lh.ID_DV,
    dv.TEN_DV,
    cn.TEN_CN AS TEN_CHI_NHANH,
    hd.ID_HD,
    hd.TRANGTHAI_THANHTOAN,
    hd.TONG_TIEN,
    hd.YEU_CAU_XAC_NHAN,
    ph.NOI_DUNG AS NOI_DUNG_PH,
    ph.XEP_HANG_DV,
    CASE WHEN ph.ID_TK IS NULL THEN 0 ELSE 1 END AS DA_GUI_PHAN_HOI
FROM lich_hen lh
JOIN dich_vu dv         ON lh.ID_DV = dv.ID_DV
LEFT JOIN chi_nhanh cn  ON lh.ID_CHINHANH = cn.ID_CN
LEFT JOIN hoa_don hd    ON hd.ID_LICHHEN = lh.ID_LICHHEN
LEFT JOIN phan_hoi_cua_khach_hang ph 
       ON ph.ID_TK = lh.ID_TK 
      AND ph.ID_DV = lh.ID_DV
WHERE lh.ID_TK = ?
ORDER BY lh.THOI_GIAN_BAT_DAU DESC
";

$stmtMain = $conn->prepare($sqlMain);
if (!$stmtMain) {
    die("Lỗi truy vấn lịch hẹn: " . $conn->error);
}
$stmtMain->bind_param("s", $userId);
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

// Nếu không có lịch thì khỏi query phụ
if (empty($lichIds)) {
    $staffMap = [];
    $deviceMap = [];
    $quoteMap  = [];
} else {
    $inClause = implode(',', array_map('intval', $lichIds));

    /*
    4. TRUY VẤN NHÂN VIÊN PHỤ TRÁCH CHO MỖI LỊCH
    - phan_cong_nhan_vien + nhan_vien
    */
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

    $staffMap = []; // [ID_LICHHEN] => [ {ten_nv, chuyen_mon}, ... ]
    if ($rsStaff) {
        while ($r = $rsStaff->fetch_assoc()) {
            $lid = $r['ID_LICHHEN'];
            if (!isset($staffMap[$lid])) $staffMap[$lid] = [];
            $staffMap[$lid][] = [
                'HO_TEN'      => $r['HO_TEN'] ?? $r['ID_TK'],
                'CHUYEN_MON'  => $r['CHUYEN_MON'] ?? ''
            ];
        }
    }

    /*
    5. TRUY VẤN THIẾT BỊ ĐÃ GẮN
    - lich_hen_thiet_bi + trang_thiet_bi
      (bảng trang_thiet_bi tồn tại trong dbv4 với các cột ID_TB, TEN_TB,...)
    */
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

    $deviceMap = []; // [ID_LICHHEN] => [ {TEN_TB, SO_LUONG}, ...]
    if ($rsDevice) {
        while ($r = $rsDevice->fetch_assoc()) {
            $lid = $r['ID_LICHHEN'];
            if (!isset($deviceMap[$lid])) $deviceMap[$lid] = [];
            $deviceMap[$lid][] = [
                'TEN_TB'   => $r['TEN_TB'],
                'SO_LUONG' => $r['SO_LUONG']
            ];
        }
    }

    /*
    6. TRUY VẤN BÁO GIÁ TẠM TÍNH
    - bao_gia_tam_tinh (ID_LICHHEN, TONG_TAM_TINH, CHI_TIET_JSON)
    */
    $sqlQuote = "
        SELECT ID_LICHHEN, TONG_TAM_TINH, CHI_TIET_JSON
        FROM bao_gia_tam_tinh
        WHERE ID_LICHHEN IN ($inClause)
    ";
    $rsQuote = $conn->query($sqlQuote);

    $quoteMap = []; // [ID_LICHHEN] => ['TONG_TAM_TINH'=>..., 'CHI_TIET_JSON'=>...]
    if ($rsQuote) {
        while ($r = $rsQuote->fetch_assoc()) {
            $quoteMap[$r['ID_LICHHEN']] = [
                'TONG_TAM_TINH' => $r['TONG_TAM_TINH'],
                'CHI_TIET_JSON' => $r['CHI_TIET_JSON']
            ];
        }
    }
}

$conn->close();

/*
7. HÀM HIỂN THỊ BADGE
*/
function renderStatusBadge($status) {
    $map = [
        'Đang chờ'       => ['bg' => 'bg-amber-100 text-amber-700', 'label' => 'Đang chờ'],
        'Đã xác nhận'    => ['bg' => 'bg-blue-100 text-blue-700',  'label' => 'Đã xác nhận'],
        'Đã hoàn thành'  => ['bg' => 'bg-green-100 text-green-700','label' => 'Hoàn thành'],
        'Đã hủy'         => ['bg' => 'bg-red-100 text-red-700',    'label' => 'Đã hủy'],
    ];
    $cfg = $map[$status] ?? ['bg' => 'bg-gray-100 text-gray-600', 'label' => ($status ?: 'Không rõ')];
    return "<span class=\"{$cfg['bg']} px-3 py-1 rounded-full text-xs font-medium\">{$cfg['label']}</span>";
}

function renderPaymentBadge($paymentStatus) {
    if (!$paymentStatus) {
        return '<span class="text-xs text-gray-400 italic">Chưa tạo hóa đơn</span>';
    }
    if ($paymentStatus === 'Đã thanh toán') {
        return '<span class="bg-emerald-100 text-emerald-700 px-2 py-1 text-xs rounded-md font-medium">Đã thanh toán</span>';
    }
    return '<span class="bg-rose-100 text-rose-700 px-2 py-1 text-xs rounded-md font-medium">Chưa thanh toán</span>';
}

// Helper để encode array sang JS safely
function jsSafe($val) {
    return htmlspecialchars(json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lịch hẹn của bạn</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 via-blue-100 to-blue-200 min-h-screen text-gray-800">

    <div class="max-w-6xl mx-auto px-4 py-8">

        <!-- Flash message -->
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="mb-4 rounded-md bg-green-100 text-green-800 px-4 py-3 text-sm font-medium shadow">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="mb-4 rounded-md bg-red-100 text-red-800 px-4 py-3 text-sm font-medium shadow">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <header class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl font-extrabold text-blue-900 drop-shadow">
                Lịch hẹn của bạn
            </h1>
            <p class="text-gray-600 mt-2 text-sm md:text-base">
                Theo dõi trạng thái lịch chụp / thuê thiết bị, thanh toán và gửi phản hồi dịch vụ.
            </p>
        </header>

        <?php if (empty($schedules)): ?>
            <div class="bg-white/60 backdrop-blur-sm border border-gray-200 rounded-xl p-8 text-center shadow">
                <p class="text-gray-500 text-lg">Bạn chưa có lịch hẹn nào.</p>
                <a href="../Views/datLich.php"
                   class="inline-block mt-4 bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg shadow hover:bg-blue-700">
                   Đặt lịch ngay
                </a>
            </div>
        <?php else: ?>

            <div class="overflow-x-auto bg-white rounded-xl shadow-lg border border-gray-200">
                <table class="min-w-full text-sm text-center">
                    <thead>
                        <tr class="bg-gradient-to-r from-blue-100 to-blue-200 text-gray-800 text-xs uppercase font-semibold">
                            <th class="py-3 px-4 border-b">#</th>
                            <th class="py-3 px-4 border-b">Thời gian</th>
                            <th class="py-3 px-4 border-b">Dịch vụ</th>
                            <th class="py-3 px-4 border-b">Địa điểm</th>
                            <th class="py-3 px-4 border-b">Chi nhánh</th>
                            <th class="py-3 px-4 border-b">Trạng thái</th>
                            <th class="py-3 px-4 border-b">Thanh toán</th>
                            <th class="py-3 px-4 border-b">Hành động</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">

                        <?php 
                        $idx = 0;
                        foreach ($schedules as $lichId => $sc): 
                            $idx++;

                            $isDone         = ($sc['TRANGTHAI'] === 'Đã hoàn thành');
                            $isPending      = ($sc['TRANGTHAI'] === 'Đang chờ');
                            $isConfirmed    = ($sc['TRANGTHAI'] === 'Đã xác nhận');
                            $hasFeedback    = ((int)$sc['DA_GUI_PHAN_HOI'] === 1);
                            $canPayNow      = ($isConfirmed && $sc['TRANGTHAI_THANHTOAN'] !== 'Đã thanh toán');

                            // build payload chi tiết cho JS modal
                            $detailPayload = [
                                'id_lichhen'   => $lichId,
                                'thoi_gian'    => $sc['THOI_GIAN_BAT_DAU'],
                                'dia_diem'     => $sc['DIA_CHI_HEN'],
                                'dich_vu'      => $sc['TEN_DV'],
                                'chi_nhanh'    => $sc['TEN_CHI_NHANH'] ?? '—',
                                'trang_thai'   => $sc['TRANGTHAI'],
                                'nhan_vien'    => $staffMap[$lichId] ?? [],
                                'thiet_bi'     => $deviceMap[$lichId] ?? [],
                                'tam_tinh'     => $quoteMap[$lichId]['TONG_TAM_TINH'] ?? null,
                                'chi_tiet_bg'  => $quoteMap[$lichId]['CHI_TIET_JSON'] ?? null,
                                'hoa_don'      => [
                                    'id_hd'              => $sc['ID_HD'],
                                    'tong_tien'          => $sc['TONG_TIEN'],
                                    'trang_thai_tt'      => $sc['TRANGTHAI_THANHTOAN'],
                                    'yeu_cau_xac_nhan'   => $sc['YEU_CAU_XAC_NHAN'],
                                ],
                            ];

                            // payload cho modal thanh toán
                            $payPayload = [
                                'id_lichhen'   => $lichId,
                                'id_hd'        => $sc['ID_HD'],
                                'tong_tien'    => $sc['TONG_TIEN'],
                                'da_gui_bang_chung' => $sc['YEU_CAU_XAC_NHAN'] ? true : false,
                            ];
                        ?>
                        <tr class="hover:bg-blue-50 transition duration-150 align-top">
                            <td class="py-3 px-4 border-b"><?= $idx ?></td>

                            <td class="py-3 px-4 border-b text-gray-700">
                                <div class="font-medium"><?= htmlspecialchars($sc['THOI_GIAN_BAT_DAU']) ?></div>
                            </td>

                            <td class="py-3 px-4 border-b">
                                <div class="font-semibold text-gray-800"><?= htmlspecialchars($sc['TEN_DV']) ?></div>
                            </td>

                            <td class="py-3 px-4 border-b text-gray-700">
                                <?= htmlspecialchars($sc['DIA_CHI_HEN']) ?>
                            </td>

                            <td class="py-3 px-4 border-b text-gray-700">
                                <?= htmlspecialchars($sc['TEN_CHI_NHANH'] ?? '—') ?>
                            </td>

                            <td class="py-3 px-4 border-b">
                                <?= renderStatusBadge($sc['TRANGTHAI']) ?>
                            </td>

                            <td class="py-3 px-4 border-b">
                                <?= renderPaymentBadge($sc['TRANGTHAI_THANHTOAN'] ?? null) ?>
                            </td>

                            <td class="py-3 px-4 border-b text-xs text-center space-y-2">

                                <?php if ($isPending): ?>
                                <!-- HỦY LỊCH -->
                                <form method="POST"
                                      onsubmit="return confirm('Bạn chắc chắn muốn hủy lịch hẹn này?')">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="id_lichhen" value="<?= $lichId ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit"
                                        class="w-full inline-block bg-red-600 hover:bg-red-700 text-white font-medium px-3 py-1 rounded-md shadow">
                                        Hủy
                                    </button>
                                </form>
                                <?php endif; ?>

                                <?php if ($isDone): ?>
                                <!-- GỬI / CHỈNH PHẢN HỒI -->
                                <button type="button"
                                        onclick="toggleFeedbackForm(<?= $lichId ?>)"
                                        class="w-full inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-1 rounded-md shadow">
                                    <?= $hasFeedback ? 'Chỉnh sửa phản hồi' : 'Gửi phản hồi' ?>
                                </button>
                                <?php endif; ?>

                                <!-- CHI TIẾT -->
                                <button type="button"
                                        class="w-full inline-block border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-3 py-1 rounded-md shadow-sm"
                                        onclick='openDetailModal(<?= jsSafe($detailPayload) ?>)'>
                                    Chi tiết
                                </button>

                                <?php if ($canPayNow && $sc['ID_HD']): ?>
                                <!-- THANH TOÁN NGAY -->
                                <button type="button"
                                        class="w-full inline-block bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-3 py-1 rounded-md shadow"
                                        onclick='openPayModal(<?= jsSafe($payPayload) ?>)'>
                                    Thanh toán ngay
                                </button>
                                <?php endif; ?>

                            </td>
                        </tr>

                        <?php if ($isDone): ?>
                        <!-- FORM PHẢN HỒI (ẩn/hiện bằng JS) -->
                        <tr id="feedback-form-<?= $lichId ?>" class="hidden">
                            <td colspan="8" class="bg-gray-50 border-b p-4 text-left">
                                <form method="POST"
                                      action="../components/luu_phan_hoi.php"
                                      class="space-y-3 max-w-xl">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="id_dv" value="<?= htmlspecialchars($sc['ID_DV']) ?>">

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                                            Phản hồi về dịch vụ:
                                        </label>
                                        <textarea name="noidung"
                                            rows="3"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Bạn thấy trải nghiệm thế nào?"><?= htmlspecialchars($sc['NOI_DUNG_PH'] ?? '') ?></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                                            Đánh giá:
                                        </label>
                                        <select name="xephang"
                                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <?php
                                                $ratingNow = $sc['XEP_HANG_DV'] ?? 5;
                                                for ($r = 5; $r >= 1; $r--) {
                                                    $label = str_repeat("⭐", $r);
                                                    $sel = ($r == $ratingNow) ? 'selected' : '';
                                                    echo "<option value=\"$r\" $sel>$label</option>";
                                                }
                                            ?>
                                        </select>
                                    </div>

                                    <button type="submit"
                                        class="inline-flex items-center bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-4 py-2 rounded-lg shadow text-sm">
                                        <?= $hasFeedback ? 'Cập nhật phản hồi' : 'Gửi phản hồi' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- =============== MODAL CHI TIẾT LỊCH HẸN =============== -->
    <div id="detailModal" class="fixed inset-0 z-50 hidden items-center justify-center">
        <!-- overlay -->
        <div class="absolute inset-0 bg-black/40" onclick="closeDetailModal()"></div>

        <!-- panel -->
        <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full mx-4 p-6 z-10">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center justify-between">
                Chi tiết lịch hẹn
                <button class="text-gray-400 hover:text-gray-600" onclick="closeDetailModal()">&times;</button>
            </h2>

            <div class="space-y-4 text-sm text-gray-700 max-h-[60vh] overflow-y-auto">

                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Mã lịch hẹn</div>
                    <div id="dm-idlich" class="font-medium text-gray-900"></div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-gray-500 text-xs uppercase font-semibold">Thời gian</div>
                        <div id="dm-time" class="font-medium text-gray-900"></div>
                    </div>
                    <div>
                        <div class="text-gray-500 text-xs uppercase font-semibold">Trạng thái</div>
                        <div id="dm-status" class="font-medium"></div>
                    </div>
                </div>

                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold">Dịch vụ</div>
                    <div id="dm-service" class="font-medium text-gray-900"></div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-gray-500 text-xs uppercase font-semibold">Chi nhánh</div>
                        <div id="dm-branch" class="font-medium text-gray-900"></div>
                    </div>
                    <div>
                        <div class="text-gray-500 text-xs uppercase font-semibold">Địa điểm hẹn</div>
                        <div id="dm-address" class="font-medium text-gray-900"></div>
                    </div>
                </div>

                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold mb-1">Nhân viên được phân công</div>
                    <ul id="dm-staff" class="list-disc list-inside text-gray-800 text-sm space-y-1">
                        <!-- filled by JS -->
                    </ul>
                </div>

                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold mb-1">Thiết bị đi kèm</div>
                    <ul id="dm-devices" class="list-disc list-inside text-gray-800 text-sm space-y-1">
                        <!-- filled by JS -->
                    </ul>
                </div>

                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold mb-1">Báo giá tạm tính</div>
                    <div id="dm-quote-total" class="font-semibold text-blue-700"></div>
                    <pre id="dm-quote-detail" class="bg-gray-100 text-gray-700 rounded-md p-2 text-xs overflow-x-auto whitespace-pre-wrap"></pre>
                </div>

                <div>
                    <div class="text-gray-500 text-xs uppercase font-semibold mb-1">Hóa đơn</div>
                    <div class="text-sm text-gray-800">
                        <div><span class="text-gray-500">Mã hóa đơn:</span> <span id="dm-hd-id" class="font-medium"></span></div>
                        <div><span class="text-gray-500">Tổng tiền:</span> <span id="dm-hd-total" class="font-semibold text-emerald-700"></span></div>
                        <div><span class="text-gray-500">Thanh toán:</span> <span id="dm-hd-status" class="font-medium"></span></div>
                        <div><span class="text-gray-500">Yêu cầu xác nhận:</span> <span id="dm-hd-verify" class="font-medium"></span></div>
                    </div>
                </div>

            </div>

            <div class="mt-6 text-right">
                <button onclick="closeDetailModal()"
                        class="inline-flex items-center justify-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg px-4 py-2 text-sm">
                    Đóng
                </button>
            </div>
        </div>
    </div>

    <!-- =============== MODAL THANH TOÁN =============== -->
    <div id="payModal" class="fixed inset-0 z-50 hidden items-center justify-center">
        <!-- overlay -->
        <div class="absolute inset-0 bg-black/40" onclick="closePayModal()"></div>

        <!-- panel -->
        <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6 z-10">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center justify-between">
                Thanh toán chuyển khoản
                <button class="text-gray-400 hover:text-gray-600" onclick="closePayModal()">&times;</button>
            </h2>

            <!-- Hướng dẫn QR (tĩnh / bạn thay QR bank thật của studio) -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm text-gray-700 space-y-2">
                <p class="font-medium text-gray-900">Bước 1. Quét mã QR / Chuyển khoản đúng số tiền:</p>
                <ul class="list-disc list-inside text-gray-600 text-sm">
                    <li>Ngân hàng: <span class="font-medium text-gray-900">VCB - Stygian Blue</span></li>
                    <li>Nội dung CK: <span class="font-medium text-gray-900">Thanh toan lich hen #<span id="pm-lich-id-inline"></span></span></li>
                    <li>Số tiền: <span class="font-semibold text-emerald-700" id="pm-total"></span></li>
                </ul>

                <div class="bg-white rounded-md border border-gray-300 p-3 text-center">
                    <div class="text-[10px] uppercase text-gray-500 tracking-wide mb-2">QR CODE</div>
                    <div class="w-32 h-32 bg-gray-200 mx-auto rounded-md flex items-center justify-center text-[10px] text-gray-500">
                        QR img here
                    </div>
                </div>

                <p class="text-[13px] text-gray-500 leading-snug">
                    Bước 2. Sau khi chuyển khoản xong, chụp màn hình biên lai (screenshot app ngân hàng).<br>
                    Bước 3. Tải ảnh minh chứng lên đây để yêu cầu xác nhận.
                </p>
            </div>

            <!-- Form upload minh chứng -->
            <form class="mt-4 space-y-4 text-sm"
                  method="POST"
                  action="../components/upload_minh_chung.php"
                  enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="id_hd" id="pm-id-hd">
                <input type="hidden" name="id_lichhen" id="pm-id-lichhen">

                <div>
                    <label class="block font-medium text-gray-700 mb-1">Ảnh minh chứng (jpg/png):</label>
                    <input type="file" name="minhchung" accept="image/*"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-blue-600 file:text-white hover:file:bg-blue-700">
                </div>

                <div>
                    <label class="block font-medium text-gray-700 mb-1">Ghi chú thêm (tuỳ chọn):</label>
                    <input type="text" name="ghichu"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Ví dụ: Em đã chuyển lúc 14:23 qua Vietcombank">
                </div>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg px-4 py-2 text-sm shadow">
                    Gửi minh chứng thanh toán
                </button>
            </form>

            <div class="mt-4 text-[11px] text-gray-500 leading-relaxed">
                Sau khi gửi, trạng thái hóa đơn sẽ chuyển sang
                <span class="font-medium text-gray-800">"Yêu cầu xác nhận"</span>. Bộ phận kế toán sẽ kiểm tra giao dịch và cập nhật sang
                <span class="text-emerald-600 font-semibold">"Đã thanh toán"</span>
                nếu hợp lệ.
            </div>

            <div class="mt-6 text-right">
                <button onclick="closePayModal()"
                        class="inline-flex items-center justify-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg px-4 py-2 text-sm">
                    Đóng
                </button>
            </div>
        </div>
    </div>

    <script>
    function toggleFeedbackForm(id) {
        const el = document.getElementById('feedback-form-' + id);
        if (el) {
            el.classList.toggle('hidden');
        }
    }

    // ===== Detail Modal Logic =====
    const detailModal    = document.getElementById('detailModal');
    const dmIdLich       = document.getElementById('dm-idlich');
    const dmTime         = document.getElementById('dm-time');
    const dmStatus       = document.getElementById('dm-status');
    const dmService      = document.getElementById('dm-service');
    const dmBranch       = document.getElementById('dm-branch');
    const dmAddress      = document.getElementById('dm-address');
    const dmStaff        = document.getElementById('dm-staff');
    const dmDevices      = document.getElementById('dm-devices');
    const dmQuoteTotal   = document.getElementById('dm-quote-total');
    const dmQuoteDetail  = document.getElementById('dm-quote-detail');
    const dmHdId         = document.getElementById('dm-hd-id');
    const dmHdTotal      = document.getElementById('dm-hd-total');
    const dmHdStatus     = document.getElementById('dm-hd-status');
    const dmHdVerify     = document.getElementById('dm-hd-verify');

    function openDetailModal(payload) {
        // payload = {
        //  id_lichhen, thoi_gian, dia_diem, dich_vu, chi_nhanh,
        //  trang_thai, nhan_vien[], thiet_bi[], tam_tinh,
        //  chi_tiet_bg (JSON string), hoa_don{...}
        // }

        dmIdLich.textContent    = '#' + (payload.id_lichhen ?? '');
        dmTime.textContent      = payload.thoi_gian ?? '—';
        dmStatus.textContent    = payload.trang_thai ?? '—';
        dmService.textContent   = payload.dich_vu ?? '—';
        dmBranch.textContent    = payload.chi_nhanh ?? '—';
        dmAddress.textContent   = payload.dia_diem ?? '—';

        // staff list
        dmStaff.innerHTML = '';
        if (payload.nhan_vien && payload.nhan_vien.length) {
            payload.nhan_vien.forEach(st => {
                const li = document.createElement('li');
                li.textContent = (st.HO_TEN || '(chưa rõ)') + (st.CHUYEN_MON ? ` – ${st.CHUYEN_MON}` : '');
                dmStaff.appendChild(li);
            });
        } else {
            const li = document.createElement('li');
            li.textContent = 'Chưa phân công';
            dmStaff.appendChild(li);
        }

        // device list
        dmDevices.innerHTML = '';
        if (payload.thiet_bi && payload.thiet_bi.length) {
            payload.thiet_bi.forEach(dev => {
                const li = document.createElement('li');
                li.textContent = (dev.TEN_TB || 'Thiết bị') + ` × ${dev.SO_LUONG ?? 1}`;
                dmDevices.appendChild(li);
            });
        } else {
            const li = document.createElement('li');
            li.textContent = 'Không có thiết bị đính kèm';
            dmDevices.appendChild(li);
        }

        // quote
        dmQuoteTotal.textContent  = payload.tam_tinh
            ? (payload.tam_tinh + " VND (tạm tính)")
            : "—";
        if (payload.chi_tiet_bg) {
            dmQuoteDetail.textContent = payload.chi_tiet_bg;
        } else {
            dmQuoteDetail.textContent = "Không có chi tiết báo giá.";
        }

        // invoice
        dmHdId.textContent     = payload.hoa_don.id_hd ?? '—';
        dmHdTotal.textContent  = payload.hoa_don.tong_tien
            ? (payload.hoa_don.tong_tien + " VND")
            : "—";
        dmHdStatus.textContent = payload.hoa_don.trang_thai_tt ?? '—';
        dmHdVerify.textContent = payload.hoa_don.yeu_cau_xac_nhan
            ? "Đã gửi minh chứng / chờ xác nhận"
            : "Chưa xác nhận";

        detailModal.classList.remove('hidden');
        detailModal.classList.add('flex');
    }

    function closeDetailModal() {
        detailModal.classList.add('hidden');
        detailModal.classList.remove('flex');
    }

    // ===== Pay Modal Logic =====
    const payModal             = document.getElementById('payModal');
    const pmIdHdInput          = document.getElementById('pm-id-hd');
    const pmIdLichInput        = document.getElementById('pm-id-lichhen');
    const pmLichIdInline       = document.getElementById('pm-lich-id-inline');
    const pmTotal              = document.getElementById('pm-total');

    function openPayModal(payload) {
        // payload = {
        //   id_lichhen, id_hd, tong_tien, da_gui_bang_chung
        // }

        pmIdHdInput.value     = payload.id_hd || '';
        pmIdLichInput.value   = payload.id_lichhen || '';
        pmLichIdInline.textContent = '#' + (payload.id_lichhen || '');
        pmTotal.textContent   = payload.tong_tien
            ? (payload.tong_tien + " VND")
            : "—";

        // nếu da_gui_bang_chung = true => có thể show cảnh báo "đã gửi rồi"
        // (optional)
        payModal.classList.remove('hidden');
        payModal.classList.add('flex');
    }

    function closePayModal() {
        payModal.classList.add('hidden');
        payModal.classList.remove('flex');
    }
    </script>
</body>
</html>
