<?php
include '../../../database/config.php';

// Accept both GET (legacy) and POST (panel) parameters
$branch = $_GET['branch'] ?? $_POST['branch_id'] ?? 'all';
$month = date('Y-m');

if (!preg_match('/^cn(\d+)$/', $branch, $matches)) {
    // Không chọn chi nhánh -> hiển thị tổng hợp chi phí theo loại
    $query = "SELECT TEN_CP, SUM(GIA_TRI) AS GIA_TRI, MAX(NGAY_GIO) AS NGAY_GIO, ID_CN
              FROM chi_phi_phat_sinh
              WHERE DATE_FORMAT(NGAY_GIO, '%Y-%m') = '$month'
              GROUP BY TEN_CP";

    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 0) {
        echo "<p class='text-gray-500 italic'>Chưa có chi phí phát sinh trong tháng này.</p>";
        exit;
    }
    ?>

    <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-300">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border px-4 py-2 text-left">Tên Chi Phí</th>
                    <th class="border px-4 py-2 text-right">Tổng Giá Trị (VND)</th>
                    <th class="border px-4 py-2 text-left">Ngày Tính</th>
                    <th class="border px-4 py-2 text-center">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="border px-4 py-2"><?= htmlspecialchars($row['TEN_CP']) ?></td>
                        <td class="border px-4 py-2 text-right font-semibold"><?= number_format($row['GIA_TRI'], 0, ',', '.') ?></td>
                        <td class="border px-4 py-2"><?= date('d/m/Y', strtotime($row['NGAY_GIO'])) ?></td>
                        <td class="border px-4 py-2 text-center">
                            <button onclick="editPanelExpense('<?= htmlspecialchars($row['TEN_CP']) ?>', <?= (float)$row['GIA_TRI'] ?>, '')" class="px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 rounded">Sửa</button>
                            <button onclick="deletePanelExpense('<?= htmlspecialchars($row['TEN_CP']) ?>', '<?= $row['ID_CN'] ?>')" class="px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 rounded ml-1">Xóa</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php
    exit;
}

// Nếu có chọn chi nhánh cụ thể:
$id_cn = (int)$matches[1];
$query = "SELECT TEN_CP, GIA_TRI, NGAY_GIO, MOTA_CP
          FROM chi_phi_phat_sinh 
          WHERE ID_CN = $id_cn AND DATE_FORMAT(NGAY_GIO, '%Y-%m') = '$month'
          ORDER BY TEN_CP";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    echo "<p class='text-gray-500 italic'>Chưa có chi phí phát sinh trong tháng này.</p>";
    exit;
}
?>

<div class="overflow-x-auto">
    <table class="w-full text-sm border border-gray-300">
        <thead class="bg-gray-100">
            <tr>
                <th class="border px-4 py-2 text-left">Tên Chi Phí</th>
                <th class="border px-4 py-2 text-right">Giá Trị (VND)</th>
                <th class="border px-4 py-2 text-left">Ghi Chú</th>
                <th class="border px-4 py-2 text-left">Ngày Ghi Nhận</th>
                <th class="border px-4 py-2 text-center">Thao Tác</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-gray-50">
                    <td class="border px-4 py-2"><?= htmlspecialchars($row['TEN_CP']) ?></td>
                    <td class="border px-4 py-2 text-right font-medium"><?= number_format($row['GIA_TRI'], 0, ',', '.') ?></td>
                    <td class="border px-4 py-2 text-gray-600"><?= htmlspecialchars($row['MOTA_CP'] ?? '') ?></td>
                    <td class="border px-4 py-2"><?= date('d/m/Y', strtotime($row['NGAY_GIO'])) ?></td>
                    <td class="border px-4 py-2 text-center">
                        <button onclick="editPanelExpense('<?= htmlspecialchars($row['TEN_CP']) ?>', <?= (float)$row['GIA_TRI'] ?>, '<?= htmlspecialchars($row['MOTA_CP'] ?? '') ?>')" class="px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 rounded">Sửa</button>
                        <button onclick="deletePanelExpense('<?= htmlspecialchars($row['TEN_CP']) ?>', <?= $id_cn ?>)" class="px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 rounded ml-1">Xóa</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
