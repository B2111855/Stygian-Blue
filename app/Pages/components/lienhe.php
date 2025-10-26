<?php
include '../../../database/config.php';

// Bắt đầu session (nếu chưa có)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra thông tin người dùng
$id_tk = null;
if (isset($_SESSION['user']) && isset($_SESSION['user']['ID_TK'])) {
    $id_tk = $_SESSION['user']['ID_TK'];
}

// Lấy thông tin người dùng
$stmt = $conn->prepare("SELECT HO_TEN, EMAIL, SDT FROM TAI_KHOAN WHERE ID_TK = ?");
$stmt->bind_param("s", $userId);
$stmt->execute();
$stmt->bind_result($hoTen, $email, $sdt);
$stmt->fetch();
$stmt->close();

// Lấy danh sách chi nhánh
$branches = [];
$stmtBranches = $conn->prepare("SELECT ID_CN, TEN_CN FROM CHI_NHANH");
$stmtBranches->execute();
$resultBranches = $stmtBranches->get_result();
while ($row = $resultBranches->fetch_assoc()) $branches[] = $row;
$stmtBranches->close();

// Lấy danh sách dịch vụ
$services = [];
$stmtServices = $conn->prepare("SELECT ID_DV, TEN_DV FROM DICH_VU");
$stmtServices->execute();
$resultServices = $stmtServices->get_result();
while ($row = $resultServices->fetch_assoc()) $services[] = $row;
$stmtServices->close();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<!-- ===== Booking Map & Form (SCOPED) ===== -->
<section data-sb-booking class="sbk">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-10 m-4 mb-6 items-stretch">
    <!-- Map (lazy) -->
    <div class="lg:col-span-2 w-full h-full rounded-2xl overflow-hidden border border-slate-200 shadow-sm bg-white">
      <div class="relative w-full aspect-[16/9]">
        <!-- Skeleton while waiting -->
        <div class="sbk-skeleton absolute inset-0"></div>
        <iframe
          src="about:blank"
          data-map-src="https://www.google.com/maps/embed?pb=!1m17!1m8!1m3!1d601.1369952501527!2d105.7694797520741!3d10.031037462832488!3m2!1i1024!2i768!4f13.1!4m6!3e6!4m0!4m3!3m2!1d10.031076258582523!2d105.76916785116899!5e0!3m2!1svi!2s!4v1733014584068!5m2!1svi!2s"
          class="absolute inset-0 w-full h-full border-0"
          loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"
          onload="this.previousElementSibling?.remove()"></iframe>
      </div>
    </div>

    <!-- Form -->
    <div class="sbk-card p-6 rounded-2xl border border-slate-200 shadow-sm">
      <header class="text-center mb-4">
        <p class="sbk-pill inline-flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          Đặt lịch nhanh · Chọn chi nhánh · Ngày/Giờ · Dịch vụ
        </p>
        <h1 class="sbk-title">Đặt Lịch Hẹn</h1>
        <p class="sbk-muted">Chọn chi nhánh → ngày → giờ → dịch vụ, hệ thống sẽ tạm tính chi phí.</p>
      </header>

      <form action="../controller/process_schedule.php" method="POST" class="space-y-4" id="scheduleForm" novalidate>
        <!-- Chi nhánh -->
        <div>
          <label class="sbk-label">Chi nhánh</label>
          <div class="sbk-field">
            <i class="fas fa-store-alt"></i>
            <select name="branch_id" required>
              <option value="">Chọn chi nhánh...</option>
              <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['ID_CN'] ?>"><?= htmlspecialchars($branch['TEN_CN']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <p class="sbk-hint">Hệ thống sẽ kiểm tra lịch trống theo chi nhánh đã chọn.</p>
        </div>

        <!-- Ngày -->
        <div>
          <label class="sbk-label">Ngày</label>
          <div class="sbk-field">
            <i class="fas fa-calendar-day"></i>
            <input type="text" id="ngayHen" name="ngayHen" required placeholder="YYYY-MM-DD">
          </div>
          <p class="sbk-hint">Chỉ hiển thị các khung giờ còn trống và chưa qua thời điểm hiện tại.</p>
        </div>

        <!-- Giờ -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="sbk-label m-0">Khung giờ</label>
            <div class="flex items-center gap-3 text-xs">
              <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-emerald-500 inline-block"></span><span class="sbk-muted">Còn trống</span></span>
              <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-rose-500 inline-block"></span><span class="sbk-muted">Hết chỗ</span></span>
            </div>
          </div>

          <div id="khungGioContainer" role="radiogroup" aria-label="Chọn khung giờ" class="grid grid-cols-3 gap-2"></div>
          <input type="hidden" id="gioHen" name="gioHen" required>
          <p id="slotMsg" class="sbk-hint"></p>
        </div>

        <!-- Địa điểm -->
        <div>
          <label class="sbk-label">Địa điểm hẹn <span class="opacity-60">(tuỳ chọn)</span></label>
          <div class="sbk-field">
            <i class="fas fa-map-marker-alt"></i>
            <input type="text" name="address" placeholder="Tại studio hoặc địa điểm ngoài...">
          </div>
        </div>

        <!-- Dịch vụ -->
        <div>
          <label class="sbk-label">Dịch vụ</label>
          <div class="sbk-field">
            <i class="fas fa-camera"></i>
            <select id="service" name="service_id" required>
              <option value="">Chọn dịch vụ...</option>
              <?php foreach ($services as $service): ?>
                <option value="<?= $service['ID_DV'] ?>"><?= htmlspecialchars($service['TEN_DV']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <p class="sbk-hint">Hệ thống sẽ tính tạm tính dựa trên dịch vụ & thiết bị (nếu có).</p>
        </div>

        <!-- Thiết bị (ẩn/hiện) -->
        <div id="chon_thiet_bi_div" class="hidden">
          <label class="sbk-label">Chọn thiết bị</label>
          <div id="thiet_bi_checkbox_list" class="space-y-2"></div>
        </div>

        <!-- Thông tin KH (readonly) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="sbk-label">Họ tên</label>
            <div class="sbk-field">
              <i class="fas fa-user"></i>
              <input type="text" name="full_name" value="<?= htmlspecialchars($hoTen) ?>" readonly>
            </div>
          </div>
          <div>
            <label class="sbk-label">Email</label>
            <div class="sbk-field">
              <i class="fas fa-envelope"></i>
              <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" readonly>
            </div>
          </div>
          <div>
            <label class="sbk-label">Số điện thoại</label>
            <div class="sbk-field">
              <i class="fas fa-phone"></i>
              <input type="text" name="phone" value="<?= htmlspecialchars($sdt) ?>" readonly>
            </div>
          </div>
        </div>

        <!-- Tạm tính -->
        <div id="quoteBox" class="sbk-quote hidden">
          <div class="flex items-center justify-between">
            <span class="text-sm">Tạm tính</span>
            <strong id="quoteTotal" class="font-bold">…</strong>
          </div>
          <div id="quoteNote" class="sbk-hint mt-1">Chưa bao gồm phụ phí đặc biệt (nếu có).</div>
        </div>

        <!-- Submit -->
        <button id="submitBtn" type="submit" class="sbk-btn w-full">
          <i class="fas fa-check-circle"></i> Xác Nhận Đặt Lịch
        </button>
        <p id="formMsg" class="sbk-hint text-amber-600"></p>
      </form>
    </div>
  </div>
</section>

<!-- Flatpickr (nếu layout chưa có) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
/* ================================
   SCOPED styles — ONLY for booking
   ================================= */
[data-sb-booking].sbk { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
[data-sb-booking] .sbk-card{
  background: linear-gradient(135deg,#ecfeff,#ffffff,#fdf2f8);
}
[data-sb-booking] .sbk-title{
  margin:.25rem 0 .25rem; font-size: clamp(22px, 2.6vw, 28px); font-weight:800;
  background:linear-gradient(90deg,#0ea5e9,#a78bfa,#f472b6); -webkit-background-clip:text; background-clip:text; color:transparent;
}
[data-sb-booking] .sbk-muted{ color:#475569; font-size:13px; }

[data-sb-booking] .sbk-pill{
  font-size:12px; color:#0c4a6e; background:#e0f2fe; border:1px solid #bae6fd;
  border-radius:9999px; padding:.35rem .7rem; box-shadow:0 1px 0 rgba(2,6,23,.04);
}

[data-sb-booking] .sbk-label{ display:block; font-size:13px; font-weight:700; color:#0f172a; margin-bottom:.35rem; }
[data-sb-booking] .sbk-hint{ font-size:12px; color:#64748b; }

[data-sb-booking] .sbk-field{
  display:flex; align-items:center; gap:.5rem; padding:.65rem .8rem; border-radius:.75rem;
  background:#fff; border:1px solid #e2e8f0; box-shadow:0 1px 0 rgba(2,6,23,.03);
}
[data-sb-booking] .sbk-field:focus-within{
  border-color:#22d3ee; box-shadow:0 0 0 3px rgba(34,211,238,.25);
}
[data-sb-booking] .sbk-field i{ color:#64748b; }
[data-sb-booking] .sbk-field input,
[data-sb-booking] .sbk-field select{
  border:0; outline:0; width:100%; background:transparent; color:#0f172a; font-size:14px;
}

/* Slot buttons */
[data-sb-booking] #khungGioContainer button{
  padding:.6rem .5rem; border-radius:.7rem; border:1px solid transparent; font-weight:700; font-size:14px;
  background:linear-gradient(180deg,#f0fdf4,#dcfce7); color:#064e3b;
  transition: transform .15s ease, filter .15s ease, box-shadow .15s ease;
}
[data-sb-booking] #khungGioContainer button:hover{
  transform: translateY(-1px); filter:brightness(1.03);
  box-shadow: 0 6px 14px rgba(16,185,129,.15);
}
[data-sb-booking] #khungGioContainer button[disabled]{
  background:linear-gradient(180deg,#fff1f2,#ffe4e6); color:#9f1239; border-color:#fecdd3; cursor:not-allowed;
}
[data-sb-booking] #khungGioContainer button[aria-checked="true"]{
  outline:0; border-color:#22d3ee; box-shadow:0 0 0 3px rgba(34,211,238,.25);
}

/* Quote box */
[data-sb-booking] .sbk-quote{
  border:1px solid rgba(14,165,233,.35);
  background: linear-gradient(180deg,#ecfeff,#f0f9ff);
  color:#0f172a; border-radius:.75rem; padding:.75rem .8rem;
}

/* Submit button */
[data-sb-booking] .sbk-btn{
  display:inline-flex; align-items:center; justify-content:center; gap:.6rem;
  padding:.75rem 1rem; border-radius:.9rem; color:#fff; font-weight:800; letter-spacing:.2px;
  background:linear-gradient(90deg,#06b6d4,#a78bfa); border:1px solid transparent;
  box-shadow:0 10px 24px rgba(6,182,212,.22); transition: transform .15s ease, filter .15s ease;
}
[data-sb-booking] .sbk-btn:hover{ transform: translateY(-1px); filter: brightness(1.05); }
[data-sb-booking] .sbk-btn:disabled{ opacity:.55; cursor:not-allowed; }

/* Map skeleton shimmer */
[data-sb-booking] .sbk-skeleton{
  background:
    linear-gradient(90deg, rgba(226,232,240,0) 0%, rgba(226,232,240,.7) 50%, rgba(226,232,240,0) 100%),
    linear-gradient(#ffffff,#f8fafc);
  background-size:200% 100%,100% 100%;
  animation: sbk-shimmer 1.2s infinite;
}
@keyframes sbk-shimmer{0%{background-position:-200% 0,0 0}100%{background-position:200% 0,0 0}}

@media (prefers-reduced-motion: reduce){
  [data-sb-booking] *{ transition:none !important; animation:none !important; }
}
</style>

<script>
/* ===== SCOPED JS for [data-sb-booking] ===== */
(function(){
  const root = document.querySelector('[data-sb-booking]');
  if(!root) return;

  /* Lazy-load map */
  const iframe = root.querySelector('iframe[data-map-src]');
  if(iframe){
    const io = new IntersectionObserver((entries)=>{
      entries.forEach(e=>{
        if(e.isIntersecting){
          iframe.src = iframe.dataset.mapSrc;
          io.disconnect();
        }
      });
    }, { rootMargin: '200px' });
    io.observe(iframe);
  }

  /* State/Refs */
  const khungGio = ["08:00:00","09:00:00","10:00:00","13:00:00","14:00:00","15:00:00"];

  const container   = root.querySelector("#khungGioContainer");
  const hiddenInput = root.querySelector("#gioHen");
  const ngayInput   = root.querySelector("#ngayHen");
  const branchInput = root.querySelector("select[name='branch_id']");
  const serviceSel  = root.querySelector("#service");
  const slotMsg     = root.querySelector("#slotMsg");
  const quoteBox    = root.querySelector("#quoteBox");
  const quoteTotal  = root.querySelector("#quoteTotal");
  const submitBtn   = root.querySelector("#submitBtn");
  const formMsg     = root.querySelector("#formMsg");
  const thietBiDiv  = root.querySelector("#chon_thiet_bi_div");
  const thietBiList = root.querySelector("#thiet_bi_checkbox_list");

  /* Utils */
  const fmt = (v)=> (v||0).toLocaleString('vi-VN') + '₫';
  const todayYMD = ()=> {
    const d=new Date(),pad=n=>String(n).padStart(2,'0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  };
  const isToday = (ymd)=> ymd === todayYMD();
  const nowHHMMSS = ()=> {
    const d=new Date(),pad=n=>String(n).padStart(2,'0');
    return `${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
  };
  const disableForm = (b)=> submitBtn.disabled = b;

  /* Render Slots */
  async function loadBookedSlots(){
    const ngay = ngayInput.value;
    const branchId = parseInt(branchInput.value);
    hiddenInput.value = "";
    disableForm(true);
    container.innerHTML = "";
    slotMsg.textContent = "";

    if(!ngay || isNaN(branchId)){
      slotMsg.textContent = "Vui lòng chọn chi nhánh và ngày.";
      return;
    }

    // Loading state
    container.innerHTML = Array.from({length:6}).map(()=>
      `<div class="p-2 w-full rounded-md bg-slate-200 animate-pulse h-10 border border-slate-200"></div>`
    ).join('');

    try{
      const res = await fetch(`../controller/get_booked_slots.php?date=${encodeURIComponent(ngay)}&branch_id=${branchId}`);
      const booked = await res.json();

      container.innerHTML = "";
      const currentTime = nowHHMMSS();

      khungGio.forEach(gio=>{
        const btn = document.createElement("button");
        btn.type = "button";
        btn.innerText = gio.slice(0,5);
        btn.setAttribute("role","radio");
        btn.setAttribute("aria-checked","false");

        const isPastToday = isToday(ngay) && (gio <= currentTime);
        const isBooked = Array.isArray(booked) && booked.includes(gio);

        if(isBooked || isPastToday){
          btn.disabled = true;
        }
        btn.addEventListener("click",()=>{
          if(btn.disabled) return;
          container.querySelectorAll("button[role='radio']").forEach(b=>{
            b.setAttribute("aria-checked","false");
          });
          btn.setAttribute("aria-checked","true");
          hiddenInput.value = gio;
          disableForm(false);
          updateQuotePreview();
        });
        container.appendChild(btn);
      });

      if(!container.querySelector("button:not([disabled])")){
        slotMsg.textContent = "Hết chỗ trong ngày này. Vui lòng chọn ngày khác.";
      }
    }catch(e){
      slotMsg.textContent = "Không tải được khung giờ. Vui lòng thử lại.";
    }
  }

  /* Flatpickr */
  if (window.flatpickr) {
    flatpickr(ngayInput, {
      dateFormat: "Y-m-d",
      minDate: "today",
      disableMobile: true,
      onChange: loadBookedSlots
    });
  }
  ngayInput.addEventListener("change", loadBookedSlots);
  branchInput.addEventListener("change", loadBookedSlots);

  /* Dịch vụ & thiết bị */
  serviceSel.addEventListener("change", async function(){
    const selectedText = this.options[this.selectedIndex]?.text || "";
    if (selectedText.trim() === "Thuê trang thiết bị"){
      thietBiDiv.classList.remove("hidden");
      thietBiList.innerHTML = '<div class="sbk-hint">Đang tải danh sách thiết bị…</div>';
      try{
        const res = await fetch('../controller/get_trang_thiet_bi.php');
        const data = await res.json();
        thietBiList.innerHTML = '';
        data.forEach(item=>{
          const lbl = document.createElement("label");
          lbl.className = "flex items-center gap-2 cursor-pointer";
          lbl.innerHTML = `<input type="checkbox" name="thiet_bi_id[]" value="${item.ID_TB}"><span>${item.TEN_TB}</span>`;
          thietBiList.appendChild(lbl);
        });
      }catch(e){
        thietBiList.innerHTML = '<div class="text-amber-600 text-sm">Không tải được thiết bị.</div>';
      }
    }else{
      thietBiDiv.classList.add("hidden");
      thietBiList.innerHTML = "";
    }
    updateQuotePreview();
  });

  /* Tạm tính */
  async function updateQuotePreview(){
    const branchId = parseInt(branchInput.value);
    const date = ngayInput.value;
    const time = hiddenInput.value;
    const serviceId = parseInt(serviceSel.value || 0);
    const devices = Array.from(root.querySelectorAll("input[name='thiet_bi_id[]']:checked")).map(i=>parseInt(i.value));

    if(!branchId || !date || !time || !serviceId){
      quoteBox.classList.add("hidden");
      return;
    }
    quoteBox.classList.remove("hidden");
    quoteTotal.textContent = "Đang tính…";

    try{
      const res = await fetch('../controller/quote_preview.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ branch_id: branchId, date, time, service_id: serviceId, device_ids: devices })
      });
      const data = await res.json();
      quoteTotal.textContent = fmt(data?.total || 0);
    }catch(e){
      quoteTotal.textContent = "—";
    }
  }

  /* Submit safety */
  root.querySelector("#scheduleForm").addEventListener("submit", function(e){
    formMsg.textContent = "";
    if(!branchInput.value || !ngayInput.value || !hiddenInput.value || !serviceSel.value){
      e.preventDefault();
      formMsg.textContent = "Vui lòng điền đủ Chi nhánh, Ngày, Giờ và Dịch vụ.";
      return;
    }
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi…';
  });
})();
</script>
