<?php
// Include the database connection
include '../../../database/config.php';

// Fetch services from the database for the selection dropdown
$servicesQuery = "SELECT * FROM dich_vu";
$servicesResult = mysqli_query($conn, $servicesQuery);

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tk = $_POST['ID_TK']; // Assuming the customer's ID is stored in session or passed via POST
    $id_dv = $_POST['ID_DV'];
    $noi_dung = $_POST['NOI_DUNG'];
    $xep_hang = $_POST['XEP_HANG_DV'];
    $ngay_gui = date('Y-m-d H:i:s'); // Set current date and time

    // Insert feedback into the database
    $insertQuery = "
        INSERT INTO phan_hoi_cua_khach_hang (ID_TK, ID_DV, NOI_DUNG, NGAY_GUI, XEP_HANG_DV)
        VALUES ('$id_tk', '$id_dv', '$noi_dung', '$ngay_gui', '$xep_hang')
    ";

    if (mysqli_query($conn, $insertQuery)) {
        $successMessage = "Phản hồi của bạn đã được gửi thành công!";
    } else {
        $errorMessage = "Có lỗi khi gửi phản hồi! Vui lòng thử lại.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gửi Phản Hồi</title>
    <link rel="stylesheet" href="/path/to/your/css/style.css">
</head>

<body class="bg-gray-100 p-6">
    <h1 class="text-2xl font-bold mb-4">Gửi Phản Hồi</h1>

    <!-- Display success or error message -->
    <?php if (isset($successMessage)) : ?>
        <div class="bg-green-500 text-white p-3 rounded mb-4">
            <?= $successMessage; ?>
        </div>
    <?php elseif (isset($errorMessage)) : ?>
        <div class="bg-red-500 text-white p-3 rounded mb-4">
            <?= $errorMessage; ?>
        </div>
    <?php endif; ?>

    <!-- Feedback Form -->
    <form method="POST" action="submit_feedback.php" class="bg-white p-6 shadow-md rounded mb-4">
        <label for="ID_DV" class="block mb-2">Dịch Vụ</label>
        <select name="ID_DV" id="ID_DV" class="p-2 border w-full mb-4" required>
            <option value="">Chọn dịch vụ</option>
            <?php while ($service = mysqli_fetch_assoc($servicesResult)) : ?>
                <option value="<?= $service['ID_DV']; ?>"><?= $service['TEN_DV']; ?></option>
            <?php endwhile; ?>
        </select>

        <label for="NOI_DUNG" class="block mb-2">Nội Dung Phản Hồi</label>
        <textarea name="NOI_DUNG" id="NOI_DUNG" placeholder="Viết phản hồi của bạn..." class="p-2 border w-full mb-4" required></textarea>

        <label for="XEP_HANG_DV" class="block mb-2">Đánh Giá (1 đến 5 sao)</label>
        <input type="number" name="XEP_HANG_DV" id="XEP_HANG_DV" min="1" max="5" class="p-2 border w-full mb-4" required>

        <!-- Hidden field for customer ID (assuming session is used for login) -->
        <input type="hidden" name="ID_TK" value="<?= $_SESSION['ID_TK']; ?>">

        <button type="submit" class="bg-blue-500 text-white p-2 mt-4 rounded">Gửi Phản Hồi</button>
    </form>

    <!-- Button to go back -->
    <a href="index.php" class="bg-gray-500 text-white p-2 mt-4 rounded">Trở Lại</a>
</body>

</html>

<?php
// Close database connection
mysqli_close($conn);
?>
