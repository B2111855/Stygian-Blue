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
<body class="bg-slate-900 min-h-screen text-white flex items-start justify-center py-10 px-4">

  <div class="w-full max-w-7xl relative">
    <!-- background glowing blobs -->
    <div class="absolute -top-32 -left-24 w-72 h-72 bg-indigo-600/30 blur-[100px] rounded-full pointer-events-none"></div>
    <div class="absolute top-1/3 -right-24 w-72 h-72 bg-cyan-400/20 blur-[110px] rounded-full pointer-events-none"></div>

    <div class="relative bg-slate-800/70 backdrop-blur-xl border border-white/10 rounded-2xl shadow-2xl shadow-indigo-900/40 overflow-hidden">
      <!-- HEADER / PROFILE STRIP -->
      <div class="bg-gradient-to-r from-indigo-600 via-blue-500 to-cyan-400 p-6 md:p-8 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
          <!-- avatar + name + badges -->
          <div class="flex items-start gap-4">
            <div class="shrink-0 w-16 h-16 rounded-xl bg-white/20 ring-2 ring-white/40 flex items-center justify-center font-semibold text-xl">
              <?= strtoupper(substr($hoTen, 0, 1)); ?>
            </div>
            <div>
              <div class="text-2xl font-extrabold leading-tight">
                <?= htmlspecialchars($hoTen); ?>
              </div>

              <div class="text-white/80 text-sm mt-1 flex flex-wrap gap-x-3 gap-y-1">
                <!-- Quyền -->
                <span class="inline-flex items-center gap-1 bg-black/20 rounded-md px-2 py-[2px] leading-none text-xs font-medium">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
                  <?= htmlspecialchars($TEN_QUYEN_view ?? 'Người dùng'); ?>
                </span>

                <!-- Loại nhân viên -->
                <?php if (!empty($LOAI_NV_view)): ?>
                <span class="inline-flex items-center gap-1 bg-black/20 rounded-md px-2 py-[2px] leading-none text-xs font-medium">
                  <?= htmlspecialchars($LOAI_NV_view); ?>
                </span>
                <?php endif; ?>

                <!-- Chi nhánh -->
                <?php if (!empty($TEN_CN_view)): ?>
                <span class="inline-flex items-center gap-1 bg-black/20 rounded-md px-2 py-[2px] leading-none text-xs font-medium">
                  CN: <?= htmlspecialchars($TEN_CN_view); ?>
                </span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- quick info cards -->
          <div class="grid grid-cols-2 gap-4 text-xs md:text-sm">
            <div class="bg-white/10 rounded-lg p-3 min-w-[8rem]">
              <div class="text-white/70">Mã tài khoản</div>
              <div class="font-semibold break-all"><?= htmlspecialchars($idTk); ?></div>
            </div>
            <div class="bg-white/10 rounded-lg p-3 min-w-[8rem]">
              <div class="text-white/70">Số điện thoại</div>
              <div class="font-semibold"><?= htmlspecialchars($sdt); ?></div>
            </div>
            <div class="bg-white/10 rounded-lg p-3 min-w-[8rem]">
              <div class="text-white/70">Email</div>
              <div class="font-semibold break-all"><?= htmlspecialchars($email); ?></div>
            </div>
            <div class="bg-white/10 rounded-lg p-3 min-w-[8rem]">
              <div class="text-white/70">Bảo mật</div>
              <div class="font-semibold flex items-center gap-1">
                🔒 Mật khẩu đã mã hoá
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- BODY CONTENT -->
      <div class="p-6 md:p-8 space-y-8">

        <!-- Toast báo lỗi/thành công -->
        <?php if (isset($error) || isset($success)): ?>
        <div class="grid grid-cols-1">
          <?php if (isset($error)): ?>
            <div class="bg-red-500/20 text-red-300 border border-red-500/40 text-[13px] rounded-lg px-4 py-3 mb-2 shadow-lg shadow-red-900/30">
              <?= $error; ?>
            </div>
          <?php endif; ?>
          <?php if (isset($success)): ?>
            <div class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 text-[13px] rounded-lg px-4 py-3 mb-2 shadow-lg shadow-emerald-900/30">
              <?= $success; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

          <!-- LEFT: SYSTEM INFO (READONLY) -->
          <section class="lg:col-span-1 bg-slate-800/80 rounded-xl border border-white/5 shadow-inner p-5 space-y-5">
            <header class="flex items-start justify-between">
              <div>
                <div class="text-sm text-slate-400 font-medium uppercase tracking-wide">Thông tin hệ thống</div>
                <div class="text-base font-semibold text-white mt-1">Chỉ đọc</div>
                <p class="text-[13px] text-slate-400 leading-snug mt-1">
                  Đây là vai trò và ngữ cảnh làm việc hiện tại của bạn. Không thể tự chỉnh tại đây.
                </p>
              </div>
              <div class="text-[10px] leading-tight text-right text-slate-400">
                Quyền
                <br>
                <span class="font-semibold text-white">
                  <?= htmlspecialchars($TEN_QUYEN_view ?? '—'); ?>
                </span>
              </div>
            </header>

            <div class="space-y-4 text-sm">
              <div>
                <div class="text-slate-400 text-xs mb-1 flex items-center gap-2">
                  <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                  Chi nhánh
                </div>
                <input
                  class="w-full bg-slate-700/60 rounded-lg px-3 py-2 text-white text-[13px] border border-slate-600/60 cursor-not-allowed"
                  value="<?= htmlspecialchars($TEN_CN_view ?? 'Không thuộc chi nhánh'); ?>"
                  readonly
                >
              </div>

              <div>
                <div class="text-slate-400 text-xs mb-1 flex items-center gap-2">
                  <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                  Loại nhân viên / Chức vụ
                </div>
                <input
                  class="w-full bg-slate-700/60 rounded-lg px-3 py-2 text-white text-[13px] border border-slate-600/60 cursor-not-allowed"
                  value="<?php
                    if (!empty($LOAI_NV_view)) {
                      echo htmlspecialchars($LOAI_NV_view . ($CHUYEN_MON_view ? ' · '.$CHUYEN_MON_view : ''));
                    } else {
                      echo 'Không áp dụng';
                    }
                  ?>"
                  readonly
                >
              </div>
            </div>
          </section>

          <!-- RIGHT: PERSONAL INFO FORM -->
          <section class="lg:col-span-2 bg-slate-800/80 rounded-xl border border-white/5 shadow-inner p-5">
            <header class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-6">
              <div>
                <div class="text-sm text-slate-400 font-medium uppercase tracking-wide">Thông tin cá nhân</div>
                <h2 class="text-xl font-semibold text-white leading-tight">Cập nhật hồ sơ</h2>
                <p class="text-[13px] text-slate-400 leading-snug mt-1">
                  Vui lòng giữ thông tin liên hệ chính xác để chúng tôi có thể xác nhận lịch chụp, gửi hóa đơn và hỗ trợ bạn.
                </p>
              </div>
            </header>

            <form method="POST" action="" class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <div class="space-y-1">
                  <label for="id_tk" class="block text-xs font-medium text-slate-400">Mã Tài Khoản</label>
                  <input
                    id="id_tk"
                    name="id_tk"
                    readonly
                    value="<?= htmlspecialchars($idTk); ?>"
                    class="w-full bg-slate-700/60 border border-slate-600/60 rounded-lg px-3 py-2 text-[13px] text-white cursor-not-allowed"
                  >
                </div>

                <div class="space-y-1">
                  <label for="ho_ten" class="block text-xs font-medium text-slate-200">Họ tên</label>
                  <input
                    id="ho_ten"
                    name="full_name"
                    type="text"
                    value="<?= htmlspecialchars($hoTen); ?>"
                    class="w-full bg-slate-900/40 border border-slate-600 rounded-lg px-3 py-2 text-[13px] text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/60"
                  >
                </div>

                <div class="space-y-1">
                  <label for="ngay_sinh" class="block text-xs font-medium text-slate-200">Ngày sinh</label>
                  <input
                    id="ngay_sinh"
                    name="birth_date"
                    type="date"
                    value="<?= htmlspecialchars($ngaySinh); ?>"
                    class="w-full bg-slate-900/40 border border-slate-600 rounded-lg px-3 py-2 text-[13px] text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/60"
                  >
                </div>

                <div class="space-y-1">
                  <label for="dia_chi" class="block text-xs font-medium text-slate-200">Địa chỉ</label>
                  <input
                    id="dia_chi"
                    name="address"
                    type="text"
                    value="<?= htmlspecialchars($diaChi); ?>"
                    class="w-full bg-slate-900/40 border border-slate-600 rounded-lg px-3 py-2 text-[13px] text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/60"
                  >
                </div>

                <div class="space-y-1">
                  <label for="email" class="block text-xs font-medium text-slate-200">Email</label>
                  <input
                    id="email"
                    name="email"
                    type="email"
                    value="<?= htmlspecialchars($email); ?>"
                    class="w-full bg-slate-900/40 border border-slate-600 rounded-lg px-3 py-2 text-[13px] text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/60"
                  >
                </div>

                <div class="space-y-1">
                  <label for="sdt" class="block text-xs font-medium text-slate-200">Số điện thoại</label>
                  <input
                    id="sdt"
                    name="phone"
                    type="text"
                    value="<?= htmlspecialchars($sdt); ?>"
                    class="w-full bg-slate-900/40 border border-slate-600 rounded-lg px-3 py-2 text-[13px] text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/60"
                  >
                </div>
              </div>

              <div class="flex flex-col sm:flex-row justify-end gap-4 pt-4 border-t border-white/5">
                <button
                  type="button"
                  onclick="togglePasswordModal()"
                  class="bg-yellow-500/10 hover:bg-yellow-500/20 border border-yellow-400/40 text-yellow-300 font-semibold rounded-lg px-4 py-2 text-[13px] flex items-center justify-center gap-2"
                >
                  🔐 Đổi mật khẩu
                </button>

                <button
                  type="submit"
                  class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg px-5 py-2 text-[13px] shadow-lg shadow-indigo-900/50 hover:shadow-indigo-700/40 transition"
                >
                  💾 Lưu thay đổi
                </button>
              </div>
            </form>
          </section>

        </div>
      </div>
    </div>

    <!-- MODAL ĐỔI MẬT KHẨU -->
    <div
      id="password-modal"
      class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4"
    >
      <div class="bg-slate-800 w-full max-w-sm rounded-xl border border-white/10 shadow-2xl shadow-black/80 p-6 relative animate-[fadeUp_.25s_ease-out_forwards]">
        <button
          class="absolute top-3 right-3 text-slate-400 hover:text-white text-sm"
          onclick="togglePasswordModal()"
        >✕</button>

        <h2 class="text-lg font-semibold text-white mb-1 flex items-center gap-2">
          <span class="text-yellow-300 text-xl">🔐</span>
          Đổi mật khẩu
        </h2>
        <p class="text-[13px] text-slate-400 leading-snug mb-4">
          Vui lòng nhập mật khẩu hiện tại trước khi đặt mật khẩu mới.
        </p>

        <form method="POST" class="space-y-4">
          <input
            type="password"
            name="current_password"
            placeholder="Mật khẩu hiện tại"
            class="w-full bg-slate-900/40 border border-slate-600 rounded-lg px-3 py-2 text-[13px] text-white focus:outline-none focus:ring-2 focus:ring-yellow-400/60"
          >
          <input
            type="password"
            name="new_password"
            placeholder="Mật khẩu mới (6-22 ký tự)"
            class="w-full bg-slate-900/40 border border-slate-600 rounded-lg px-3 py-2 text-[13px] text-white focus:outline-none focus:ring-2 focus:ring-yellow-400/60"
          >
          <input
            type="password"
            name="confirm_new_password"
            placeholder="Xác nhận mật khẩu mới"
            class="w-full bg-slate-900/40 border border-slate-600 rounded-lg px-3 py-2 text-[13px] text-white focus:outline-none focus:ring-2 focus:ring-yellow-400/60"
          >

          <div class="flex flex-col sm:flex-row justify-end gap-3 pt-2">
            <button
              type="button"
              onclick="togglePasswordModal()"
              class="bg-red-500/10 hover:bg-red-500/20 border border-red-400/40 text-red-300 font-semibold rounded-lg px-4 py-2 text-[13px] text-center"
            >
              Hủy
            </button>

            <button
              type="submit"
              class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg px-4 py-2 text-[13px] shadow-lg shadow-emerald-900/40 text-center"
            >
              Xác nhận đổi
            </button>
          </div>
        </form>
      </div>

      <style>
        @keyframes fadeUp {
          from {opacity:0; transform:translateY(12px) scale(.98)}
          to   {opacity:1; transform:translateY(0) scale(1)}
        }
      </style>
    </div>

    <script>
      function togglePasswordModal() {
        document.getElementById('password-modal').classList.toggle('hidden');
      }
    </script>
  </div>
</body>
<!-- ====================== UI END ====================== -->
