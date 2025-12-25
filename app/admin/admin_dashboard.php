<?php
// Start output buffering to handle header redirects in included components
ob_start();

// KHÔNG cần session_start ở đây nếu require_* đã tự start; nếu chưa, vẫn ok.
require_once '../../middlewares/require_admin.php';
require_once '../../database/config.php';

// CHECK FOR EXPORT REQUEST EARLY - before ANY HTML output
// This allows manage_system_logs.php to send headers for CSV/JSON export
$page = $_GET['page'] ?? '';
$exportFormat = strtolower(trim($_GET['export'] ?? ''));
if ($page === 'system_logs' && in_array($exportFormat, ['csv', 'json'], true)) {
    // Clear output buffer and let manage_system_logs.php handle the export
    ob_end_clean();
    include __DIR__ . '/components/manage_system_logs.php';
    exit; // Stop execution after export
}

include './components/head.php';

// Initialize error/success messages
$errorMessage = null;
$successMessage = null;

// Helper functions for manage_packages handlers
function pkg_table_exists(mysqli $conn, string $table): bool {
  $tbl = $conn->real_escape_string($table);
  $res = $conn->query("SHOW TABLES LIKE '".$tbl."'");
  return $res && $res->num_rows > 0;
}

if (!function_exists('pkg_csrf_token')) {
  function pkg_csrf_token(): string {
    return $_SESSION['csrf_token'] ?? '';
  }
}

if (!function_exists('pkg_require_csrf')) {
  function pkg_require_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $posted = $_POST['csrf'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    return ($posted !== '' && $stored !== '' && hash_equals($stored,$posted));
  }
}

// Include handlers for global modals
include './components/create_promotion_handler.php';

$pageTitle = 'Quản trị hệ thống';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <script>
    // Define toggleModal early so buttons can use it immediately
    function toggleModal(modalId) {
      console.log('toggleModal called with:', modalId);
      console.log('document.readyState:', document.readyState);
      
      // Try to find modal using multiple methods
      let modal = document.getElementById(modalId);
      console.log('modal by getElementById:', modal);
      
      if (!modal) {
        modal = document.querySelector(`#${modalId}`);
        console.log('modal by querySelector:', modal);
      }
      
      if (!modal) {
        // Try searching entire document
        const allDivs = document.querySelectorAll('[id]');
        console.log('All elements with id:', allDivs.length);
        allDivs.forEach(el => {
          if (el.id.includes('Modal')) {
            console.log('Found modal element:', el.id);
          }
        });
      }
      
      if (modal) {
        modal.classList.toggle('hidden');
        console.log('modal classes after toggle:', modal.className);
      } else {
        console.error('Modal still not found:', modalId);
        console.log('HTML length:', document.documentElement.outerHTML.length);
      }
    }
  </script>
</head>

<body class="bg-gray-100 font-sans text-gray-800">
  <div class="flex min-h-screen">

    <!-- Sidebar -->
    <?php include './components/sidebar_admin.php'; ?>

    <!-- Main content -->
    <div class="flex-1 relative overflow-hidden">
      <?php include '../Pages/components/thongbao.php'; ?>
      <img src="https://storage.googleapis.com/a1aa/image/Fuczd4lY6jrjNZZrBoHIu8nkMLFP5TpPG7lwv9u4f24Wjd7JA.jpg"
           alt="Background"
           class="absolute inset-0 w-full h-full object-cover opacity-10" />

      <div class="relative z-10 p-8">
        <div class="bg-white bg-opacity-90 backdrop-blur-md rounded-xl p-6 shadow-xl min-h-[80vh]">

          <div id="notification" class="bg-blue-100 text-blue-700 p-4 rounded mb-6 hidden shadow-md animate-fade-in">
            <strong>🔔 Thông báo:</strong> Bạn có cập nhật mới!
          </div>

          <?php
          // --- Router WHITELIST an toàn ---
          $page = $_GET['page'] ?? 'overview';
          $map  = [
            'overview'         => 'admin_overview.php',
            'finances'          => 'manage_finances.php',
            'finances_v2'       => 'manage_finances_v2.php',
            'report'            => 'trending_report.php',
            'trending_report'   => 'trending_report.php',
            'payments'          => 'admin_confirm_payments.php',
            'hoa_don_chi_tiet'  => 'hoa_don_chi_tiet.php',
            'appointment_detail'=> 'appointment_detail.php',
            'appointments'      => 'manage_appointments.php',
            'employees'         => 'manage_employees.php',
            'deleted_employees' => 'manage_deleted_employees.php',
            'customers'         => 'manage_customers.php',
            'deleted_customers' => 'manage_deleted_customers.php',
            'salaries'          => 'manage_salaries.php',
            'services'          => 'manage_services.php',
            'packages'          => 'manage_packages.php',
            'package_costumes'  => 'manage_costume_packages.php',
            // Trang chỉnh gói trang phục độc lập (không thêm vào sidebar, truy cập qua nút hoặc redirect)
            'edit_costume_package' => 'edit_costume_package.php',
            'branches'          => 'manage_branches.php',
            'assignments'       => 'manage_assignments.php',
            'equipment'         => 'manage_equipment.php',
            'costumes'          => 'manage_costumes.php',
            'costume_rentals'   => 'manage_costume_rentals.php',
            'albums'            => 'manage_albums.php',
            'expenses'          => 'manager_expenses.php',
            'expense_categories'=> 'admin_expense_categories.php',
            'feedback'          => 'feedback.php',
            'selfInfo'          => 'manage_selfInfo.php',
            'system_logs'       => 'manage_system_logs.php',
            'admin_accounts'    => 'manage_admin_accounts.php',
          ];

          if (isset($map[$page])) {
            include __DIR__ . '/components/' . $map[$page];
          } else {
            echo "<h1 class='text-2xl font-bold text-center'>Chào mừng đến với Admin Dashboard 🎯</h1>";
          }
          ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Global Modals for YC and KM Creation (OUTSIDE main container) -->
  <!-- Modal: Tạo Yêu cầu trang phục -->
  <div id="createYCGlobalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 p-4 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen py-8">
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden relative">
        <!-- Close Button -->
        <button type="button" onclick="document.getElementById('createYCGlobalModal').classList.add('hidden')" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white/80 hover:bg-white text-gray-600 hover:text-gray-800 flex items-center justify-center transition z-10">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>

        <!-- Header -->
        <div class="bg-blue-600 text-white p-6">
          <div>
            <h2 class="text-2xl font-bold">Tạo Yêu Cầu</h2>
            <p class="text-white text-sm opacity-90">Thêm yêu cầu trang phục mới</p>

      <script>
        // Preload type options grouped by ID_NHOM for fast client-side filtering
        window.ycLoaisByGroup = <?php
          if (!isset($loaiMap)) {
            // Build once from database if not provided by an include
            $loaiMap = [];
            try {
              if (!isset($conn)) {
                require_once __DIR__ . '/../../database/config.php';
              }
              $loaiRes = $conn->query("SELECT ID_LOAI, ID_NHOM, TEN_LOAI FROM trang_phuc_loai WHERE TRANG_THAI='active'");
              if ($loaiRes) {
                while ($row = $loaiRes->fetch_assoc()) {
                  $gid = $row['ID_NHOM'];
                  if (!isset($loaiMap[$gid])) {
                    $loaiMap[$gid] = [];
                  }
                  $loaiMap[$gid][] = [
                    'id' => $row['ID_LOAI'],
                    'name' => $row['TEN_LOAI'],
                  ];
                }
              }
            } catch (Exception $e) {
              $loaiMap = [];
            }
          }
          echo json_encode($loaiMap, JSON_UNESCAPED_UNICODE);
        ?>;

        // Utility to render filtered options with optional preselection
        function renderLoaiOptions(selectEl, groupId, selectedIds = []) {
          if (!selectEl) return;
          const options = window.ycLoaisByGroup[groupId] || [];
          selectEl.innerHTML = '';
          options.forEach(opt => {
            const optionEl = document.createElement('option');
            optionEl.value = opt.id;
            optionEl.textContent = opt.name;
            if (selectedIds.includes(String(opt.id)) || selectedIds.includes(opt.id)) {
              optionEl.selected = true;
            }
            selectEl.appendChild(optionEl);
          });
        }

        // Render quantity inputs for selected types
        function renderLoaiQtyInputs(selectEl, containerEl, existingQty = {}) {
          if (!selectEl || !containerEl) return;
          const selected = Array.from(selectEl.selectedOptions).map(o => ({ id: o.value, name: o.textContent }));
          containerEl.innerHTML = '';
          if (!selected.length) return;
          selected.forEach(item => {
            const wrap = document.createElement('div');
            wrap.className = 'flex items-center gap-3';

            const label = document.createElement('div');
            label.className = 'flex-1 text-sm';
            label.textContent = item.name;

            const input = document.createElement('input');
            input.type = 'number';
            input.min = '1';
            input.name = `LOAI_QTY[${item.id}]`;
            input.value = existingQty[item.id] ? existingQty[item.id] : '1';
            input.className = 'w-24 px-2 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500';

            wrap.appendChild(label);
            wrap.appendChild(input);
            containerEl.appendChild(wrap);
          });
        }

        document.addEventListener('DOMContentLoaded', () => {
          // Wire create modal: filter types by selected group
          const createGroup = document.getElementById('ycCreateNhom');
          const createLoai = document.getElementById('ycCreateLoai');
          const createQtyWrap = document.getElementById('ycCreateLoaiQty');
          if (createGroup && createLoai) {
            const syncCreate = () => {
              renderLoaiOptions(createLoai, createGroup.value, Array.from(createLoai.selectedOptions).map(o => o.value));
              renderLoaiQtyInputs(createLoai, createQtyWrap, {});
            };
            createGroup.addEventListener('change', syncCreate);
            createLoai.addEventListener('change', () => renderLoaiQtyInputs(createLoai, createQtyWrap, {}));
            if (createGroup.value) {
              syncCreate();
            }
          }

          // Wire edit modal: keep existing selections and filter when group changes
          const editGroup = document.getElementById('ycEditNhom');
          const editLoai = document.getElementById('ycEditLoai');
          if (editGroup && editLoai) {
            const syncEditOptions = () => {
              const selected = Array.from(editLoai.selectedOptions).map(o => o.value);
              renderLoaiOptions(editLoai, editGroup.value, selected);
            };
            editGroup.addEventListener('change', syncEditOptions);
            if (editGroup.value) {
              syncEditOptions();
            }
          }
        });
      </script>
          </div>
        </div>

        <!-- Form Content -->
        <form method="POST" action="admin_dashboard.php?page=packages" class="p-6 space-y-5">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <input type="hidden" name="yc_create_new" value="1">
          <!-- Yêu cầu độc lập: không cần chọn gói -->
          
          <!-- Section 1: Thông tin cơ bản -->
          <div class="space-y-4">
            <div class="flex items-center gap-2 pb-2 border-b border-gray-200">
              <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold">1</div>
              <h3 class="text-sm font-semibold text-gray-700">Thông tin cơ bản</h3>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Nhóm trang phục <span class="text-red-500">*</span>
              </label>
              <select name="ID_NHOM" id="ycCreateNhom" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                <option value="">-- Chọn nhóm --</option>
                <?php
                try {
                  if (!isset($conn)) {
                    require_once __DIR__ . '/../../database/config.php';
                  }
                  $grpResult = $conn->query("SELECT ID_NHOM, TEN_NHOM FROM trang_phuc_nhom WHERE TRANG_THAI='active' ORDER BY TEN_NHOM");
                  if ($grpResult) {
                    while ($g = $grpResult->fetch_assoc()) {
                      echo '<option value="'.$g['ID_NHOM'].'">'.htmlspecialchars($g['TEN_NHOM']).'</option>';
                    }
                  }
                } catch (Exception $e) {
                  // Silent catch to prevent rendering failure
                }
                ?>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Loại trang phục (cùng nhóm, có thể chọn nhiều) <span class="text-red-500">*</span>
              </label>
              <select name="LOAI_IDS[]" id="ycCreateLoai" multiple required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition min-h-[160px]"></select>
              <p class="text-xs text-gray-500 mt-1">Chỉ hiển thị các loại thuộc nhóm đã chọn.</p>
              <div id="ycCreateLoaiQty" class="mt-3 space-y-2 text-sm text-gray-700"></div>
            </div>

          </div>

          <!-- Section 2: Yêu cầu chi tiết -->
          <div class="space-y-4">
            <div class="flex items-center gap-2 pb-2 border-b border-gray-200">
              <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold">2</div>
              <h3 class="text-sm font-semibold text-gray-700">Yêu cầu chi tiết</h3>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Màu sắc
              </label>
              <textarea name="YC_COLOR" rows="2" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none" placeholder="VD: Đỏ, Xanh, Vàng..."></textarea>
              <p class="text-xs text-gray-500 mt-1">Ngăn cách bằng dấu phẩy hoặc xuống dòng</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Kích thước
              </label>
              <textarea name="YC_SIZE" rows="2" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none" placeholder="VD: S, M, L, XL..."></textarea>
              <p class="text-xs text-gray-500 mt-1">Ngăn cách bằng dấu phẩy hoặc xuống dòng</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Phụ kiện
              </label>
              <textarea name="YC_ACCESSORIES" rows="2" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none" placeholder="VD: Thắt lưng, Nơ, Mũ..."></textarea>
              <p class="text-xs text-gray-500 mt-1">Ngăn cách bằng dấu phẩy hoặc xuống dòng</p>
            </div>
          </div>

          <!-- Section 3: Thêm info -->
          <div class="space-y-4">
            <div class="flex items-center gap-2 pb-2 border-b border-gray-200">
              <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold">3</div>
              <h3 class="text-sm font-semibold text-gray-700">Thêm thông tin</h3>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Hạn chốt
              </label>
              <input type="datetime-local" name="YC_DEADLINE" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
              <p class="text-xs text-gray-500 mt-1">Khi nào cần hoàn thành yêu cầu này</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Ghi chú fitting
              </label>
              <textarea name="YC_FITTING" rows="2" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none" placeholder="Ví dụ: Form ôm, Rộng, Dài..."></textarea>
            </div>

            <div class="bg-blue-50 rounded-lg p-3 border border-blue-200">
              <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="YC_BAT_BUOC" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                <div>
                  <p class="text-sm font-medium text-gray-700">Bắt buộc</p>
                  <p class="text-xs text-gray-500">Yêu cầu này không được bỏ qua</p>
                </div>
              </label>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-4 border-t border-gray-200">
            <button type="submit" class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-2.5 rounded-lg transition shadow-md hover:shadow-lg">
              ✓ Tạo yêu cầu
            </button>
            <button type="button" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2.5 rounded-lg transition" onclick="document.getElementById('createYCGlobalModal').classList.add('hidden')">
              ✕ Hủy
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Global Modal: Tạo Khuyến mãi (OUTSIDE main container) -->
  <div id="createKMGlobalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 p-4 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen py-8">
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden relative">
        <!-- Close Button -->
        <button type="button" onclick="document.getElementById('createKMGlobalModal').classList.add('hidden')" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white/80 hover:bg-white text-gray-600 hover:text-gray-800 flex items-center justify-center transition z-10">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>

        <!-- Header -->
        <div class="bg-purple-600 text-white p-6" style="background-color: #9333ea; color: #ffffff;">
          <div>
            <h2 class="text-2xl font-bold" style="color: #ffffff;">Tạo Khuyến mãi</h2>
            <p class="text-white text-sm" style="color: #ffffff; opacity: 1;">Thêm khuyến mãi mới cho các gói dịch vụ</p>
          </div>
        </div>

        <!-- Form Content -->
        <form method="POST" action="admin_dashboard.php?page=packages" class="p-6 space-y-6" id="createKMGlobalForm">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(pkg_csrf_token()); ?>">
          <input type="hidden" name="promo_create_new" value="1">
          
          <!-- Messages -->
          <?php if ($successMessage): ?>
            <div class="bg-green-100 border border-green-300 text-green-700 px-4 py-3 rounded-lg text-sm">
              ✓ <?php echo htmlspecialchars($successMessage); ?>
            </div>
          <?php endif; ?>
          <?php if ($errorMessage): ?>
            <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-lg text-sm">
              ✗ <?php echo htmlspecialchars($errorMessage); ?>
            </div>
          <?php endif; ?>
          
          <!-- Section 1: Thông tin khuyến mãi -->
          <div class="space-y-4">
            <div class="flex items-center gap-2 pb-2 border-b border-gray-200">
              <div class="w-5 h-5 rounded-full bg-purple-600 text-white flex items-center justify-center text-xs font-bold" style="background-color: #9333ea; color: #ffffff;">1</div>
              <h3 class="text-sm font-semibold text-gray-700">Thông tin khuyến mãi</h3>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Tên khuyến mãi
              </label>
              <input type="text" name="TEN_KM" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" placeholder="Ví dụ: Khuyến mãi Tết Nguyên Đán">
              <p class="text-xs text-gray-500 mt-1">Bắt buộc</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Loại khuyến mãi
                </label>
                <select name="KIEU_KM" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                  <option value="">-- Chọn loại --</option>
                  <option value="percent">Phần trăm (%)</option>
                  <option value="fixed">Cố định (₫)</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Giá trị
                </label>
                <div class="flex items-center gap-2">
                  <input type="number" name="GIA_TRI" required min="0" step="0.01" class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" placeholder="0">
                  <span id="kmUnitDisplay" class="text-lg font-semibold text-purple-600 min-w-8 text-center">₫</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Section 2: Thời gian hiệu lực -->
          <div class="space-y-4">
            <div class="flex items-center gap-2 pb-2 border-b border-gray-200">
              <div class="w-5 h-5 rounded-full bg-purple-600 text-white flex items-center justify-center text-xs font-bold" style="background-color: #9333ea; color: #ffffff;">2</div>
              <h3 class="text-sm font-semibold text-gray-700">Thời gian hiệu lực</h3>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Ngày bắt đầu
                </label>
                <input type="datetime-local" name="NGAY_BAT_DAU" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Ngày kết thúc
                </label>
                <input type="datetime-local" name="NGAY_KET_THUC" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
              </div>
            </div>
          </div>

          <!-- Section 3: Mô tả -->
          <div class="space-y-4">
            <div class="flex items-center gap-2 pb-2 border-b border-gray-200">
              <div class="w-5 h-5 rounded-full bg-purple-600 text-white flex items-center justify-center text-xs font-bold" style="background-color: #9333ea; color: #ffffff;">3</div>
              <h3 class="text-sm font-semibold text-gray-700">Thêm thông tin</h3>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Mô tả
              </label>
              <textarea name="MO_TA" rows="2" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition resize-none" placeholder="Ví dụ: Khuyến mãi cho khách hàng VIP..."></textarea>
              <p class="text-xs text-gray-500 mt-1">Không bắt buộc</p>
            </div>

            <div class="flex items-center gap-2">
              <input type="checkbox" name="TRANG_THAI" id="kmStatus" value="active" checked class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-2 focus:ring-purple-500">
              <label for="kmStatus" class="text-sm font-medium text-gray-700">Bật khuyến mãi ngay</label>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-4 border-t border-gray-200">
            <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2.5 rounded-lg transition shadow-md hover:shadow-lg" style="color: #ffffff; background-color: #9333ea;">
              ✓ Tạo khuyến mãi
            </button>
            <button type="button" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2.5 rounded-lg transition" onclick="document.getElementById('createKMGlobalModal').classList.add('hidden')">
              ✕ Hủy
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Scripts and Styles -->
  <script>
    // Update unit display based on promo type
    document.addEventListener('DOMContentLoaded', function() {
      const loaiSelect = document.querySelector('select[name="LOAI_KHUYEN_MAI"]');
      if (loaiSelect) {
        loaiSelect.addEventListener('change', function() {
          const display = document.getElementById('kmUnitDisplay');
          if (display) {
            display.textContent = this.value === 'percent' ? '%' : '₫';
          }
        });
      }
      
      // Setup datetime picker
      document.querySelectorAll('input[type="datetime-local"]').forEach(input => {
        input.addEventListener('click', function () { 
          if (this.showPicker) {
            this.showPicker();
          }
        });
      });
    });
  </script>

  <style>
    @keyframes fade-in {
      from { opacity:0; transform: translateY(10px); }
      to   { opacity:1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fade-in 0.4s ease-out both; }
  </style>
</body>
</html>
