<?php
include '../../database/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$staff_id = $_SESSION['ID_TK'] ?? null;

$query = "
    SELECT lh.ID_LICHHEN, kh.HO_TEN AS ten_khach_hang, dv.TEN_DV AS ten_dich_vu,
           lh.THOI_GIAN_BAT_DAU, pc.THOI_GIAN_KET_THUC, lh.DIA_CHI_HEN, lh.TRANGTHAI
    FROM phan_cong_nhan_vien pc
    JOIN lich_hen lh ON pc.ID_LICHHEN = lh.ID_LICHHEN
    JOIN khach_hang kh ON lh.ID_TK = kh.ID_TK
    JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
    WHERE pc.ID_TK = ?
    ORDER BY lh.THOI_GIAN_BAT_DAU DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

function getRequestStatus($id_lichhen, $id_tk)
{
    global $conn;
    $stmt = $conn->prepare("SELECT TRANGTHAI FROM yeu_cau_thay_doi_lich WHERE ID_LICHHEN = ? AND ID_TK = ? ORDER BY NGAY_GUI DESC LIMIT 1");
    if ($stmt === false) return null;
    $stmt->bind_param("is", $id_lichhen, $id_tk);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['TRANGTHAI'];
    }
    return null;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lịch làm việc cá nhân</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes slideUpFade {
            0% { opacity: 0; transform: translateY(8px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: slideUpFade 0.4s ease-out both;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen p-8 font-sans text-gray-800">
<div class="max-w-7xl mx-auto fade-in">
    <header class="mb-10 text-center">
        <h1 class="text-4xl font-extrabold text-indigo-700 mb-2">🗓️Lịch làm việc cá nhân</h1>
        <p class="text-gray-600 text-lg">Chi tiết các lịch hẹn bạn đã được phân công</p>
    </header>

    <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-200">
        <?php if ($result->num_rows === 0): ?>
            <div class="p-6 text-center text-red-500 text-lg font-medium">
                Bạn chưa có lịch phân công nào!
            </div>
        <?php else: ?>
            <table class="w-full text-sm text-center">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Khách hàng</th>
                        <th class="px-4 py-3">Dịch vụ</th>
                        <th class="px-4 py-3">Bắt đầu</th>
                        <th class="px-4 py-3">Kết thúc</th>
                        <th class="px-4 py-3">Địa điểm</th>
                        <th class="px-4 py-3">Yêu cầu đổi</th>
                        <th class="px-4 py-3">Hoàn thành</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php $status = getRequestStatus($row['ID_LICHHEN'], $staff_id); ?>
                        <tr class="hover:bg-indigo-50 transition duration-150">
                            <td class="py-3 px-4 font-semibold text-indigo-700"><?= $row['ID_LICHHEN'] ?></td>
                            <td class="py-3 px-4"><?= $row['ten_khach_hang'] ?></td>
                            <td class="py-3 px-4"><?= $row['ten_dich_vu'] ?></td>
                            <td class="py-3 px-4"><?= $row['THOI_GIAN_BAT_DAU'] ?></td>
                            <td class="py-3 px-4"><?= $row['THOI_GIAN_KET_THUC'] ?></td>
                            <td class="py-3 px-4"><?= $row['DIA_CHI_HEN'] ?></td>
                            <td class="py-3 px-4">
                                <?php
                                if ($status === 'Chờ duyệt') {
                                    echo '<span class="text-yellow-600 font-medium">🕒 Đang chờ duyệt</span>';
                                    echo '<form method="POST" action="huy_yeu_cau.php" class="inline ml-2">';
                                    echo '<input type="hidden" name="id_lichhen" value="' . $row['ID_LICHHEN'] . '">';
                                    echo '<button type="submit" class="text-red-500 hover:underline text-sm font-semibold">Hủy</button>';
                                    echo '</form>';
                                } elseif ($status === 'Đã duyệt') {
                                    echo '<span class="text-green-600 font-medium">✅ Đã duyệt</span>';
                                } elseif ($status === 'Từ chối') {
                                    echo '<span class="text-red-600 font-medium">❌ Bị từ chối</span>';
                                } else {
                                    echo '<form method="POST" action="./components/gui_yeu_cau_thay_doi.php" class="flex flex-col items-center gap-1 md:flex-row md:justify-center">';
                                    echo '<input type="hidden" name="id_lichhen" value="' . $row['ID_LICHHEN'] . '">';
                                    echo '<input type="text" name="noidung" placeholder="Lý do" class="border border-gray-300 rounded px-2 py-1 text-sm focus:ring-indigo-500 focus:border-indigo-500 transition w-full md:w-auto" required>';
                                    echo '<button type="submit" class="bg-indigo-500 text-white rounded px-3 py-1 text-sm font-medium hover:bg-indigo-600 transition">Gửi</button>';
                                    echo '</form>';
                                }
                                ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php
                                if ($row['TRANGTHAI'] === 'Đã hoàn thành') {
                                    echo '<span class="text-green-700 font-semibold">✔ Hoàn tất</span>';
                                } else {
                                    echo '<form method="POST" action="./components/gui_xac_nhan_hoan_thanh.php">';
                                    echo '<input type="hidden" name="id_lichhen" value="' . $row['ID_LICHHEN'] . '">';
                                    echo '<button type="submit" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 text-sm">Tôi đã hoàn thành</button>';
                                    echo '</form>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
