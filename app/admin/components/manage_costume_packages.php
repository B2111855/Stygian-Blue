<?php
require_once '../../database/config.php';
require_once __DIR__ . '/../../repositories/PackageRepository.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Repositories\PackageRepository;

$role = (string)($_SESSION['ID_QUYEN'] ?? '');
$staffType = (string)($_SESSION['STAFF_TYPE'] ?? '');
$branchId = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : 0;
$isBranchManager = ($role === '2' && $staffType === 'quan_ly' && $branchId > 0);

$pkgRepo = new PackageRepository($conn);
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 6;
$offset = ($page - 1) * $limit;
$totalRows = $pkgRepo->countPackages($search);
$totalPages = max(1, (int)ceil($totalRows / $limit));
$packages = $pkgRepo->searchPackages($search, $limit, $offset, $isBranchManager ? $branchId : null);

$packageIds = array_map('intval', array_column($packages, 'ID_GOI'));
$costumesByPackage = [];
if (!empty($packageIds)) {
    $inClause = implode(',', $packageIds);
    $sql = "SELECT gtp.ID_GOI, gtp.ID_TRANG_PHUC, gtp.DISCOUNT_PERCENT, gtp.BAT_BUOC, gtp.THU_TU,"
         . " tp.TEN, tp.GIA_THUE, tp.TRANG_THAI, tp.MAU_SAC"
         . " FROM goi_trang_phuc gtp"
         . " JOIN trang_phuc tp ON tp.ID_TRANG_PHUC = gtp.ID_TRANG_PHUC"
         . " WHERE gtp.ID_GOI IN ($inClause)"
         . " ORDER BY gtp.ID_GOI, COALESCE(gtp.THU_TU, 999), tp.TEN";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $costumesByPackage[(int)$row['ID_GOI']][] = $row;
        }
    }
}

function renderCostumeBadge(array $row): string {
    return (int)$row['BAT_BUOC'] === 1 ? 'Bắt buộc' : 'Tùy chọn';
}

$baseUrl = '?page=package_costumes';
$searchParams = $search !== '' ? '&search=' . urlencode($search) : '';
?>
<body class="bg-gray-50 p-6">
  <div class="max-w-6xl mx-auto space-y-6">
    <header class="flex flex-col gap-3">
      <h1 class="text-3xl font-bold text-indigo-700">Quản lý gói trang phục</h1>
      <p class="text-sm text-gray-600">Hiển thị liên kết giữa gói dịch vụ và trang phục đã cấu hình. Chỉ hiện trang phục thuộc các gói bạn có quyền xem.</p>
      <div class="flex flex-wrap items-center gap-3">
        <form action="?" method="get" class="flex items-center gap-2 flex-wrap">
          <input type="hidden" name="page" value="package_costumes" />
          <input name="search" class="border border-gray-300 rounded px-3 py-2 w-64" placeholder="Tìm theo tên gói" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" />
          <button class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition">Tìm</button>
        </form>
        <a href="?page=packages" class="text-indigo-600 hover:text-indigo-800 text-sm">Quay lại quản lý gói dịch vụ →</a>
      </div>
    </header>

    <?php if (empty($packages)): ?>
      <div class="bg-white rounded-xl shadow border border-dashed border-gray-200 p-6 text-gray-500">
        Không có gói dịch vụ nào khớp với bộ lọc này.
      </div>
    <?php else: ?>
      <?php foreach ($packages as $pkg): ?>
        <?php
          $costumes = $costumesByPackage[$pkg['ID_GOI']] ?? [];
          $mandatory = array_filter($costumes, fn($row) => (int)$row['BAT_BUOC'] === 1);
          $optional = array_filter($costumes, fn($row) => (int)$row['BAT_BUOC'] === 0);
          $totalCount = count($costumes);
          $avgDiscount = $totalCount ? round(array_sum(array_column($costumes, 'DISCOUNT_PERCENT')) / $totalCount, 1) : 0;
        ?>
        <article class="bg-white rounded-2xl shadow-md overflow-hidden border border-gray-200">
          <div class="p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 class="text-xl font-semibold text-indigo-800"><?= htmlspecialchars($pkg['TEN_GOI']) ?></h2>
                <p class="text-sm text-gray-500"><?= htmlspecialchars($pkg['MO_TA']) ?></p>
              </div>
              <div class="flex items-center gap-4 text-xs uppercase tracking-wide">
                <span class="px-3 py-1 rounded-full bg-indigo-100 text-indigo-700"><?= htmlspecialchars($pkg['TRANG_THAI'] ?? 'nhap') ?></span>
                <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-600">Scope: <?= htmlspecialchars($pkg['SCOPE_TYPE'] ?? 'global') ?></span>
                <?php if (!empty($pkg['ID_CN_OWNER'])): ?>
                  <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-600">CN <?= (int)$pkg['ID_CN_OWNER'] ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-gray-600">
              <span><strong><?= $totalCount ?></strong> trang phục</span>
              <span><strong><?= count($mandatory) ?></strong> bắt buộc</span>
              <span><strong><?= count($optional) ?></strong> tùy chọn</span>
              <?php if ($totalCount > 0): ?>
                <span><strong><?= $avgDiscount ?>%</strong> chiết khấu trung bình</span>
              <?php endif; ?>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <?php if ($totalCount === 0): ?>
                <div class="col-span-full rounded-xl border border-dashed border-indigo-200 bg-indigo-50 p-4 text-center text-sm text-indigo-700">Chưa có trang phục nào được gán.</div>
              <?php else: ?>
                <?php foreach ($costumes as $row): ?>
                  <div class="rounded-2xl border border-gray-100 p-4 bg-gray-50 space-y-2">
                    <div class="flex items-center justify-between text-xs uppercase tracking-widest text-gray-500">
                      <span><?= renderCostumeBadge($row) ?></span>
                      <span class="font-semibold text-indigo-700"><?= (int)$row['DISCOUNT_PERCENT'] ?>% giảm</span>
                    </div>
                    <div>
                      <p class="font-semibold text-gray-800"><?= htmlspecialchars($row['TEN']) ?></p>
                      <p class="text-xs text-gray-500">ID trang phục #<?= (int)$row['ID_TRANG_PHUC'] ?></p>
                    </div>
                    <div class="flex items-center justify-between text-sm text-gray-700">
                      <span>Giá thuê: <strong><?= number_format((int)$row['GIA_THUE'], 0, ',', '.') ?>₫</strong></span>
                      <span class="px-2 py-0.5 rounded-full text-[11px] bg-white border border-gray-200"><?= htmlspecialchars($row['TRANG_THAI']) ?></span>
                    </div>
                    <?php if (!empty($row['MAU_SAC'])): ?>
                      <p class="text-xs text-gray-400">Màu sắc: <?= htmlspecialchars($row['MAU_SAC']) ?></p>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>

      <?php if ($totalPages > 1): ?>
        <div class="flex flex-wrap items-center justify-center gap-2">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="<?= $baseUrl ?>&p=<?= $i ?><?= $searchParams ?>" class="px-3 py-1 rounded-full border text-xs font-medium <?= $i === $page ? 'bg-indigo-600 text-white border-transparent' : 'bg-white text-indigo-600 border-indigo-200' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
