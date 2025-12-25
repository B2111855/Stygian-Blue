<?php
/**
 * Invoice Details - API Version
 * Tải chi tiết hóa đơn từ database
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../../database/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_GET['id_hd'])) {
    echo "<script>alert('Thiếu mã hóa đơn.'); history.back();</script>";
    exit;
}

$id_hd = (int)$_GET['id_hd'];
$backUrl = 'admin_dashboard.php?page=payments';
$source = $_GET['source'] ?? 'admin';

// Kiểm tra nếu được gọi từ manager_dashboard
if ($source === 'manager') {
    $backUrl = 'manager_dashboard.php?page=invoices';
}

// Fetch invoice data from database
$stmt = $conn->prepare("
    SELECT 
        hd.ID_HD, hd.NGAY_GIO, hd.TRANGTHAI_THANHTOAN, hd.PHUONGTHUC_THANHTOAN, hd.TONG_TIEN,
        tt.TRANG_THAI AS VNPAY_TRANG_THAI, tt.MA_THAM_CHIEU AS VNPAY_MA_THAM_CHIEU, tt.CREATED_AT AS VNPAY_UPDATED_AT,
        kh.HO_TEN, kh.EMAIL, kh.SDT,
        lh.ID_LICHHEN, dv.TEN_DV, dv.thoi_gian,
        ttp.ID_TTP, ttp.NGAY_NHAN, ttp.NGAY_TRA_DK, ttp.NGAY_TRA_TT, ttp.TRANG_THAI AS TTP_TRANGTHAI,
        ttp.TIEN_COC, ttp.TONG_TIEN_DU_KIEN, ttp.TONG_TIEN_THUC_TE
    FROM hoa_don hd
    LEFT JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN
    LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
    LEFT JOIN don_thue_trang_phuc ttp ON hd.ID_TTP = ttp.ID_TTP
    LEFT JOIN tai_khoan kh ON COALESCE(lh.ID_TK, ttp.ID_TK) = kh.ID_TK
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
    WHERE hd.ID_HD = ?
");

if (!$stmt) {
    echo "<script>alert('Không thể tải dữ liệu hóa đơn.'); history.back();</script>";
    exit;
}

$stmt->bind_param('i', $id_hd);
$stmt->execute();
$invoice_result = $stmt->get_result();
$invoice_data = $invoice_result->fetch_assoc();
$stmt->close();

if (!$invoice_data) {
    echo "<script>alert('Không tìm thấy hóa đơn.'); history.back();</script>";
    exit;
}

// Fetch line items
$line_items = [];
$line_stmt = $conn->prepare("SELECT MO_TA, SO_LUONG, DON_GIA, THANH_TIEN FROM chi_tiet_hoa_don WHERE ID_HD = ? ORDER BY ID_CTHD ASC");
if ($line_stmt) {
    $line_stmt->bind_param('i', $id_hd);
    $line_stmt->execute();
    $line_result = $line_stmt->get_result();
    while ($row = $line_result->fetch_assoc()) {
        $line_items[] = $row;
    }
    $line_stmt->close();
}

// Fetch rental items if it's a rental invoice
$rental_items = [];
if (!empty($invoice_data['ID_TTP'])) {
    $rental_stmt = $conn->prepare("
        SELECT tp.TEN_TP, dttp.SO_LUONG, dttp.DON_GIA_AP_DUNG, (dttp.SO_LUONG * dttp.DON_GIA_AP_DUNG) as THANH_TIEN
        FROM chi_tiet_don_thue dttp
        JOIN trang_phuc tp ON dttp.ID_TP = tp.ID_TP
        WHERE dttp.ID_TTP = ?
        ORDER BY dttp.ID_CTHD ASC
    ");
    if ($rental_stmt) {
        $rental_stmt->bind_param('i', $invoice_data['ID_TTP']);
        $rental_stmt->execute();
        $rental_result = $rental_stmt->get_result();
        while ($row = $rental_result->fetch_assoc()) {
            $rental_items[] = [
                'ten' => $row['TEN_TP'],
                'so_luong' => (int)$row['SO_LUONG'],
                'don_gia_ap_dung' => (float)$row['DON_GIA_AP_DUNG'],
                'thanh_tien' => (float)$row['THANH_TIEN']
            ];
        }
        $rental_stmt->close();
    }
}

// Prepare invoice data in the format expected by JavaScript
$invoice = [
    'id_hd' => (int)$invoice_data['ID_HD'],
    'ngay_gio' => $invoice_data['NGAY_GIO'],
    'is_paid' => $invoice_data['TRANGTHAI_THANHTOAN'] === 'Đã thanh toán',
    'phuongthuc_thanhtoan' => $invoice_data['PHUONGTHUC_THANHTOAN'],
    'tong_tien' => (float)$invoice_data['TONG_TIEN'],
    'vnpay_trang_thai' => $invoice_data['VNPAY_TRANG_THAI'],
    'vnpay_ma_tham_chieu' => $invoice_data['VNPAY_MA_THAM_CHIEU'],
    'vnpay_updated_at' => $invoice_data['VNPAY_UPDATED_AT'],
    'khach_hang' => [
        'ho_ten' => $invoice_data['HO_TEN'] ?? 'N/A',
        'email' => $invoice_data['EMAIL'] ?? 'N/A',
        'sdt' => $invoice_data['SDT'] ?? 'N/A'
    ],
    'is_rental' => !empty($invoice_data['ID_TTP']),
    'line_items' => $line_items,
    'rental_items' => $rental_items
];

// Add rental or schedule info
if (!empty($invoice_data['ID_TTP'])) {
    $invoice['rental_info'] = [
        'id_ttp' => $invoice_data['ID_TTP'],
        'ngay_nhan' => $invoice_data['NGAY_NHAN'],
        'ngay_tra_dk' => $invoice_data['NGAY_TRA_DK'],
        'ngay_tra_tt' => $invoice_data['NGAY_TRA_TT'],
        'trang_thai' => $invoice_data['TTP_TRANGTHAI'],
        'tien_coc' => (float)$invoice_data['TIEN_COC'],
        'tong_tien_du_kien' => (float)$invoice_data['TONG_TIEN_DU_KIEN'],
        'tong_tien_thuc_te' => (float)$invoice_data['TONG_TIEN_THUC_TE']
    ];
} else if (!empty($invoice_data['ID_LICHHEN'])) {
    $invoice['schedule_info'] = [
        'id_lichhen' => $invoice_data['ID_LICHHEN'],
        'ten_dv' => $invoice_data['TEN_DV'] ?? 'N/A',
        'thoi_luong' => (int)$invoice_data['thoi_gian'] ?? 0
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa đơn #<?= $invoice['id_hd'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body { background: white !important; margin: 0; padding: 0; }
            .no-print { display: none !important; }
        }
        .spinner {
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-top: 3px solid #2563eb;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-50">
<div id="invoice-detail-container" class="max-w-4xl mx-auto p-8 bg-white">
  <!-- Loading state -->
  <div id="loading-state" class="text-center py-12">
    <div class="spinner mb-4"></div>
    <p class="text-gray-600">Đang tải thông tin hóa đơn...</p>
  </div>

  <!-- Content -->
  <div id="content-state">
    <!-- Error message -->
    <div id="error-alert" class="hidden mb-4 border border-red-200 bg-red-50 text-red-800 px-4 py-3 rounded-lg text-sm font-medium"></div>

    <!-- Success message -->
    <div id="success-alert" class="hidden mb-4 border border-green-200 bg-green-50 text-green-800 px-4 py-3 rounded-lg text-sm font-medium"></div>

    <!-- Header -->
    <div class="flex justify-between items-start mb-8">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">HÓA ĐƠN #<span id="invoice-id">-</span></h1>
        <p class="text-sm text-gray-500 mt-1">Ngày <span id="invoice-date">-</span></p>
      </div>
      <div class="flex gap-2">
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition text-sm font-semibold no-print">
          In
        </button>
        <a href="<?= htmlspecialchars($backUrl) ?>" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 transition text-sm font-semibold no-print">
          Quay lại
        </a>
      </div>
    </div>

    <!-- Status Banner -->
    <div id="status-banner" class="mb-8 p-4 rounded-lg border"></div>

    <!-- Khách hàng -->
    <div class="mb-8">
      <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Khách hàng</h3>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <p class="text-xs text-gray-600 uppercase">Họ tên</p>
          <p class="text-sm font-semibold text-gray-900" id="customer-name">-</p>
        </div>
        <div>
          <p class="text-xs text-gray-600 uppercase">Email</p>
          <p class="text-sm text-gray-900" id="customer-email">-</p>
        </div>
        <div>
          <p class="text-xs text-gray-600 uppercase">Số điện thoại</p>
          <p class="text-sm text-gray-900" id="customer-phone">-</p>
        </div>
      </div>
    </div>

    <!-- Thông tin đơn -->
    <div id="order-info-section" class="mb-8"></div>

    <!-- Dịch vụ/Trang phục -->
    <div id="service-section" class="mb-8"></div>

    <!-- Line items -->
    <div id="line-items-section" class="mb-8"></div>

    <!-- Thiết bị -->
    <div id="equipment-section" class="mb-8"></div>

    <!-- Total -->
    <div class="mb-8 border-t pt-4">
      <div class="flex justify-end mb-4">
        <div class="text-right">
          <p class="text-sm text-gray-600">Tổng cộng</p>
          <p class="text-2xl font-bold text-gray-900" id="total-amount">0 VND</p>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div id="actions-section" class="mt-8 no-print"></div>

    <!-- Footer -->
    <div class="mt-12 pt-6 border-t text-center text-xs text-gray-600">
      <p>Stygian Blue © 2025</p>
    </div>
  </div>
</div>

<script src="/StygianBlue/public/assets/js/invoice-client.js"></script>
<script>
class InvoiceDetailUI {
  constructor(invoiceData) {
    this.invoiceData = invoiceData;
    this.client = new InvoiceClient();
  }

  loadInvoiceData() {
    // Data đã được tải từ PHP, ẩn loading state
    const loadingState = document.getElementById('loading-state');
    if (loadingState) {
      loadingState.classList.add('hidden');
    }
  }

  showError(message) {
    const alert = document.getElementById('error-alert');
    alert.textContent = message;
    alert.classList.remove('hidden');
  }

  showSuccess(message) {
    const alert = document.getElementById('success-alert');
    alert.textContent = message;
    alert.classList.remove('hidden');
    setTimeout(() => alert.classList.add('hidden'), 4000);
  }

  render() {
    const data = this.invoiceData;

    // Header
    document.getElementById('invoice-id').textContent = data.id_hd;
    document.getElementById('invoice-date').textContent = this.formatDateTime(data.ngay_gio);

    // Status Banner
    this.renderStatusBanner(data);

    // Customer info
    document.getElementById('customer-name').textContent = this.escapeHtml(data.khach_hang?.ho_ten || '-');
    document.getElementById('customer-email').textContent = this.escapeHtml(data.khach_hang?.email || '-');
    document.getElementById('customer-phone').textContent = this.escapeHtml(data.khach_hang?.sdt || '-');

    // Order info
    this.renderOrderInfo(data);

    // Service/Rental info
    this.renderServiceSection(data);

    // Line items
    if (data.line_items && data.line_items.length > 0) {
      this.renderLineItems(data);
    }

    // Equipment
    if (data.equipments && data.equipments.length > 0) {
      this.renderEquipment(data);
    }

    // Total
    document.getElementById('total-amount').textContent = this.formatCurrency(data.tong_tien);

    // Actions
    this.renderActions(data);
  }

  renderStatusBanner(data) {
    const banner = document.getElementById('status-banner');
    const isPaid = data.is_paid;
    
    if (isPaid) {
      banner.className = 'mb-8 p-4 rounded-lg border border-green-200 bg-green-50';
      banner.innerHTML = `<p class="text-green-800 font-semibold"><i class="fas fa-check-circle"></i> Hóa đơn đã được thanh toán</p>`;
    } else {
      banner.className = 'mb-8 p-4 rounded-lg border border-yellow-200 bg-yellow-50';
      banner.innerHTML = `<p class="text-yellow-800 font-semibold"><i class="fas fa-exclamation-circle"></i> Hóa đơn chưa thanh toán</p>`;
    }
  }

  renderOrderInfo(data) {
    const section = document.getElementById('order-info-section');
    let html = '<div class="mb-8">';
    html += '<h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Thông tin đơn hàng</h3>';
    html += '<div class="grid grid-cols-2 gap-4 text-sm">';

    if (data.is_rental && data.rental_info) {
      const ri = data.rental_info;
      html += `
        <div><p class="text-xs text-gray-600 uppercase">Mã đơn thuê</p><p class="font-semibold">${ri.id_ttp}</p></div>
        <div><p class="text-xs text-gray-600 uppercase">Trạng thái</p><p class="font-semibold">${this.escapeHtml(ri.trang_thai || '-')}</p></div>
        <div><p class="text-xs text-gray-600 uppercase">Ngày nhận</p><p>${this.formatDateTime(ri.ngay_nhan)}</p></div>
        <div><p class="text-xs text-gray-600 uppercase">Ngày trả dự kiến</p><p>${this.formatDateTime(ri.ngay_tra_dk)}</p></div>
        ${ri.ngay_tra_tt ? `<div><p class="text-xs text-gray-600 uppercase">Ngày trả thực tế</p><p>${this.formatDateTime(ri.ngay_tra_tt)}</p></div>` : ''}
        <div><p class="text-xs text-gray-600 uppercase">Tiền cọc</p><p class="font-semibold">${this.formatCurrency(ri.tien_coc)}</p></div>
      `;
    } else if (data.schedule_info) {
      const si = data.schedule_info;
      html += `
        <div><p class="text-xs text-gray-600 uppercase">Mã lịch hẹn</p><p class="font-semibold">${si.id_lichhen}</p></div>
        <div><p class="text-xs text-gray-600 uppercase">Dịch vụ</p><p class="font-semibold">${this.escapeHtml(si.ten_dv)}</p></div>
        <div><p class="text-xs text-gray-600 uppercase">Thời lượng</p><p>${si.thoi_luong} phút</p></div>
      `;
    }

    html += '</div></div>';
    section.innerHTML = html;
  }

  renderServiceSection(data) {
    const section = document.getElementById('service-section');
    if (!data.is_rental && !data.schedule_info) return;

    let html = '';
    
    if (data.is_rental && data.rental_items && data.rental_items.length > 0) {
      html += `
        <div class="mb-8">
          <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Trang phục thuê</h3>
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-gray-100 border">
                <th class="p-3 text-left">Tên trang phục</th>
                <th class="p-3 text-center">SL</th>
                <th class="p-3 text-right">Đơn giá</th>
                <th class="p-3 text-right">Thành tiền</th>
              </tr>
            </thead>
            <tbody>
      `;

      let subtotal = 0;
      data.rental_items.forEach(item => {
        subtotal += parseFloat(item.thanh_tien) || 0;
        html += `
          <tr class="border">
            <td class="p-3">${this.escapeHtml(item.ten)}</td>
            <td class="p-3 text-center">${item.so_luong}</td>
            <td class="p-3 text-right">${this.formatCurrency(item.don_gia_ap_dung)}</td>
            <td class="p-3 text-right">${this.formatCurrency(item.thanh_tien)}</td>
          </tr>
        `;
      });

      html += `
            </tbody>
          </table>
        </div>
      `;
    }

    section.innerHTML = html;
  }

  renderLineItems(data) {
    const section = document.getElementById('line-items-section');
    if (!data.line_items || data.line_items.length === 0) return;

    let html = `
      <div class="mb-8">
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Chi tiết hóa đơn</h3>
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr class="bg-gray-100 border">
              <th class="p-3 text-left">Mô tả</th>
              <th class="p-3 text-center">SL</th>
              <th class="p-3 text-right">Đơn giá</th>
              <th class="p-3 text-right">Thành tiền</th>
            </tr>
          </thead>
          <tbody>
    `;

    let total = 0;
    data.line_items.forEach(item => {
      total += parseFloat(item.THANH_TIEN) || 0;
      html += `
        <tr class="border">
          <td class="p-3">${this.escapeHtml(item.MO_TA)}</td>
          <td class="p-3 text-center">${item.SO_LUONG}</td>
          <td class="p-3 text-right">${this.formatCurrency(item.DON_GIA)}</td>
          <td class="p-3 text-right">${this.formatCurrency(item.THANH_TIEN)}</td>
        </tr>
      `;
    });

    html += `
          </tbody>
        </table>
      </div>
    `;

    section.innerHTML = html;
  }

  renderRentalItems(data) {
    const section = document.getElementById('rental-items-section');
    if (!data.rental_items || data.rental_items.length === 0) return;

    let html = `
      <h2 class="text-xl font-bold text-slate-900 mb-2">Trang phục thuê</h2>
      <table class="w-full border mt-2 rounded-lg overflow-hidden text-sm">
        <thead class="bg-indigo-100">
          <tr>
            <th class="p-3 border">Tên trang phục</th>
            <th class="p-3 border">Số lượng</th>
            <th class="p-3 border">Đơn giá áp dụng</th>
            <th class="p-3 border">Thành tiền</th>
          </tr>
        </thead>
        <tbody>
    `;

    let subtotal = 0;
    data.rental_items.forEach(item => {
      subtotal += item.thanh_tien;
      html += `
        <tr class="bg-white hover:bg-gray-50">
          <td class="p-3 border">${this.escapeHtml(item.ten)}</td>
          <td class="p-3 border">${item.so_luong}</td>
          <td class="p-3 border">${this.formatCurrency(item.don_gia_ap_dung)}</td>
          <td class="p-3 border">${this.formatCurrency(item.thanh_tien)}</td>
        </tr>
      `;
    });

    html += `
        <tr class="font-bold bg-gray-100">
          <td colspan="3" class="p-3 border text-right">Tạm tính trang phục</td>
          <td class="p-3 border text-indigo-700">${this.formatCurrency(subtotal)}</td>
        </tr>
        </tbody>
      </table>
    `;

    section.innerHTML = html;
  }

  renderEquipment(data) {
    const section = document.getElementById('equipment-section');
    if (!data.equipments || data.equipments.length === 0) return;

    let html = `
      <div class="mb-8">
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-3">Thiết bị kèm theo</h3>
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr class="bg-gray-100 border">
              <th class="p-3 text-left">Thiết bị</th>
              <th class="p-3 text-center">SL</th>
              <th class="p-3 text-right">Đơn giá</th>
              <th class="p-3 text-right">Thành tiền</th>
            </tr>
          </thead>
          <tbody>
    `;

    let total = 0;
    data.equipments.forEach(item => {
      const subtotal = (parseFloat(item.don_gia) || 0) * (parseInt(item.so_luong) || 0);
      total += subtotal;
      html += `
        <tr class="border">
          <td class="p-3">${this.escapeHtml(item.ten_tb)}</td>
          <td class="p-3 text-center">${item.so_luong}</td>
          <td class="p-3 text-right">${this.formatCurrency(item.don_gia)}</td>
          <td class="p-3 text-right">${this.formatCurrency(subtotal)}</td>
        </tr>
      `;
    });

    html += `
          </tbody>
        </table>
      </div>
    `;

    section.innerHTML = html;
  }

  renderActions(data) {
    const section = document.getElementById('actions-section');
    const isPaid = data.is_paid;
    const isGatewayPending = !isPaid && data.vnpay_trang_thai === 'pending';

    if (!isPaid) {
      let html = `
        <div class="p-4 rounded-lg border ${isGatewayPending ? 'border-yellow-200 bg-yellow-50' : 'border-gray-200 bg-gray-50'}">
          <p class="text-sm ${isGatewayPending ? 'text-yellow-800' : 'text-gray-700'}">
            ${isGatewayPending
              ? 'VNPay đã ghi nhận giao dịch và đang chờ IPN. Không cần xác nhận thủ công.'
              : 'Chỉ xác nhận thủ công khi đã đối chiếu được chứng từ chuyển khoản.'}
          </p>
        </div>
        <div class="mt-6 flex gap-3">
          <button id="confirm-payment-btn" ${isGatewayPending ? 'disabled' : ''} 
            class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-lg font-semibold shadow-md transition ${isGatewayPending ? 'opacity-60 cursor-not-allowed' : ''}">
            ${isGatewayPending ? 'Chờ VNPay' : 'Xác nhận đã thanh toán'}
          </button>
          <a href="<?= htmlspecialchars($backUrl) ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2.5 rounded-lg font-semibold shadow-md transition">
            Quay lại
          </a>
        </div>
      `;
      section.innerHTML = html;

      if (!isGatewayPending) {
        document.getElementById('confirm-payment-btn').addEventListener('click', () => this.confirmPayment(data.id_hd));
      }
    } else {
      let html = `
        <div class="flex gap-3">
          <a href="<?= htmlspecialchars($backUrl) ?>" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-6 py-2.5 rounded-lg font-semibold shadow-md transition">
            Quay lại danh sách
          </a>
        </div>
      `;
      section.innerHTML = html;
    }
  }

  async confirmPayment(invoiceId) {
    if (!confirm('Xác nhận khách hàng đã thanh toán hóa đơn này?')) return;

    try {
      const result = await this.client.confirmPayment(invoiceId);

      if (result.success) {
        this.showSuccess('Đã xác nhận thanh toán thành công');
        setTimeout(() => {
          location.href = '<?= htmlspecialchars($backUrl) ?>';
        }, 1500);
      } else {
        this.showError(result.message || 'Lỗi xác nhận thanh toán');
      }
    } catch (error) {
      this.showError('Lỗi xác nhận thanh toán: ' + error.message);
    }
  }

  async processRefund(invoiceId) {
    const refundType = document.getElementById('refund-type').value;
    const amount = parseFloat(document.getElementById('refund-amount').value);
    const reason = document.getElementById('refund-reason').value;

    if (amount <= 0) {
      this.showError('Số tiền hoàn phải lớn hơn 0');
      return;
    }

    try {
      const result = await this.client.refundInvoice(invoiceId, amount, reason || '');

      if (result.success) {
        this.showSuccess('Yêu cầu hoàn tiền đã được tạo');
        setTimeout(() => {
          location.href = '<?= htmlspecialchars($backUrl) ?>';
        }, 1500);
      } else {
        this.showError(result.message || 'Lỗi xử lý hoàn tiền');
      }
    } catch (error) {
      this.showError('Lỗi xử lý hoàn tiền: ' + error.message);
    }
  }

  formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('vi-VN') + ' ' + date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
  }

  formatCurrency(num) {
    const formatted = Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return formatted + ' VND';
  }

  escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, m => map[m]);
  }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  const ui = new InvoiceDetailUI(<?= json_encode($invoice) ?>);
  ui.loadInvoiceData();
  ui.render();
});
</script>

<style>
.spinner {
  border: 3px solid rgba(0, 0, 0, 0.1);
  border-top: 3px solid #4f46e5;
  border-radius: 50%;
  width: 30px;
  height: 30px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
</body>
</html>
</style>
