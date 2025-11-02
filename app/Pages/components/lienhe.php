<?php
include '../../../database/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$idTk = null;
if (isset($_SESSION['user']['ID_TK'])) {
    $idTk = $_SESSION['user']['ID_TK'];
} elseif (isset($_SESSION['ID_TK'])) {
    $idTk = $_SESSION['ID_TK'];
}

$hoTen = '';
$email = '';
$sdt   = '';

if ($idTk) {
    $stmt = $conn->prepare('SELECT HO_TEN, EMAIL, SDT FROM TAI_KHOAN WHERE ID_TK = ?');
    if ($stmt) {
        $stmt->bind_param('s', $idTk);
        if ($stmt->execute()) {
            $stmt->bind_result($hoTen, $email, $sdt);
            $stmt->fetch();
        }
        $stmt->close();
    }
}

$branches = [];
$stmtBranches = $conn->prepare('SELECT ID_CN, TEN_CN FROM CHI_NHANH ORDER BY TEN_CN ASC');
if ($stmtBranches) {
    $stmtBranches->execute();
    $resultBranches = $stmtBranches->get_result();
    while ($row = $resultBranches->fetch_assoc()) {
        $branches[] = $row;
    }
    $stmtBranches->close();
}

$services = [];
$stmtServices = $conn->prepare('SELECT ID_DV, TEN_DV FROM DICH_VU ORDER BY TEN_DV ASC');
if ($stmtServices) {
$stmtServices->execute();
    $resultServices = $stmtServices->get_result();
    while ($row = $resultServices->fetch_assoc()) {
        $services[] = $row;
    }
    $stmtServices->close();
}

$packages = [];
$stmtPackages = $conn->prepare(
    "SELECT ID_GOI, TEN_GOI, TONG_GIA_GOI FROM v_goi_dich_vu_tong_tien \n     WHERE TRANG_THAI = 'ban' \n       AND (HIEU_LUC_TU IS NULL OR HIEU_LUC_TU <= NOW()) \n       AND (HIEU_LUC_DEN IS NULL OR HIEU_LUC_DEN >= NOW()) \n     ORDER BY TEN_GOI ASC"
);
if ($stmtPackages) {
    $stmtPackages->execute();
    $resultPackages = $stmtPackages->get_result();
    while ($row = $resultPackages->fetch_assoc()) {
        $packages[] = $row;
    }
    $stmtPackages->close();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<section data-booking class="space-y-6">
  <div class="grid gap-6 xl:grid-cols-5">
    <div class="xl:col-span-2">
      <div class="h-full overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xl">
        <iframe
          class="h-64 w-full border-0 md:h-[420px] xl:h-full"
          src="https://www.google.com/maps/embed?pb=!1m17!1m8!1m3!1d601.1369952501527!2d105.7694797520741!3d10.031037462832488!3m2!1i1024!2i768!4f13.1!4m6!3e6!4m0!4m3!3m2!1d10.031076258582523!2d105.76916785116899!5e0!3m2!1svi!2s!4v1733014584068!5m2!1svi!2s"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          allowfullscreen
          title="Bản đồ chi nhánh Stygian Blue"
        ></iframe>
      </div>
    </div>

    <div class="xl:col-span-3">
      <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-xl backdrop-blur sm:p-8">
        <header class="text-center">
          <p class="text-xs font-semibold uppercase tracking-[0.25em] text-sky-600">Điền thông tin của bạn</p>
          <h1 class="mt-2 text-2xl font-bold text-slate-900">Đặt lịch hẹn</h1>
          <p class="mt-3 text-sm text-slate-600">Chọn chi nhánh, khung giờ và loại dịch vụ để chúng tôi chuẩn bị tốt nhất cho buổi làm việc.</p>
        </header>

        <form action="../controller/process_schedule.php" method="POST" id="scheduleForm" class="mt-6 space-y-6" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

          <div class="space-y-2">
            <label for="branch" class="text-sm font-semibold text-slate-800">Chi nhánh</label>
            <select id="branch" name="branch_id" required class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200">
              <option value="">Chọn chi nhánh...</option>
              <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['ID_CN'] ?>"><?= htmlspecialchars($branch['TEN_CN']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-500">Lịch trống sẽ hiển thị theo chi nhánh đã chọn.</p>
          </div>

          <div class="space-y-2">
            <label for="ngayHen" class="text-sm font-semibold text-slate-800">Ngày</label>
            <input type="text" id="ngayHen" name="ngayHen" required placeholder="YYYY-MM-DD" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200">
            <p class="text-xs text-slate-500">Chỉ được chọn ngày từ hiện tại trở đi.</p>
          </div>

          <div class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <label class="text-sm font-semibold text-slate-800">Khung giờ</label>
              <span class="text-xs font-medium text-slate-500">Chọn một khung giờ phù hợp</span>
            </div>
            <div id="khungGioContainer" class="grid grid-cols-2 gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Chọn khung giờ"></div>
            <input type="hidden" id="gioHen" name="gioHen" required>
            <p id="slotMsg" class="text-xs text-slate-500"></p>
          </div>

          <div class="space-y-2">
            <label for="address" class="text-sm font-semibold text-slate-800">Địa điểm hẹn <span class="font-normal text-slate-400">(tuỳ chọn)</span></label>
            <input type="text" id="address" name="address" placeholder="Tại studio hoặc địa điểm ngoài..." class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200">
          </div>

          <fieldset class="space-y-3">
            <legend class="text-sm font-semibold text-slate-800">Hình thức đặt lịch</legend>
            <div class="flex flex-wrap gap-3" role="radiogroup" aria-label="Chọn hình thức đặt lịch">
              <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                <input type="radio" name="booking_type" value="service" checked class="h-4 w-4 border-slate-300 text-sky-500 focus:ring-sky-400">
                <span>Dịch vụ lẻ</span>
              </label>
              <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                <input type="radio" name="booking_type" value="package" class="h-4 w-4 border-slate-300 text-sky-500 focus:ring-sky-400">
                <span>Gói dịch vụ</span>
              </label>
            </div>
            <p class="text-xs text-slate-500">Chọn gói dịch vụ để đặt trọn bộ dịch vụ với giá ưu đãi.</p>
          </fieldset>

          <div class="space-y-2" data-service-field>
            <label for="service" class="text-sm font-semibold text-slate-800">Dịch vụ</label>
            <select id="service" name="service_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200">
              <option value="">Chọn dịch vụ...</option>
              <?php foreach ($services as $service): ?>
                <option value="<?= $service['ID_DV'] ?>"><?= htmlspecialchars($service['TEN_DV']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-500">Có thể chọn thêm thiết bị sau khi chọn dịch vụ phù hợp.</p>
          </div>

          <div class="space-y-2 hidden" data-package-field>
            <label for="package" class="text-sm font-semibold text-slate-800">Gói dịch vụ</label>
            <select id="package" name="package_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200">
              <option value="">Chọn gói dịch vụ...</option>
              <?php foreach ($packages as $package): ?>
                <option value="<?= $package['ID_GOI'] ?>" data-price="<?= (int) $package['TONG_GIA_GOI'] ?>">
                  <?= htmlspecialchars($package['TEN_GOI']) ?> (<?= number_format((int) $package['TONG_GIA_GOI'], 0, ',', '.') ?>đ)
                </option>
              <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-500">Giá hiển thị đã bao gồm toàn bộ dịch vụ trong gói.</p>
          </div>

          <div id="chon_thiet_bi_div" class="space-y-3 hidden">
            <label class="text-sm font-semibold text-slate-800">Chọn thiết bị</label>
            <div id="thiet_bi_checkbox_list" class="space-y-2"></div>
          </div>

          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="space-y-2">
              <label class="text-sm font-semibold text-slate-800" for="full_name">Họ tên</label>
              <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($hoTen) ?>" readonly class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            </div>
            <div class="space-y-2">
              <label class="text-sm font-semibold text-slate-800" for="email">Email</label>
              <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" readonly class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            </div>
            <div class="space-y-2">
              <label class="text-sm font-semibold text-slate-800" for="phone">Số điện thoại</label>
              <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($sdt) ?>" readonly class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            </div>
          </div>

          <div id="quoteBox" class="hidden rounded-2xl border border-sky-200 bg-sky-50/80 p-4">
            <div class="flex items-center justify-between text-sm font-semibold text-slate-800">
              <span>Tạm tính</span>
              <strong id="quoteTotal" class="text-lg text-slate-900">—</strong>
            </div>
            <p id="quoteNote" class="mt-2 text-xs text-slate-500">Chưa bao gồm phụ phí đặc biệt (nếu có).</p>
          </div>

          <button id="submitBtn" type="submit" class="inline-flex w-full justify-center rounded-2xl bg-gradient-to-r from-sky-500 via-indigo-500 to-violet-500 px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-300 disabled:cursor-not-allowed disabled:opacity-70">Xác nhận đặt lịch</button>
          <p id="formMsg" class="text-sm text-amber-600"></p>
        </form>
      </div>
    </div>
  </div>
</section>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
(function () {
  const root = document.querySelector('[data-booking]');
  if (!root) return;

  const slotTimes = ['08:00:00', '09:00:00', '10:00:00', '13:00:00', '14:00:00', '15:00:00'];

  const slotContainer = root.querySelector('#khungGioContainer');
  const slotField = root.querySelector('#gioHen');
  const dateField = root.querySelector('#ngayHen');
  const branchField = root.querySelector('#branch');
  const slotMessage = root.querySelector('#slotMsg');
  const bookingTypeRadios = root.querySelectorAll("input[name='booking_type']");
  const serviceFieldWrap = root.querySelector('[data-service-field]');
  const packageFieldWrap = root.querySelector('[data-package-field]');
  const serviceSelect = root.querySelector('#service');
  const packageSelect = root.querySelector('#package');
  const deviceWrapper = root.querySelector('#chon_thiet_bi_div');
  const deviceList = root.querySelector('#thiet_bi_checkbox_list');
  const quoteBox = root.querySelector('#quoteBox');
  const quoteTotal = root.querySelector('#quoteTotal');
  const quoteNote = root.querySelector('#quoteNote');
  const submitBtn = root.querySelector('#submitBtn');
  const formMsg = root.querySelector('#formMsg');

  const defaultQuoteNote = quoteNote ? quoteNote.textContent : '';
  const slotBaseClasses = 'flex w-full items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-sm font-semibold text-slate-700 transition hover:border-sky-300 hover:text-sky-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-300';
  const slotSelectedClasses = ['border-sky-500', 'bg-sky-50', 'text-sky-700', 'shadow'];
  const slotDisabledClasses = ['cursor-not-allowed', 'bg-rose-50', 'border-rose-200', 'text-rose-500', 'opacity-70'];

  function setSlotMessage(text, tone = 'muted') {
    if (!slotMessage) return;
    slotMessage.textContent = text;
    slotMessage.classList.remove('text-slate-500', 'text-rose-600', 'text-sky-600');
    if (!text) {
      slotMessage.classList.add('text-slate-500');
      return;
    }
    const toneClass = tone === 'error' ? 'text-rose-600' : tone === 'accent' ? 'text-sky-600' : 'text-slate-500';
    slotMessage.classList.add(toneClass);
  }

  function currentBookingType() {
    const checked = root.querySelector("input[name='booking_type']:checked");
    return checked && checked.value === 'package' ? 'package' : 'service';
  }

  function toggleBookingFields() {
    const type = currentBookingType();
    if (type === 'package') {
      serviceFieldWrap.classList.add('hidden');
      packageFieldWrap.classList.remove('hidden');
      deviceWrapper.classList.add('hidden');
      deviceList.innerHTML = '';
      if (serviceSelect) serviceSelect.value = '';
    } else {
      serviceFieldWrap.classList.remove('hidden');
      packageFieldWrap.classList.add('hidden');
      if (packageSelect) packageSelect.value = '';
    }
    updateQuotePreview();
  }

  async function loadBookedSlots() {
    const date = dateField.value;
    const branchId = parseInt(branchField.value, 10);
    slotField.value = '';
    slotContainer.innerHTML = '';
    setSlotMessage('');
    submitBtn.disabled = true;

    if (!date || Number.isNaN(branchId)) {
      setSlotMessage('Vui lòng chọn chi nhánh và ngày.', 'accent');
      return;
    }

    slotContainer.innerHTML = '<p class="col-span-full text-sm text-slate-500">Đang tải khung giờ...</p>';

    try {
      const response = await fetch(`../controller/get_booked_slots.php?date=${encodeURIComponent(date)}&branch_id=${branchId}`);
      const bookedSlots = await response.json();
      const hasSlots = renderSlots(Array.isArray(bookedSlots) ? bookedSlots : []);
      if (hasSlots) {
        setSlotMessage('Chọn một khung giờ phù hợp.', 'muted');
      }
    } catch (error) {
      slotContainer.innerHTML = '';
      setSlotMessage('Không thể tải khung giờ, vui lòng thử lại.', 'error');
    }
  }

  function renderSlots(booked) {
    slotContainer.innerHTML = '';
    const today = new Date();
    const currentDate = dateField.value;
    const isToday = currentDate === today.toISOString().slice(0, 10);
    const currentTime = `${String(today.getHours()).padStart(2, '0')}:${String(today.getMinutes()).padStart(2, '0')}:00`;

    let hasAvailableSlot = false;

    slotTimes.forEach((time) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = time.slice(0, 5);
      button.setAttribute('role', 'radio');
      button.setAttribute('aria-checked', 'false');
      button.dataset.slotButton = 'true';
      button.className = slotBaseClasses;

      const disabled = booked.includes(time) || (isToday && time <= currentTime);
      if (disabled) {
        button.disabled = true;
        slotDisabledClasses.forEach((cls) => button.classList.add(cls));
      } else {
        hasAvailableSlot = true;
        button.addEventListener('click', () => {
          if (button.disabled) return;
          slotContainer.querySelectorAll('button[data-slot-button]').forEach((btn) => {
            btn.setAttribute('aria-checked', 'false');
            slotSelectedClasses.forEach((cls) => btn.classList.remove(cls));
          });
          button.setAttribute('aria-checked', 'true');
          slotSelectedClasses.forEach((cls) => button.classList.add(cls));
          slotField.value = time;
          submitBtn.disabled = false;
          updateQuotePreview();
        });
      }

      slotContainer.appendChild(button);
    });

    if (!hasAvailableSlot) {
      slotContainer.innerHTML = '<p class="col-span-full rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-600">Không còn khung giờ trống trong ngày này. Vui lòng chọn ngày khác.</p>';
      setSlotMessage('Không còn khung giờ trống trong ngày này. Vui lòng chọn ngày khác.', 'error');
      return false;
    }

    return true;
  }

  if (window.flatpickr) {
    window.flatpickr(dateField, {
      dateFormat: 'Y-m-d',
      minDate: 'today',
      disableMobile: true,
      onChange: loadBookedSlots
    });
  }

  dateField.addEventListener('change', loadBookedSlots);
  branchField.addEventListener('change', loadBookedSlots);
  bookingTypeRadios.forEach((radio) => {
    radio.addEventListener('change', toggleBookingFields);
  });

  if (serviceSelect) {
    serviceSelect.addEventListener('change', async function onServiceChange() {
      if (currentBookingType() !== 'service') {
        deviceWrapper.classList.add('hidden');
        deviceList.innerHTML = '';
        updateQuotePreview();
        return;
      }

      const selectedOption = this.options[this.selectedIndex];
      const selectedLabel = selectedOption ? selectedOption.textContent.trim() : '';

      if (selectedLabel === 'Thuê trang thiết bị') {
        deviceWrapper.classList.remove('hidden');
        deviceList.innerHTML = '<p class="text-sm text-slate-500">Đang tải danh sách thiết bị...</p>';
        try {
          const response = await fetch('../controller/get_trang_thiet_bi.php');
          const devices = await response.json();
          deviceList.innerHTML = '';
          if (Array.isArray(devices) && devices.length > 0) {
            devices.forEach((item) => {
              const label = document.createElement('label');
              const checkbox = document.createElement('input');
              const span = document.createElement('span');

              label.className = 'flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700';
              checkbox.type = 'checkbox';
              checkbox.name = 'thiet_bi_id[]';
              checkbox.value = item.ID_TB;
              checkbox.className = 'h-4 w-4 rounded border-slate-300 text-sky-500 focus:ring-sky-400';
              span.textContent = item.TEN_TB;

              label.appendChild(checkbox);
              label.appendChild(span);
              deviceList.appendChild(label);
            });
          } else {
            deviceList.innerHTML = '<p class="text-sm text-slate-500">Không có thiết bị khả dụng.</p>';
          }
        } catch (error) {
          deviceList.innerHTML = '<p class="text-sm text-rose-600">Không thể tải thiết bị.</p>';
        }
      } else {
        deviceWrapper.classList.add('hidden');
        deviceList.innerHTML = '';
      }

      updateQuotePreview();
    });
  }

  if (packageSelect) {
    packageSelect.addEventListener('change', updateQuotePreview);
  }

  function selectedDeviceIds() {
    return Array.from(root.querySelectorAll("input[name='thiet_bi_id[]']:checked"))
      .map((checkbox) => parseInt(checkbox.value, 10))
      .filter((value) => !Number.isNaN(value));
  }

  async function updateQuotePreview() {
    const branchId = parseInt(branchField.value, 10);
    const date = dateField.value;
    const time = slotField.value;
    const type = currentBookingType();
    const serviceId = parseInt(serviceSelect ? serviceSelect.value : '0', 10);
    const packageId = parseInt(packageSelect ? packageSelect.value : '0', 10);

    if (!branchId || !date || !time) {
      quoteBox.classList.add('hidden');
      return;
    }

    if (type === 'package') {
      if (!packageId) {
        quoteBox.classList.add('hidden');
        return;
      }

      const selectedOption = packageSelect.options[packageSelect.selectedIndex];
      const price = selectedOption ? parseInt(selectedOption.getAttribute('data-price') || '0', 10) : 0;
      quoteTotal.textContent = price.toLocaleString('vi-VN') + '₫';
      if (quoteNote) {
        quoteNote.textContent = 'Giá gói đã bao gồm toàn bộ dịch vụ trong gói.';
      }
      quoteBox.classList.remove('hidden');
      return;
    }

    if (!serviceId) {
      quoteBox.classList.add('hidden');
      return;
    }

    quoteBox.classList.remove('hidden');
    quoteTotal.textContent = 'Đang tính...';
    if (quoteNote) {
      quoteNote.textContent = defaultQuoteNote;
    }

    try {
      const response = await fetch('../controller/quote_preview.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          branch_id: branchId,
          date,
          time,
          service_id: serviceId,
          device_ids: selectedDeviceIds()
        })
      });
      const data = await response.json();
      const total = data && typeof data.total === 'number' ? data.total : 0;
      quoteTotal.textContent = total.toLocaleString('vi-VN') + '₫';
    } catch (error) {
      quoteTotal.textContent = '—';
    }
  }

  toggleBookingFields();

  const form = root.querySelector('#scheduleForm');
  form.addEventListener('submit', (event) => {
    formMsg.textContent = '';
    const type = currentBookingType();
    const missingService = type === 'service' && !(serviceSelect && serviceSelect.value);
    const missingPackage = type === 'package' && !(packageSelect && packageSelect.value);

    if (!branchField.value || !dateField.value || !slotField.value || missingService || missingPackage) {
      event.preventDefault();
      formMsg.textContent = 'Vui lòng chọn đủ chi nhánh, ngày, giờ và loại dịch vụ phù hợp.';
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Đang gửi...';
  });
})();
</script>