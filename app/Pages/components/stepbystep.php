<section data-sb-steps aria-labelledby="sb-steps-title" class="h-full">
  <div class="flex h-full flex-col gap-10 rounded-3xl border border-slate-200/70 bg-white/90 p-8 shadow-xl backdrop-blur">
    <header class="text-center">
      <p class="text-xs font-semibold uppercase tracking-[0.25em] text-sky-600">Quy trình dịch vụ</p>
      <h2 id="sb-steps-title" class="mt-3 text-2xl font-bold text-slate-900">Các bước sử dụng dịch vụ</h2>
      <p class="mt-2 text-sm text-slate-600">Rõ ràng, nhanh gọn và luôn đặt trải nghiệm của bạn lên hàng đầu.</p>
    </header>

    <div class="overflow-x-auto pb-2" role="presentation">
      <ol class="flex min-w-max gap-6" aria-label="Các bước sử dụng dịch vụ">
        <li class="flex w-64 flex-col items-center rounded-2xl border border-slate-200/80 bg-white/90 p-6 text-center shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
          <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-r from-sky-500 via-indigo-500 to-violet-500 text-sm font-semibold text-white shadow-md">1</span>
          <h3 class="text-base font-semibold text-slate-900">Chọn dịch vụ</h3>
          <p class="mt-2 text-sm text-slate-600">Lựa chọn dịch vụ mong muốn trên hệ thống hoặc tại cửa hàng.</p>
        </li>
        <li class="flex w-64 flex-col items-center rounded-2xl border border-slate-200/80 bg-white/90 p-6 text-center shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
          <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-r from-sky-500 via-indigo-500 to-violet-500 text-sm font-semibold text-white shadow-md">2</span>
          <h3 class="text-base font-semibold text-slate-900">Đặt lịch hẹn</h3>
          <p class="mt-2 text-sm text-slate-600">Điền thông tin và thời gian, đội ngũ sẽ xác nhận trong thời gian sớm nhất.</p>
        </li>
        <li class="flex w-64 flex-col items-center rounded-2xl border border-slate-200/80 bg-white/90 p-6 text-center shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
          <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-r from-sky-500 via-indigo-500 to-violet-500 text-sm font-semibold text-white shadow-md">3</span>
          <h3 class="text-base font-semibold text-slate-900">Xuất hóa đơn</h3>
          <p class="mt-2 text-sm text-slate-600">Hóa đơn được gửi qua email hoặc nhận trực tiếp tại studio theo yêu cầu.</p>
        </li>
        <li class="flex w-64 flex-col items-center rounded-2xl border border-slate-200/80 bg-white/90 p-6 text-center shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
          <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-r from-sky-500 via-indigo-500 to-violet-500 text-sm font-semibold text-white shadow-md">4</span>
          <h3 class="text-base font-semibold text-slate-900">Thanh toán</h3>
          <p class="mt-2 text-sm text-slate-600">Hỗ trợ chuyển khoản, ví điện tử hoặc thanh toán tại quầy.</p>
        </li>
        <li class="flex w-64 flex-col items-center rounded-2xl border border-slate-200/80 bg-white/90 p-6 text-center shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
          <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-r from-sky-500 via-indigo-500 to-violet-500 text-sm font-semibold text-white shadow-md">5</span>
          <h3 class="text-base font-semibold text-slate-900">Thực hiện buổi chụp</h3>
          <p class="mt-2 text-sm text-slate-600">Trải nghiệm buổi chụp chuyên nghiệp với ekip Stygian Blue.</p>
        </li>
        <li class="flex w-64 flex-col items-center rounded-2xl border border-slate-200/80 bg-white/90 p-6 text-center shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
          <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-r from-sky-500 via-indigo-500 to-violet-500 text-sm font-semibold text-white shadow-md">6</span>
          <h3 class="text-base font-semibold text-slate-900">Đánh giá dịch vụ</h3>
          <p class="mt-2 text-sm text-slate-600">Gửi phản hồi để chúng tôi hoàn thiện dịch vụ từng ngày.</p>
        </li>
      </ol>
    </div>
  </div>
</section>
<style>
  [data-sb-steps] {
    display: block;
    width: 100%;
  }

  [data-sb-steps] .sb-steps__container {
    margin: 0 auto;
    width: min(1100px, 100%);
    padding: clamp(1.5rem, 3vw, 2.5rem);
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.08), rgba(167, 139, 250, 0.08));
    border-radius: 1.25rem;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
  }

  [data-sb-steps] .sb-steps__header {
    text-align: center;
    margin-bottom: clamp(1.75rem, 4vw, 2.5rem);
  }

  [data-sb-steps] .sb-steps__eyebrow {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem 0.85rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: #0c4a6e;
    background: rgba(224, 242, 254, 0.9);
    border: 1px solid rgba(186, 230, 253, 0.9);
    text-transform: uppercase;
  }

  [data-sb-steps] .sb-steps__title {
    margin: 0.75rem 0 0.25rem;
    font-size: clamp(1.75rem, 4vw, 2.25rem);
    font-weight: 800;
    letter-spacing: 0.015em;
    color: #0f172a;
  }

  [data-sb-steps] .sb-steps__subtitle {
    margin: 0;
    font-size: 0.95rem;
    color: #475569;
  }

  [data-sb-steps] .sb-steps__list {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 1.5rem;
    list-style: none;
    padding: 0;
    margin: 0;
  }

  [data-sb-steps] .sb-steps__item {
    position: relative;
    padding: 2.75rem 1.5rem 1.75rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 1rem;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
  }

  [data-sb-steps] .sb-steps__item:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
  }

  [data-sb-steps] .sb-steps__badge {
    position: absolute;
    top: -1.75rem;
    left: 50%;
    transform: translateX(-50%);
    display: grid;
    place-items: center;
    width: 3.25rem;
    height: 3.25rem;
    border-radius: 9999px;
    font-weight: 700;
    font-size: 1.125rem;
    color: #ffffff;
    background: linear-gradient(135deg, #0ea5e9, #6366f1);
    box-shadow: 0 12px 30px rgba(14, 165, 233, 0.35);
  }

  [data-sb-steps] .sb-steps__name {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    text-align: center;
  }

  [data-sb-steps] .sb-steps__desc {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.6;
    color: #475569;
    text-align: center;
  }

  @media (min-width: 640px) {
    [data-sb-steps] .sb-steps__list {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (min-width: 1024px) {
    [data-sb-steps] .sb-steps__list {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    [data-sb-steps] .sb-steps__item {
      padding: 3rem 2rem 2rem;
    }
  }
</style>