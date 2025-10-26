<?php
include '../../database/config.php';

// Lấy ID_TK đang đăng nhập
$idTk = $_SESSION['ID_TK'] ?? null;

// Tìm ID_CN từ bảng nhan_vien
$stmt = $conn->prepare("SELECT ID_CN FROM nhan_vien WHERE ID_TK = ?");
$stmt->bind_param("i", $idTk);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
  $idCn = $row['ID_CN'];
} else {
  die("<p class='text-red-600'>Lỗi: Không xác định được chi nhánh của nhân viên!</p>");
}

$month = date('Y-m');
$expenseQuery = $conn->prepare("SELECT TEN_CP, GIA_TRI FROM chi_phi_phat_sinh WHERE ID_CN = ? AND DATE_FORMAT(NGAY_GIO, '%Y-%m') = ?");
$expenseQuery->bind_param("is", $idCn, $month);
$expenseQuery->execute();
$expenseResult = $expenseQuery->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Cập nhật chi phí</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen py-10 px-4">
  <div class="max-w-3xl mx-auto bg-white p-8 rounded-2xl shadow-xl fade-in">
    <h1 class="text-3xl font-extrabold text-center text-indigo-700 mb-8">📋 Cập Nhật Chi Phí Chi Nhánh</h1>

    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 text-center shadow">
        ✅ Lưu chi phí thành công!
      </div>
    <?php endif; ?>

    <form action="./components/save_expenses.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
      <input type="hidden" name="branch" value="cn<?= $idCn ?>">

      <div class="col-span-1">
        <label class="block text-sm font-semibold text-gray-700 mb-1">🏢 Chi phí mặt bằng (VND)</label>
        <input type="number" name="rent" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring focus:ring-indigo-300" min="0" required>
      </div>

      <div class="col-span-1">
        <label class="block text-sm font-semibold text-gray-700 mb-1">💡 Chi phí điện nước (VND)</label>
        <input type="number" name="utilities" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring focus:ring-indigo-300" min="0" required>
      </div>

      <div class="col-span-2 text-sm text-gray-500 italic">Thuế (10% doanh thu) sẽ được tính tự động sau khi lưu.</div>

      <div class="col-span-2">
        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 rounded-lg shadow transition">
          💾 Lưu chi phí
        </button>
      </div>
    </form>

    <div>
      <h2 class="text-xl font-bold text-gray-800 mb-4">💸 Chi phí đã nhập tháng <?= date('m/Y') ?>:</h2>
      <?php if ($expenseResult->num_rows > 0): ?>
        <table class="w-full border border-gray-300 text-sm text-gray-800">
          <thead class="bg-indigo-100">
            <tr>
              <th class="border px-4 py-2 text-left">Tên chi phí</th>
              <th class="border px-4 py-2 text-right">Giá trị (VND)</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $expenseResult->fetch_assoc()): ?>
              <tr class="hover:bg-indigo-50">
                <td class="border px-4 py-2 font-medium text-gray-700"><?= htmlspecialchars($row['TEN_CP']) ?></td>
                <td class="border px-4 py-2 text-right text-indigo-700 font-semibold"><?= number_format($row['GIA_TRI'], 0, ',', '.') ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-gray-500 italic">Chưa có chi phí phát sinh trong tháng này.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>