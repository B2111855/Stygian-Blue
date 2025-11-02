<!-- trang dat lich.php -->
<?php
include '../components/auth_state_boot.php';
include '../components/auth_check.php';
include '../components/header.php';
include '../components/thongbao.php';
?>

<main class="relative min-h-screen bg-slate-50 pt-28 pb-20 px-4 sm:px-6 lg:px-10" role="main">
  <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-24 -z-10">
    <div class="mx-auto h-72 w-full max-w-none -translate-y-16 rounded-full bg-gradient-to-r from-sky-100 via-indigo-100 to-violet-100 blur-3xl"></div>
  </div>

  <div class="relative mx-auto flex w-full max-w-none flex-col gap-12">
    <header class="mx-auto text-center">
      <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">Luôn sẵn sàng hỗ trợ</p>
      <h1 class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl">Đặt lịch làm việc với Stygian Blue</h1>
      <p class="mt-4 text-base text-slate-600">Chọn dịch vụ lẻ hoặc gói dịch vụ trọn bộ, xác nhận khung giờ phù hợp và đội ngũ của chúng tôi sẽ liên hệ ngay.</p>
    </header>

    <section aria-label="Các bước đặt lịch" class="w-full">
      <?php include '../components/stepbystep.php'; ?>
    </section>

    <section aria-label="Bản đồ và biểu mẫu đặt lịch" class="w-full">
      <?php include '../components/lienhe.php'; ?>
    </section>
  </div>
</main>

<?php include '../components/footer.php'; ?>
<?php include '../components/chat_widget.php'; ?>
</main>
