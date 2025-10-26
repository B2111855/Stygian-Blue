<?php
session_start();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'];

    if ($code == $_SESSION['reset_code']) {
        header("Location: reset_password.php");
        exit();
    } else {
        $message = "Mã xác nhận không đúng.";
    }
}
?>

<!-- ... phần xử lý PHP giữ nguyên ... -->

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Xác nhận mã</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-100 via-blue-100 to-green-100">
  <div class="bg-white/80 backdrop-blur-lg shadow-xl rounded-xl p-8 w-full max-w-md border border-indigo-200 relative">

    <!-- Logo -->
    <div class="flex justify-center mb-6">
      <img src="public/images/logo5.png" alt="Logo Stygian Blue" class="w-16 h-16 rounded-lg shadow-md">
    </div>

    <h2 class="text-2xl font-bold text-center text-green-700 mb-4">🔑 Nhập mã xác nhận</h2>

    <form method="POST" class="space-y-4">
      <input type="text" name="code" placeholder="Nhập mã xác nhận"
             class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" required>

      <button type="submit"
              class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg transition font-semibold shadow">
        Xác nhận
      </button>
    </form>

    <?php if (!empty($message)): ?>
      <p class="mt-4 text-red-600 text-center font-medium"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <!-- Quay lại -->
    <div class="text-center mt-6">
      <a href="login.php" class="text-indigo-600 hover:underline hover:text-indigo-800 transition text-sm">
        ⬅ Quay lại đăng nhập
      </a>
    </div>
  </div>
</body>
</html>

