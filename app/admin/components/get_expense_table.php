<?php
include '../../../database/config.php';

$branch = $_GET['branch'] ?? 'all';
$month = date('Y-m');

if (!preg_match('/^cn(\d+)$/', $branch, $matches)) {
    // Không chọn chi nhánh -> hiển thị tổng hợp chi phí theo loại
    $query = "SELECT TEN_CP, SUM(GIA_TRI) AS GIA_TRI, MAX(NGAY_GIO) AS NGAY_GIO
              FROM chi_phi_phat_sinh
              WHERE DATE_FORMAT(NGAY_GIO, '%Y-%m') = '$month'
              GROUP BY TEN_CP";

    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 0) {
        echo "<p class='text-gray-500 italic'>Chưa có chi phí phát sinh trong tháng này.</p>";
        exit;
    }
    ?>

    <table class="w-full text-sm border border-gray-300">
        <thead class="bg-gray-100">
            <tr>
                <th class="border px-4 py-2">Tên Chi Phí</th>
                <th class="border px-4 py-2">Tổng Giá Trị (VND)</th>
                <th class="border px-4 py-2">Ngày Tính</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td class="border px-4 py-2"><?= htmlspecialchars($row['TEN_CP']) ?></td>
                    <td class="border px-4 py-2 text-blue-800 font-semibold"><?= number_format($row['GIA_TRI'], 0, ',', '.') ?></td>
                    <td class="border px-4 py-2"><?= date('d/m/Y', strtotime($row['NGAY_GIO'])) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <?php
    exit;
}

// Nếu có chọn chi nhánh cụ thể:
$id_cn = (int)$matches[1];
$query = "SELECT TEN_CP, GIA_TRI, NGAY_GIO 
          FROM chi_phi_phat_sinh 
          WHERE ID_CN = $id_cn AND DATE_FORMAT(NGAY_GIO, '%Y-%m') = '$month'";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    echo "<p class='text-gray-500 italic'>Chưa có chi phí phát sinh trong tháng này.</p>";
    exit;
}
?>

<table class="w-full text-sm border border-gray-300">
    <thead class="bg-gray-100">
        <tr>
            <th class="border px-4 py-2">Tên Chi Phí</th>
            <th class="border px-4 py-2">Giá Trị (VND)</th>
            <th class="border px-4 py-2">Ngày Ghi Nhận</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td class="border px-4 py-2"><?= htmlspecialchars($row['TEN_CP']) ?></td>
                <td class="border px-4 py-2"><?= number_format($row['GIA_TRI'], 0, ',', '.') ?></td>
                <td class="border px-4 py-2"><?= date('d/m/Y', strtotime($row['NGAY_GIO'])) ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
