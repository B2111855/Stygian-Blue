<?php
include '../../database/config.php';
include_once './components/get_selfInfo.php';

// ======================================================
// 1. Lấy thông tin user hiện tại
// ======================================================
$userInfo = getUserInfo($conn);

if (!$userInfo) {
    die("Không thể tải thông tin người dùng. Vui lòng đăng nhập lại.");
}

// Map về biến quen thuộc để tránh phải sửa nhiều ở HTML
$userId       = $userInfo['ID_TK'];
$idTk         = $userInfo['ID_TK'];
$hoTen        = $userInfo['HO_TEN'];
$ngaySinh     = $userInfo['NGAY_SINH'];
$diaChi       = $userInfo['DIA_CHI'];
$email        = $userInfo['EMAIL'];
$sdt          = $userInfo['SDT'];

$TEN_QUYEN_view    = $userInfo['TEN_QUYEN'];    // vd: 'Admin', 'Nhân viên', 'Khách hàng'
$LOAI_NV_view      = $userInfo['LOAI_NV'];      // vd: 'quan_ly', 'chuyen_trach'
$CHUYEN_MON_view   = $userInfo['CHUYEN_MON'];   // vd: 'Quản lý chi nhánh'
$TEN_CN_view       = $userInfo['TEN_CN'];       // vd: 'Chi nhánh Cần Thơ'

// ======================================================
// 2. Nếu form POST (cập nhật thông tin cá nhân - KHÔNG phải đổi mật khẩu)
//    Điều kiện: form không gửi current_password
// ======================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['current_password'])) {
    $inputFullName  = trim($_POST['full_name'] ?? '');
    $inputBirthDate = trim($_POST['birth_date'] ?? '');
    $inputAddress   = trim($_POST['address'] ?? '');
    $inputEmail     = trim($_POST['email'] ?? '');
    $inputPhone     = trim($_POST['phone'] ?? '');

    if (empty($inputFullName) || empty($inputEmail) || empty($inputPhone)) {
        $error = "Vui lòng điền đầy đủ thông tin.";
    } elseif (!filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Địa chỉ email không hợp lệ.";
    } elseif (!preg_match('/^[0-9]{10,12}$/', $inputPhone)) {
        $error = "Số điện thoại không hợp lệ.";
    } elseif (empty($userId)) {
        $error = "Lỗi: Không tìm thấy ID_TK người dùng.";
    } else {
        $updateStmt = $conn->prepare("
            UPDATE tai_khoan 
            SET HO_TEN = ?, NGAY_SINH = ?, DIA_CHI = ?, EMAIL = ?, SDT = ?
            WHERE ID_TK = ?
        ");
        if ($updateStmt) {
            $updateStmt->bind_param(
                "ssssss",
                $inputFullName,
                $inputBirthDate,
                $inputAddress,
                $inputEmail,
                $inputPhone,
                $userId
            );
            if ($updateStmt->execute()) {
                $success = "Cập nhật thông tin thành công.";
                // Cập nhật lại biến hiển thị sau khi lưu thành công
                $hoTen    = $inputFullName;
                $ngaySinh = $inputBirthDate;
                $diaChi   = $inputAddress;
                $email    = $inputEmail;
                $sdt      = $inputPhone;
            } else {
                $error = "Lỗi khi cập nhật thông tin: " . $updateStmt->error;
            }
            $updateStmt->close();
        } else {
            $error = "Không thể chuẩn bị truy vấn cập nhật.";
        }
    }
}

// ======================================================
// 3. Chuẩn bị cho đổi mật khẩu
//    - Lấy hash mật khẩu hiện tại để verify
// ======================================================
$hashedPassword = null;
$stmtPwd = $conn->prepare("SELECT MAT_KHAU FROM tai_khoan WHERE ID_TK = ?");
if ($stmtPwd) {
    $stmtPwd->bind_param("s", $userId);
    $stmtPwd->execute();
    $resultPwd = $stmtPwd->get_result();
    if ($resultPwd && $resultPwd->num_rows > 0) {
        $rowPwd = $resultPwd->fetch_assoc();
        $hashedPassword = $rowPwd['MAT_KHAU'];
    }
    $stmtPwd->close();
}

// ======================================================
// 4. Nếu form POST đổi mật khẩu
//    Điều kiện: form gửi current_password
// ======================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['current_password'])) {
    $currentPassword     = trim($_POST['current_password'] ?? '');
    $newPassword         = trim($_POST['new_password'] ?? '');
    $confirmNewPassword  = trim($_POST['confirm_new_password'] ?? '');

    if (empty($currentPassword) || empty($newPassword) || empty($confirmNewPassword)) {
        $error = "Vui lòng điền đầy đủ thông tin.";
    } elseif ($newPassword !== $confirmNewPassword) {
        $error = "Mật khẩu mới không khớp.";
    } elseif (strlen($newPassword) < 6 || strlen($newPassword) > 22) {
        $error = "Mật khẩu mới phải từ 6 đến 22 ký tự.";
    } elseif (!$hashedPassword) {
        $error = "Không thể xác thực tài khoản.";
    } else {
        if (password_verify($currentPassword, $hashedPassword)) {
            $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $updatePwdStmt = $conn->prepare("
                UPDATE tai_khoan 
                SET MAT_KHAU = ? 
                WHERE ID_TK = ?
            ");
            if ($updatePwdStmt) {
                $updatePwdStmt->bind_param("ss", $hashedNewPassword, $userId);
                if ($updatePwdStmt->execute()) {
                    $success = "Đổi mật khẩu thành công.";
                } else {
                    $error = "Lỗi khi đổi mật khẩu: " . $updatePwdStmt->error;
                }
                $updatePwdStmt->close();
            } else {
                $error = "Không thể chuẩn bị truy vấn đổi mật khẩu.";
            }
        } else {
            $error = "Mật khẩu hiện tại không đúng.";
        }
    }
}
?>

<!-- ====================== UI START ====================== -->
<div class="text-slate-800">
  <div class="mx-auto max-w-6xl px-4 py-6">
    <div class="space-y-8">
      <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-6 border-b border-slate-200 px-6 py-8 md:flex-row md:items-center md:justify-between">
          <div class="flex items-start gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-xl font-semibold text-indigo-600">
              <?= strtoupper(substr($hoTen, 0, 1)); ?>
            </div>
            <div>
              <h1 class="text-2xl font-semibold text-slate-900"><?= htmlspecialchars($hoTen); ?></h1>
              <p class="mt-1 text-sm text-slate-500">Thông tin được đồng bộ với hệ thống quản trị.</p>
              <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                <span class="rounded-full bg-slate-100 px-3 py-1"><?= htmlspecialchars($TEN_QUYEN_view ?? 'Người dùng'); ?></span>
                <?php if (!empty($LOAI_NV_view)): ?>
                  <span class="rounded-full bg-slate-100 px-3 py-1"><?= htmlspecialchars($LOAI_NV_view); ?></span>
                <?php endif; ?>
                <?php if (!empty($TEN_CN_view)): ?>
                  <span class="rounded-full bg-slate-100 px-3 py-1">Chi nhánh: <?= htmlspecialchars($TEN_CN_view); ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <p class="text-xs uppercase tracking-wide text-slate-500">Mã tài khoản</p>
              <p class="mt-1 font-semibold text-slate-900 whitespace-nowrap" title="<?= htmlspecialchars($idTk); ?>"><?= htmlspecialchars($idTk); ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <p class="text-xs uppercase tracking-wide text-slate-500">Số điện thoại</p>
              <p class="mt-1 font-semibold text-slate-900"><?= htmlspecialchars($sdt); ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <p class="text-xs uppercase tracking-wide text-slate-500">Email</p>
              <p class="mt-1 font-semibold text-slate-900 whitespace-nowrap" title="<?= htmlspecialchars($email); ?>"><?= htmlspecialchars($email); ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
              <p class="text-xs uppercase tracking-wide text-slate-500">Bảo mật</p>
              <p class="mt-1 font-semibold text-slate-900 whitespace-nowrap">Mật khẩu đã mã hoá</p>
            </div>
          </div>
        </div>

        <?php if (isset($error) || isset($success)): ?>
          <div class="px-6 pt-6">
            <?php if (isset($error)): ?>
              <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-600">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
              </div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
              <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-600">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="grid gap-8 px-6 pb-8 pt-6 lg:grid-cols-3">
          <section class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-6 text-sm">
            <h2 class="text-sm font-semibold text-slate-700">Ngữ cảnh làm việc</h2>
            <p class="mt-2 text-xs text-slate-500">Các thông tin này được thiết lập bởi bộ phận quản trị và không thể chỉnh sửa tại đây.</p>
            <div class="mt-5 space-y-4">
              <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Chi nhánh</p>
                <input value="<?= htmlspecialchars($TEN_CN_view ?? 'Không thuộc chi nhánh'); ?>" readonly class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" />
              </div>
              <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Loại nhân viên / Chuyên môn</p>
                <input value="<?php
                    if (!empty($LOAI_NV_view)) {
                        echo htmlspecialchars($LOAI_NV_view . ($CHUYEN_MON_view ? ' · ' . $CHUYEN_MON_view : ''));
                    } else {
                        echo 'Không áp dụng';
                    }
                ?>" readonly class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" />
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-slate-200 bg-white px-5 py-6 lg:col-span-2">
            <div class="flex flex-col gap-2">
              <h2 class="text-lg font-semibold text-slate-800">Cập nhật thông tin liên hệ</h2>
              <p class="text-sm text-slate-500">Giữ thông tin chính xác để chúng tôi có thể hỗ trợ bạn nhanh chóng khi cần.</p>
            </div>
            <form method="POST" action="" class="mt-6 space-y-6">
              <div class="grid gap-5 md:grid-cols-2">
                <div>
                  <label for="id_tk" class="text-xs font-medium uppercase tracking-wide text-slate-500">Mã tài khoản</label>
                  <input id="id_tk" name="id_tk" readonly value="<?= htmlspecialchars($idTk); ?>" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600" />
                </div>
                <div>
                  <label for="ho_ten" class="text-xs font-medium uppercase tracking-wide text-slate-500">Họ tên</label>
                  <input id="ho_ten" name="full_name" type="text" value="<?= htmlspecialchars($hoTen); ?>" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                </div>
                <div>
                  <label for="ngay_sinh" class="text-xs font-medium uppercase tracking-wide text-slate-500">Ngày sinh</label>
                  <input id="ngay_sinh" name="birth_date" type="date" value="<?= htmlspecialchars($ngaySinh); ?>" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                </div>
                <div>
                  <label for="dia_chi" class="text-xs font-medium uppercase tracking-wide text-slate-500">Địa chỉ</label>
                  <input id="dia_chi" name="address" type="text" value="<?= htmlspecialchars($diaChi); ?>" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                </div>
                <div>
                  <label for="email" class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</label>
                  <input id="email" name="email" type="email" value="<?= htmlspecialchars($email); ?>" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                </div>
                <div>
                  <label for="sdt" class="text-xs font-medium uppercase tracking-wide text-slate-500">Số điện thoại</label>
                  <input id="sdt" name="phone" type="text" value="<?= htmlspecialchars($sdt); ?>" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                </div>
              </div>
              <div class="flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end">
                <button type="button" onclick="togglePasswordModal()" class="rounded-lg border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:text-indigo-600">Đổi mật khẩu</button>
                <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">Lưu thay đổi</button>
              </div>
            </form>
          </section>
        </div>
      </section>

      <div id="password-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-lg">
          <button class="float-right text-sm text-slate-400 transition hover:text-slate-600" onclick="togglePasswordModal()">Đóng</button>
          <h2 class="mt-1 text-lg font-semibold text-slate-800">Đổi mật khẩu</h2>
          <p class="mt-2 text-sm text-slate-500">Nhập mật khẩu hiện tại và đặt mật khẩu mới an toàn hơn.</p>
          <form method="POST" class="mt-5 space-y-4">
            <input type="password" name="current_password" placeholder="Mật khẩu hiện tại" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
            <input type="password" name="new_password" placeholder="Mật khẩu mới (6-22 ký tự)" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
            <input type="password" name="confirm_new_password" placeholder="Xác nhận mật khẩu mới" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
            <div class="flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end">
              <button type="button" onclick="togglePasswordModal()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-rose-200 hover:text-rose-600">Hủy</button>
              <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500">Xác nhận đổi</button>
            </div>
          </form>
        </div>
      </div>

      <style>
        @keyframes fade-in {from {opacity: 0; transform: translateY(12px);} to {opacity: 1; transform: translateY(0);} }
        .fade-in {animation: fade-in .4s ease-out both;}
      </style>

      <script>
        function togglePasswordModal() {
          document.getElementById('password-modal').classList.toggle('hidden');
        }
      </script>
    </div>
  </div>
</div>
<!-- ====================== UI END ====================== -->
