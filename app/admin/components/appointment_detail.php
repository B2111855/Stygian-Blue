<?php
include '../../database/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';


function getAppointmentDetail($id)
{
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT lh.*, tk.HO_TEN, dv.TEN_DV FROM lich_hen lh JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV WHERE lh.ID_LICHHEN = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
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

function calculateTotalPrice($idLichHen)
{
    global $conn;
    $tongTienDV = 0;
    $tongTienTB = 0;

    $queryDV = "SELECT dv.thoi_gian, dg.DON_GIA FROM lich_hen lh JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV JOIN don_gia_dich_vu dg ON dv.ID_DV = dg.ID_DV WHERE lh.ID_LICHHEN = ?";
    $stmtDV = mysqli_prepare($conn, $queryDV);
    mysqli_stmt_bind_param($stmtDV, 'i', $idLichHen);
    mysqli_stmt_execute($stmtDV);
    $resultDV = mysqli_stmt_get_result($stmtDV);

    if ($rowDV = mysqli_fetch_assoc($resultDV)) {
        $soPhut = (int)$rowDV['thoi_gian'];
        $soGio = $soPhut / 60;
        $tongTienDV = $soGio * $rowDV['DON_GIA'];
    }

    $queryTB = "SELECT dgtb.DON_GIA FROM lich_hen_thiet_bi lhtb JOIN trang_thiet_bi tb ON lhtb.ID_TB = tb.ID_TB JOIN don_gia_trang_thiet_bi dgtb ON tb.ID_TB = dgtb.ID_TB WHERE lhtb.ID_LICHHEN = ?";
    $stmtTB = mysqli_prepare($conn, $queryTB);
    mysqli_stmt_bind_param($stmtTB, 'i', $idLichHen);
    mysqli_stmt_execute($stmtTB);
    $resultTB = mysqli_stmt_get_result($stmtTB);

    while ($rowTB = mysqli_fetch_assoc($resultTB)) {
        $tongTienTB += $rowTB['DON_GIA'];
    }

    return $tongTienDV + $tongTienTB;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment'])) {
    $trangThaiMoi = $_POST['TRANGTHAI'];
    $stmt = mysqli_prepare($conn, "SELECT TRANGTHAI FROM lich_hen WHERE ID_LICHHEN = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $trangThaiCu);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (in_array($trangThaiCu, ['Đã hoàn thành', 'Đã hủy'])) {
        echo "<script>alert('Lịch hẹn đã {$trangThaiCu}, không thể chỉnh sửa.');</script>";
    } elseif ($trangThaiCu === 'Đang chờ' && $trangThaiMoi === 'Đã hoàn thành') {
        echo "<script>alert('Không thể chuyển trực tiếp từ Đang chờ sang Đã hoàn thành.');</script>";
    } elseif ($trangThaiCu === 'Đã xác nhận' && $trangThaiMoi === 'Đã xác nhận') {
        echo "<script>alert('Lịch hẹn đã được xác nhận. Không thể xác nhận lại.');</script>";
    } elseif ($trangThaiCu === 'Đã xác nhận' && $trangThaiMoi === 'Đang chờ') {
        echo "<script>alert('Không thể chuyển từ Đã xác nhận về Đang chờ.');</script>";
    } else {
        $data = [
            'ID_LICHHEN' => $id,
            'THOI_GIAN_BAT_DAU' => $_POST['THOI_GIAN_BAT_DAU'],
            'DIA_CHI_HEN' => $_POST['DIA_CHI_HEN'],
            'TRANGTHAI' => $trangThaiMoi
        ];

        if (updateAppointment($data)) {
            if ($trangThaiMoi === 'Đã xác nhận') {
                $checkStmt = mysqli_prepare($conn, "SELECT 1 FROM hoa_don WHERE ID_LICHHEN = ?");
                mysqli_stmt_bind_param($checkStmt, 'i', $id);
                mysqli_stmt_execute($checkStmt);
                mysqli_stmt_store_result($checkStmt);

                if (mysqli_stmt_num_rows($checkStmt) === 0) {
                    $tongTien = calculateTotalPrice($id);
                    $stmtHD = mysqli_prepare($conn, "INSERT INTO hoa_don (ID_LICHHEN, NGAY_GIO, TONG_TIEN, TRANGTHAI_THANHTOAN) VALUES (?, NOW(), ?, 'Chưa thanh toán')");
                    mysqli_stmt_bind_param($stmtHD, 'id', $id, $tongTien);
                    mysqli_stmt_execute($stmtHD);
                }
                mysqli_stmt_close($checkStmt);

                // Lấy thông tin để gửi email
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

                // Gửi email bằng PHPMailer
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'trongnghiann4911@gmail.com';
                    $mail->Password = 'boyw rfke ahjp trlx'; // App password Gmail
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('trongnghiann4911@gmail.com', 'Stygian Blue Studio');
                    $mail->addAddress($email, $hoTen);
                    $mail->CharSet = 'UTF-8';
                    $mail->isHTML(true);
                    $mail->Subject = '📸 Xác nhận lịch hẹn tại Stygian Blue';

                    $mail->Body = "
                        <h3>Xin chào $hoTen,</h3>
                        <p>Lịch hẹn của bạn đã được <strong>xác nhận</strong> với các thông tin sau:</p>
                        <ul>
                            <li><strong>Dịch vụ:</strong> $tenDV</li>
                            <li><strong>Thời gian:</strong> $thoiGian</li>
                            <li><strong>Địa điểm:</strong> $diaChi</li>
                            <li><strong>Tổng tiền:</strong> <b>" . number_format($tongTien, 0, ',', '.') . " VND</b></li>
                        </ul>
                        <p>Hóa đơn của bạn đã được gửi đến tài khoản</p>
                        <p>Vui lòng có mặt đúng giờ. Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi!</p>
                        <p>— Stygian Blue Team</p>
                    ";
                    $mail->send();
                } catch (Exception $e) {
                    error_log("❌ Gửi email thất bại: {$mail->ErrorInfo}");
                }
            }

            // ✅ Hiển thị thông báo nhưng không redirect
            echo "<script>alert('✅ Cập nhật lịch hẹn thành công!');</script>";
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

<div class="max-w-xl mx-auto bg-white p-8 rounded-xl shadow-xl">
    <h2 class="text-3xl font-extrabold text-indigo-700 mb-6 text-center">📋 Chi tiết lịch hẹn</h2>

    <form method="POST" class="space-y-6">
        <input type="hidden" name="ID_LICHHEN" value="<?= $appointment['ID_LICHHEN'] ?>">

        <!-- Tên dịch vụ -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">📸 Dịch vụ:</label>
            <input type="text" value="<?= htmlspecialchars($appointment['TEN_DV']) ?>"
                class="bg-gray-100 border border-gray-300 rounded-lg w-full px-4 py-2 shadow-sm cursor-not-allowed"
                readonly>
        </div>


        <!-- Thời gian -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">🕒 Thời gian bắt đầu:</label>
            <input type="datetime-local" name="THOI_GIAN_BAT_DAU"
                value="<?= date('Y-m-d\TH:i', strtotime($appointment['THOI_GIAN_BAT_DAU'])) ?>"
                class="border border-gray-300 rounded-lg w-full px-4 py-2 focus:ring-indigo-500 focus:outline-none shadow-sm" required>
        </div>

        <!-- Địa chỉ -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">📍 Địa chỉ hẹn:</label>
            <input type="text" name="DIA_CHI_HEN" value="<?= htmlspecialchars($appointment['DIA_CHI_HEN']) ?>"
                class="border border-gray-300 rounded-lg w-full px-4 py-2 focus:ring-indigo-500 focus:outline-none shadow-sm" required>
        </div>

        <!-- Trạng thái -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">📌 Trạng thái:</label>
            <select name="TRANGTHAI"
                class="border border-gray-300 rounded-lg w-full px-4 py-2 focus:ring-indigo-500 focus:outline-none shadow-sm">
                <option value="Đang chờ" <?= $appointment['TRANGTHAI'] === 'Đang chờ' ? 'selected' : '' ?>>Đang chờ</option>
                <option value="Đã xác nhận" <?= $appointment['TRANGTHAI'] === 'Đã xác nhận' ? 'selected' : '' ?>>Đã xác nhận</option>
                <option value="Đã hoàn thành" <?= $appointment['TRANGTHAI'] === 'Đã hoàn thành' ? 'selected' : '' ?>>Đã hoàn thành</option>
                <option value="Đã hủy" <?= $appointment['TRANGTHAI'] === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
            </select>
        </div>

        <!-- Nút hành động -->
        <div class="flex flex-wrap justify-between items-center gap-4 pt-4">
            <button type="submit" name="update_appointment"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-semibold shadow transition">
                Cập nhật
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
                class="bg-gray-400 hover:bg-gray-500 text-white px-5 py-2 rounded-lg font-semibold shadow transition">
                Quay lại
            </a>

        </div>
    </form>
</div>