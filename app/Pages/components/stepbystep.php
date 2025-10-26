<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Steps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(to bottom right, #1e3a8a, #f1f5f9);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .service-steps-container {
            background: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 20px;
            max-width: 1290px; /* Increased width by 100px */
            width: 100%;
            margin: 20px;
            margin-top: 160px;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .service-steps-container.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .steps {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        @media (min-width: 768px) {
            .steps {
                flex-direction: row;
                justify-content: space-between;
            }
        }
        .step {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        .step:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }
        .step-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-bottom: 10px;
            font-size: 24px;
            font-weight: bold;
            position: absolute;
            top: -28px;
            left: 50%;
            transform: translateX(-50%);
        }
        .step-icon.bg-blue-500 { background: #3b82f6; }
        .step-icon.bg-green-500 { background: #10b981; }
        .step-icon.bg-yellow-500 { background: #f59e0b; }
        .step-icon.bg-red-500 { background: #ef4444; }
        .step-icon.bg-purple-500 { background: #8b5cf6; }
        .step-icon.bg-indigo-500 { background: #6366f1; }
        h2 {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            text-align: center;
            margin-bottom: 40px; /* Added margin-bottom */
        }
        h3 {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        p {
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Service Steps (SCOPED) -->
<section data-sb-steps>
  <div class="sb-steps-wrap">
    <!-- Header -->
    <header class="sb-steps-head">
      <span class="pill">
        <i class="fas fa-shoe-prints"></i> Quy trình dịch vụ
      </span>
      <h2 class="title">Các Bước Sử Dụng Dịch Vụ</h2>
      <p class="sub">Rõ ràng · Nhanh gọn · Trải nghiệm mượt</p>
    </header>

    <!-- Steps -->
    <ol class="steps" aria-label="Các bước sử dụng dịch vụ">
      <li class="step">
        <div class="ico bg-blue"><i class="fas fa-concierge-bell"></i></div>
        <h3 class="name">Chọn Dịch Vụ</h3>
        <p class="desc">Lựa chọn dịch vụ mong muốn trên hệ thống hoặc tại cửa hàng.</p>
      </li>
      <li class="step">
        <div class="ico bg-green"><i class="fas fa-calendar-alt"></i></div>
        <h3 class="name">Đặt Lịch Hẹn</h3>
        <p class="desc">Điền thông tin & thời gian, nhân viên sẽ liên hệ xác nhận.</p>
      </li>
      <li class="step">
        <div class="ico bg-amber"><i class="fas fa-file-invoice"></i></div>
        <h3 class="name">Xuất Hóa Đơn</h3>
        <p class="desc">Hóa đơn gửi qua email hoặc nhận trực tiếp tại studio.</p>
      </li>
      <li class="step">
        <div class="ico bg-red"><i class="fas fa-credit-card"></i></div>
        <h3 class="name">Thanh Toán</h3>
        <p class="desc">Chuyển khoản, ví điện tử hoặc thanh toán tại quầy.</p>
      </li>
      <li class="step">
        <div class="ico bg-purple"><i class="fas fa-camera"></i></div>
        <h3 class="name">Chụp Ảnh</h3>
        <p class="desc">Buổi chụp chuyên nghiệp với ekip Stygian Blue.</p>
      </li>
      <li class="step">
        <div class="ico bg-indigo"><i class="fas fa-star"></i></div>
        <h3 class="name">Đánh Giá</h3>
        <p class="desc">Để lại phản hồi giúp chúng tôi cải thiện chất lượng.</p>
      </li>
    </ol>
  </div>
</section>

<!-- Icons (optional if chưa có ở layout) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<style>
/* =========================
   SCOPED TO [data-sb-steps]
   ========================= */
[data-sb-steps]{
  --bg: linear-gradient(135deg,#0ea5e91a,#a78bfa1a,#f472b61a);
  --card-bg: #ffffff;
  --card-br: #e5e7eb;
  --text: #0f172a;
  --muted: #475569;
  --ring: #06b6d4;

  --blue:#3b82f6; --green:#10b981; --amber:#f59e0b;
  --red:#ef4444; --purple:#8b5cf6; --indigo:#6366f1;

  display:block; position:relative; margin:24px; margin-top:100px;
  font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
  color:var(--text);
}
@media (max-width:640px){ [data-sb-steps]{ margin:16px; margin-top:80px; } }

[data-sb-steps] .sb-steps-wrap{
  background: var(--bg), #f8fafc;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 10px 30px rgba(2,6,23,.08);
  overflow:hidden;
  margin-top: 150px;
}

/* Header */
[data-sb-steps] .sb-steps-head{ text-align:center; margin-bottom:50px; }
[data-sb-steps] .pill{
  display:inline-flex; align-items:center; gap:.5rem;
  font-size:12px; color:#0c4a6e;
  background:#e0f2fe; border:1px solid #bae6fd;
  border-radius:9999px; padding:.4rem .75rem;
}
[data-sb-steps] .pill i{ color:#0284c7; }
[data-sb-steps] .title{
  margin:10px 0 4px; font-size: clamp(20px,2.4vw,28px);
  font-weight:800; letter-spacing:.2px;
  background:linear-gradient(90deg,#0ea5e9,#a78bfa); -webkit-background-clip:text; background-clip:text; color:transparent;
}
[data-sb-steps] .sub{ margin:0; font-size:14px; color:#475569; }

/* Grid steps */
[data-sb-steps] .steps{
  display:grid; gap:16px; margin-top:40px;
  grid-template-columns: repeat(1,minmax(0,1fr));
}
@media (min-width:768px){
  [data-sb-steps] .steps{ grid-template-columns: repeat(3,minmax(0,1fr)); }
}
@media (min-width:1280px){
  [data-sb-steps] .steps{ grid-template-columns: repeat(6,minmax(0,1fr)); }
}

/* Card */
[data-sb-steps] .step{
  position:relative; background:var(--card-bg);
  border:1px solid var(--card-br); border-radius:14px;
  padding:18px 14px 16px; text-align:center;
  transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
  box-shadow: 0 1px 0 rgba(2,6,23,.04);
  outline: none;
}
[data-sb-steps] .step:focus-visible{
  box-shadow: 0 0 0 3px color-mix(in srgb,var(--ring) 35%, transparent);
  border-color: color-mix(in srgb,var(--ring) 60%, var(--card-br));
}
[data-sb-steps] .step:hover{
  transform: translateY(-2px);
  box-shadow: 0 10px 24px rgba(2,6,23,.10);
}

/* Icon bubble */
[data-sb-steps] .ico{
  width:48px; height:48px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  color:#fff; font-size:20px; font-weight:700;
  position:absolute; top:-24px; left:50%; transform:translateX(-50%);
  box-shadow: 0 6px 16px rgba(2,6,23,.15); border:2px solid #fff;
}
[data-sb-steps] .bg-blue{background:var(--blue)}
[data-sb-steps] .bg-green{background:var(--green)}
[data-sb-steps] .bg-amber{background:var(--amber)}
[data-sb-steps] .bg-red{background:var(--red)}
[data-sb-steps] .bg-purple{background:var(--purple)}
[data-sb-steps] .bg-indigo{background:var(--indigo)}

/* Texts */
[data-sb-steps] .name{
  margin:22px 0 6px; font-size:16px; font-weight:800; color:var(--text);
}
[data-sb-steps] .desc{
  margin:0; font-size:13px; line-height:1.45; color:var(--muted);
}

/* Connectors (desktop) */
@media (min-width:1280px){
  [data-sb-steps] .steps{ counter-reset: stp; }
  [data-sb-steps] .step::after{
    content:''; position:absolute; top:24px; right:-8px; width:16px; height:2px;
    background: linear-gradient(90deg,#0ea5e9,#a78bfa);
    opacity:.35;
  }
  [data-sb-steps] .step:last-child::after{ display:none; }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce){
  [data-sb-steps] .step, [data-sb-steps] .ico{ transition:none !important; }
}

/* Appear animation (scoped) */
[data-sb-steps] .step{ opacity:0; transform: translateY(10px); }
[data-sb-steps].is-visible .step{ opacity:1; transform:none; transition:opacity .5s ease, transform .5s ease; }
[data-sb-steps].is-visible .step:nth-child(1){ transition-delay:.02s }
[data-sb-steps].is-visible .step:nth-child(2){ transition-delay:.06s }
[data-sb-steps].is-visible .step:nth-child(3){ transition-delay:.10s }
[data-sb-steps].is-visible .step:nth-child(4){ transition-delay:.14s }
[data-sb-steps].is-visible .step:nth-child(5){ transition-delay:.18s }
[data-sb-steps].is-visible .step:nth-child(6){ transition-delay:.22s }
</style>

<script>
/* SCOPED JS: chỉ ảnh hưởng [data-sb-steps] */
(function(){
  const root = document.querySelector('[data-sb-steps]');
  if(!root) return;

  // Appear on view
  const onView = new IntersectionObserver((entries)=>{
    entries.forEach(e=>{ if(e.isIntersecting){ root.classList.add('is-visible'); } });
  }, {threshold:.15});
  onView.observe(root);

  // Accessible hover effects without inline styles
  root.querySelectorAll('.step').forEach(step=>{
    step.addEventListener('mouseenter', ()=> step.classList.add('hovering'));
    step.addEventListener('mouseleave', ()=> step.classList.remove('hovering'));
  });
})();
</script>

</body>
</html>