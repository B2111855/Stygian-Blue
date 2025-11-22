<?php
include '../../database/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';

$errors = [];
$successMessage = '';
$statusTransitions = [
    'Đang chờ' => ['Đang chờ', 'Đã xác nhận', 'Đã hủy'],
    'Đã xác nhận' => ['Đã xác nhận', 'Đã hoàn thành', 'Đã hủy'],
    'Đã hoàn thành' => ['Đã hoàn thành'],
    'Đã hủy' => ['Đã hủy']
];
$statusOptions = ['Đang chờ', 'Đã xác nhận', 'Đã hoàn thành', 'Đã hủy'];


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


function getAppointmentDetail($id)
{
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT lh.*, tk.HO_TEN, tk.EMAIL, tk.SDT, tk.DIA_CHI, tk.NGAY_SINH, dv.TEN_DV, cn.TEN_CN, cn.DIA_CHI_CN, cn.SDT_CN FROM lich_hen lh JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV LEFT JOIN chi_nhanh cn ON lh.ID_CHINHANH = cn.ID_CN WHERE lh.ID_LICHHEN = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    if ($result) {
        mysqli_free_result($result);
    }
    return $data;
}

function updateAppointment($data)
{
    global $conn;
    $query = "UPDATE lich_hen SET THOI_GIAN_BAT_DAU = ?, DIA_CHI_HEN = ?, TRANGTHAI = ? WHERE ID_LICHHEN = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'sssi', $data['THOI_GIAN_BAT_DAU'], $data['DIA_CHI_HEN'], $data['TRANGTHAI'], $data['ID_LICHHEN']);
    return mysqli_stmt_execute($stmt);
}

function deleteAppointment($id)
{
    global $conn;
    $stmt = mysqli_prepare($conn, "DELETE FROM lich_hen WHERE ID_LICHHEN = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    return mysqli_stmt_execute($stmt);
}

function getInvoicesByAppointment($id)
{
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT ID_HD, NGAY_GIO, TONG_TIEN, TRANGTHAI_THANHTOAN, PHUONGTHUC_THANHTOAN FROM hoa_don WHERE ID_LICHHEN = ? ORDER BY NGAY_GIO DESC");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
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

    $queryDV = "
        SELECT dv.thoi_gian,
               (
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
                $soPhut = (int)$rowDV['thoi_gian'];
                $soGio = $soPhut / 60;
                $donGia = isset($rowDV['DON_GIA']) ? (float)$rowDV['DON_GIA'] : 0;
                $tongTienDV = $soGio * $donGia;
            }
            if ($resultDV) {
                mysqli_free_result($resultDV);
            }
        } else {
            error_log('Không thể thực thi truy vấn giá dịch vụ: '.mysqli_stmt_error($stmtDV));
        }
        mysqli_stmt_close($stmtDV);
    } else {
        error_log('Không thể chuẩn bị truy vấn giá dịch vụ: '.mysqli_error($conn));
    }

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
    echo "<div class='text-red-600 font-bold'>Không tìm thấy thông tin lịch hẹn.</div>";
    exit;
}

$invoices = getInvoicesByAppointment($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment'])) {
    $currentStatus = $appointment['TRANGTHAI'];
    $newStatus = $_POST['TRANGTHAI'] ?? $currentStatus;
    $thoiGianInput = trim($_POST['THOI_GIAN_BAT_DAU'] ?? '');
    $diaChiInput = trim($_POST['DIA_CHI_HEN'] ?? '');
    $thoiGianDate = $thoiGianInput !== '' ? DateTime::createFromFormat('Y-m-d\TH:i', $thoiGianInput) : null;
    $now = new DateTime('now');
    $allowedTargets = $statusTransitions[$currentStatus] ?? [$currentStatus];

    if (!in_array($newStatus, $statusOptions, true)) {
        $errors[] = 'Trạng thái được gửi không hợp lệ.';
    } elseif (!in_array($newStatus, $allowedTargets, true)) {
        $errors[] = "Không thể chuyển từ {$currentStatus} sang {$newStatus}.";
    }

    if (in_array($currentStatus, ['Đã hoàn thành', 'Đã hủy'], true) && $newStatus !== $currentStatus) {
        $errors[] = "Lịch hẹn đã {$currentStatus}, không thể chỉnh sửa thêm.";
    }

    if ($thoiGianInput === '') {
        $errors[] = 'Thời gian bắt đầu là bắt buộc.';
    } elseif (!$thoiGianDate) {
        $errors[] = 'Định dạng thời gian bắt đầu không hợp lệ.';
    } elseif (in_array($currentStatus, ['Đang chờ', 'Đã xác nhận'], true) && $thoiGianDate < $now) {
        $errors[] = 'Vui lòng chọn thời gian bắt đầu lớn hơn thời điểm hiện tại.';
    }

    if ($diaChiInput === '') {
        $errors[] = 'Địa chỉ hẹn không được để trống.';
    }

    if (empty($errors)) {
        $payload = [
            'ID_LICHHEN' => $id,
            'THOI_GIAN_BAT_DAU' => $thoiGianDate ? $thoiGianDate->format('Y-m-d H:i:s') : $appointment['THOI_GIAN_BAT_DAU'],
            'DIA_CHI_HEN' => $diaChiInput,
            'TRANGTHAI' => $newStatus
        ];

        if (updateAppointment($payload)) {
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
                    $mail->Subject = 'Xác nhận lịch hẹn tại Stygian Blue';

                    $mail->Body = "
                        <h3>Xin chào $hoTen,</h3>
                        <p>Lịch hẹn của bạn đã được <strong>xác nhận</strong> với các thông tin sau:</p>
                        <ul>
                            <li><strong>Dịch vụ:</strong> $tenDV</li>
                            <li><strong>Thời gian:</strong> $thoiGian</li>
                            <li><strong>Địa điểm:</strong> $diaChi</li>
                            <li><strong>Tổng tiền:</strong> <b>" . number_format($tongTien, 0, ',', '.') . " VND</b></li>
                        </ul>
                        <p>Hóa đơn của bạn đã được tạo trong hệ thống. Vui lòng kiểm tra email hoặc liên hệ đội ngũ hỗ trợ khi cần.</p>
                        <p>Cảm ơn bạn đã đồng hành cùng Stygian Blue!</p>
                    ";
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Gửi email xác nhận thất bại: {$mail->ErrorInfo}");
                }
            }

            $successMessage = 'Cập nhật lịch hẹn thành công.';
            $appointment = getAppointmentDetail($id);
            $invoices = getInvoicesByAppointment($id);
        } else {
            $errors[] = 'Không thể cập nhật lịch hẹn. Vui lòng thử lại.';
        }
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
    default => 'bg-gray-100 text-gray-800',
};
$currentAllowed = $statusTransitions[$currentStatus] ?? [$currentStatus];
?>

<div class="max-w-6xl mx-auto">
    <div class="bg-white p-8 rounded-2xl shadow-xl">
        <h2 class="text-3xl font-extrabold text-gray-900 mb-6 text-center">Chi tiết lịch hẹn</h2>

        <?php if ($successMessage): ?>
            <div class="mb-6 border border-green-200 bg-green-50 text-green-800 px-4 py-3 rounded-lg text-sm font-medium">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="mb-6 border border-red-200 bg-red-50 text-red-800 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="grid gap-6 lg:grid-cols-3">
            <form method="POST" class="bg-gray-50 border border-gray-100 rounded-2xl p-6 space-y-6 lg:col-span-2">
                <input type="hidden" name="ID_LICHHEN" value="<?= $appointment['ID_LICHHEN'] ?>">

                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Mã lịch hẹn</p>
                        <p class="text-2xl font-semibold text-gray-900">#<?= $appointment['ID_LICHHEN'] ?></p>
                    </div>
                    <span class="px-4 py-1 rounded-full text-sm font-semibold <?= $statusBadgeClass ?>">
                        <?= htmlspecialchars($currentStatus) ?>
                    </span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dịch vụ</label>
                    <input type="text" value="<?= htmlspecialchars($appointment['TEN_DV']) ?>"
                        class="bg-white border border-gray-200 rounded-lg w-full px-4 py-2 shadow-sm" readonly>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Thời gian bắt đầu</label>
                    <input type="datetime-local" name="THOI_GIAN_BAT_DAU"
                        value="<?= date('Y-m-d\TH:i', strtotime($appointment['THOI_GIAN_BAT_DAU'])) ?>"
                        class="border border-gray-300 rounded-lg w-full px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Địa chỉ hẹn</label>
                    <textarea name="DIA_CHI_HEN" rows="3"
                        class="border border-gray-300 rounded-lg w-full px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
                        required><?= htmlspecialchars($appointment['DIA_CHI_HEN']) ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái (chỉ tiến về phía trước)</label>
                    <div class="flex flex-wrap gap-3">
                        <?php foreach ($statusOptions as $option):
                            $isActive = $option === $currentStatus;
                            $isAllowed = in_array($option, $currentAllowed, true);
                            $baseClass = 'px-4 py-2 rounded-lg border text-sm font-semibold';
                            $stateClass = $isActive
                                ? 'bg-indigo-600 text-white border-indigo-600'
                                : ($isAllowed ? 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400'
                                    : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed opacity-60');
                        ?>
                            <label class="inline-flex items-center gap-2 <?= $baseClass ?> <?= $stateClass ?>">
                                <input type="radio" name="TRANGTHAI" value="<?= htmlspecialchars($option) ?>"
                                    class="sr-only"
                                    <?= $isActive ? 'checked' : '' ?>
                                    <?= !$isAllowed && !$isActive ? 'disabled' : '' ?>>
                                <span><?= htmlspecialchars($option) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Đang chờ → Đã xác nhận → Đã hoàn thành. Có thể hủy khi chưa hoàn tất.</p>
                </div>

                <div class="flex flex-wrap items-center gap-4 pt-2">
                    <button type="submit" name="update_appointment"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-semibold shadow transition">
                        Lưu thay đổi
                    </button>

                    <button type="submit" name="delete_appointment"
                        onclick="return confirm('Bạn có chắc muốn xóa lịch hẹn này?');"
                        class="bg-red-500 hover:bg-red-600 text-white px-5 py-2 rounded-lg font-semibold shadow transition">
                        Xóa lịch hẹn
                    </button>

                    <?php
                    $quayLaiURL = "#";
                    if (isset($_SESSION['ID_QUYEN'])) {
                        if ($_SESSION['ID_QUYEN'] == 1) {
                            $quayLaiURL = "http://localhost:8080/stygianblue/app/admin/admin_dashboard.php?page=appointments";
                        } elseif ($_SESSION['ID_QUYEN'] == 2) {
                            $quayLaiURL = "http://localhost:8080/stygianblue/app/admin/staff_dashboard.php?page=staff_appoinments";
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
                <div class="bg-gray-50 border border-gray-100 rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Thông tin người đặt</h3>
                    <dl class="space-y-3 text-sm text-gray-700">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Họ tên</dt>
                            <dd class="font-medium text-gray-900"><?= htmlspecialchars($appointment['HO_TEN']) ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Email</dt>
                            <dd class="font-medium text-gray-900"><?= htmlspecialchars($appointment['EMAIL'] ?? 'Chưa cập nhật') ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Số điện thoại</dt>
                            <dd class="font-medium text-gray-900"><?= htmlspecialchars($appointment['SDT'] ?? 'Chưa cập nhật') ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Ngày sinh</dt>
                            <dd class="font-medium text-gray-900">
                                <?= isset($appointment['NGAY_SINH']) && $appointment['NGAY_SINH'] ? date('d/m/Y', strtotime($appointment['NGAY_SINH'])) : 'Chưa cập nhật' ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Địa chỉ</dt>
                            <dd class="font-medium text-gray-900"><?= htmlspecialchars($appointment['DIA_CHI'] ?? 'Chưa cập nhật') ?></dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-gray-50 border border-gray-100 rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Hóa đơn liên quan</h3>
                    <?php if (empty($invoices)): ?>
                        <p class="text-sm text-gray-500">Chưa có hóa đơn nào cho lịch hẹn này.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-left">
                                <thead>
                                    <tr class="text-gray-500 uppercase text-xs">
                                        <th class="py-2 pr-4">Mã hóa đơn</th>
                                        <th class="py-2 pr-4">Ngày tạo</th>
                                        <th class="py-2 pr-4">Tổng tiền</th>
                                        <th class="py-2 pr-4">Thanh toán</th>
                                        <th class="py-2 pr-4">Phương thức</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td class="py-2 pr-4 font-semibold text-gray-900">#<?= $invoice['ID_HD'] ?></td>
                                            <td class="py-2 pr-4 text-gray-700">
                                                <?= $invoice['NGAY_GIO'] ? date('d/m/Y H:i', strtotime($invoice['NGAY_GIO'])) : '-' ?>
                                            </td>
                                            <td class="py-2 pr-4 text-gray-900 font-semibold">
                                                <?= number_format((float)$invoice['TONG_TIEN'], 0, ',', '.') ?> VND
                                            </td>
                                            <td class="py-2 pr-4 text-gray-700"><?= htmlspecialchars($invoice['TRANGTHAI_THANHTOAN']) ?></td>
                                            <td class="py-2 pr-4 text-gray-700"><?= htmlspecialchars($invoice['PHUONGTHUC_THANHTOAN']) ?></td>
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