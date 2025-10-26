<?php
// Include database connection file
include '../../database/config.php';

// Fetch feedback from the database
$query = "
    SELECT 
        kh.HO_TEN AS 'Tên Khách Hàng',
        dv.TEN_DV AS 'Dịch Vụ',
        ph.NOI_DUNG AS 'Nội Dung Phản Hồi',
        ph.XEP_HANG_DV AS 'Đánh Giá',
        ph.NGAY_GUI AS 'Ngày Gửi'
    FROM 
        phan_hoi_cua_khach_hang ph
    JOIN 
        khach_hang kh ON ph.ID_TK = kh.ID_TK
    JOIN 
        dich_vu dv ON ph.ID_DV = dv.ID_DV
";

$result = mysqli_query($conn, $query);

// Check for errors
if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

?>


<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen p-8 font-sans text-gray-800">

    <div class="max-w-6xl mx-auto bg-white shadow-xl rounded-xl p-8 fade-in">
        <h1 class="text-3xl font-bold text-indigo-700 text-center mb-6">💬 Phản Hồi Khách Hàng</h1>

        <!-- Bảng phản hồi -->
        <div class="overflow-x-auto rounded-lg shadow border border-gray-200">
            <table class="min-w-full text-sm text-center bg-white">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-6 py-3">Tên Khách Hàng</th>
                        <th class="px-6 py-3">Dịch Vụ</th>
                        <th class="px-6 py-3">Nội Dung Phản Hồi</th>
                        <th class="px-6 py-3">Đánh Giá</th>
                        <th class="px-6 py-3">Ngày Gửi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-gray-800">
                    <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                        <tr class="hover:bg-indigo-50 transition">
                            <td class="px-6 py-4 font-medium"><?= $row['Tên Khách Hàng']; ?></td>
                            <td class="px-6 py-4"><?= $row['Dịch Vụ']; ?></td>
                            <td class="px-6 py-4 text-left"><?= $row['Nội Dung Phản Hồi']; ?></td>
                            <td class="px-6 py-4 text-yellow-500 text-lg font-semibold">
                                <?php for ($i = 0; $i < $row['Đánh Giá']; $i++) echo "★"; ?>
                                <?php for ($i = $row['Đánh Giá']; $i < 5; $i++) echo "☆"; ?>
                            </td>
                            <td class="px-6 py-4 text-gray-500"><?= $row['Ngày Gửi']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Hiệu ứng fade-in -->
    <style>
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fade-in 0.4s ease-out both;
        }
    </style>
</body>
</html>

<?php
// Close database connection
mysqli_close($conn);
?>
