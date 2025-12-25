<?php
/**
 * Setup Script: Initialize Expense Management System (Simplified)
 * Chạy script này một lần để cài đặt hệ thống
 * 
 * Truy cập: http://your-domain/app/admin/components/setup_expense_system.php
 * 
 * This simplified version only adds the ID_LOAI column to chi_phi_phat_sinh table
 * to link expenses with the existing loai_chi_phi table for categorization.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../database/config.php';

// Kiểm tra quyền admin
$idTk = $_SESSION['ID_TK'] ?? null;
$idQuyen = $_SESSION['ID_QUYEN'] ?? null;

// Chỉ cho admin chạy script này
if (!$idTk || !in_array($idQuyen, ['1', '2'])) { // 1 = Admin, 2 = Manager
    die("<p style='color: red; font-size: 16px;'>⚠️ Lỗi: Bạn không có quyền truy cập trang này!</p>");
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'initialize') {
        // Cài đặt hệ thống - thêm cột ID_LOAI vào chi_phi_phat_sinh
        try {
            // Check if column exists
            $checkCol = $conn->query("SHOW COLUMNS FROM chi_phi_phat_sinh LIKE 'ID_LOAI'");
            if ($checkCol->num_rows === 0) {
                // Add the column
                $alterStmt = $conn->prepare("ALTER TABLE chi_phi_phat_sinh ADD COLUMN ID_LOAI INT DEFAULT NULL AFTER TEN_CP");
                if ($alterStmt->execute()) {
                    $success = true;
                    $message = "✅ Cài đặt hệ thống quản lý chi phí thành công! Cột ID_LOAI đã được thêm vào bảng chi_phi_phat_sinh.";
                } else {
                    $message = "❌ Cài đặt thất bại: " . $alterStmt->error;
                }
            } else {
                $success = true;
                $message = "✅ Cột ID_LOAI đã tồn tại. Hệ thống đã sẵn sàng!";
            }
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Hệ Thống Quản Lý Chi Phí</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        .alert-success {
            background-color: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        .alert-error {
            background-color: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
        .checklist {
            background-color: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .checklist-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
            font-size: 14px;
            color: #4a5568;
            line-height: 1.5;
        }
        .checklist-item:last-child {
            margin-bottom: 0;
        }
        .check-icon {
            color: #48bb78;
            font-size: 18px;
            margin-right: 12px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .steps {
            background-color: #edf2f7;
            border-left: 4px solid #667eea;
            border-radius: 4px;
            padding: 16px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .steps h3 {
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .steps ol {
            margin-left: 20px;
            color: #4a5568;
            line-height: 1.6;
        }
        .steps li {
            margin-bottom: 6px;
        }
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }
        button, a.btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background-color: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background-color: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }
        .btn-secondary {
            background-color: #e2e8f0;
            color: #2d3748;
        }
        .btn-secondary:hover {
            background-color: #cbd5e0;
        }
        .success-checkmark {
            text-align: center;
            margin-bottom: 20px;
        }
        .success-icon {
            font-size: 48px;
            animation: bounce 0.6s ease-in-out;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        code {
            background-color: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 2px 6px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #d63384;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Setup Hệ Thống Quản Lý Chi Phí</h1>
        <p class="subtitle">Khởi tạo bảng, cấu hình dữ liệu và loại chi phí mặc định</p>

        <?php if ($message): ?>
            <div class="alert <?= $success ? 'alert-success' : 'alert-error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-checkmark">
                <div class="success-icon">✅</div>
                <p style="color: #22543d; font-weight: 600;">Hệ thống đã sẵn sàng!</p>
            </div>

            <div class="steps">
                <h3>📋 Các tính năng đã được cài đặt:</h3>
                <ol>
                    <li>Cột <code>ID_LOAI</code> trong bảng <code>chi_phi_phat_sinh</code></li>
                    <li>Liên kết với bảng <code>loai_chi_phi</code> để phân loại chi phí</li>
                    <li>Hỗ trợ lọc chi phí theo loại</li>
                    <li>Hiển thị biểu đồ xu hướng chi phí 12 tháng</li>
                </ol>
            </div>

            <div class="checklist">
                <div class="checklist-item">
                    <span class="check-icon">✅</span>
                    <span><strong>Quản Lý Chi Phí:</strong> <code>/app/admin/components/manager_expenses_v2.php</code></span>
                </div>
                <div class="checklist-item">
                    <span class="check-icon">✅</span>
                    <span><strong>API Chi Phí:</strong> <code>/app/admin/components/api_expense_items.php</code></span>
                </div>
                <div class="checklist-item">
                    <span class="check-icon">✅</span>
                    <span><strong>API Loại Chi Phí (Existing):</strong> <code>/app/admin/components/api_chi_phi_loai.php</code></span>
                </div>
            </div>

            <div class="button-group">
                <a href="manager_expenses_v2.php" class="btn btn-primary">📊 Vào Trang Quản Lý Chi Phí</a>
                <a href="../manage_finances.php" class="btn btn-secondary">💰 Quản Lý Tài Chính</a>
            </div>

        <?php else: ?>
            <div class="steps">
                <h3>📋 Hệ thống sẽ cài đặt:</h3>
                <ol>
                    <li>Thêm cột <code>ID_LOAI</code> vào bảng <code>chi_phi_phat_sinh</code></li>
                    <li>Kích hoạt liên kết với bảng <code>loai_chi_phi</code> hiện có</li>
                    <li>Cho phép phân loại chi phí theo các loại đã có trong hệ thống</li>
                </ol>
            </div>

            <div class="checklist">
                <h3 style="margin-bottom: 12px;">✅ Các yêu cầu:</h3>
                <div class="checklist-item">
                    <span class="check-icon">✓</span>
                    <span>MySQL/MariaDB database khả dụng</span>
                </div>
                <div class="checklist-item">
                    <span class="check-icon">✓</span>
                    <span>Bảng <code>loai_chi_phi</code> tồn tại với dữ liệu</span>
                </div>
                <div class="checklist-item">
                    <span class="check-icon">✓</span>
                    <span>Bảng <code>chi_phi_phat_sinh</code> tồn tại</span>
                </div>
                <div class="checklist-item">
                    <span class="check-icon">✓</span>
                    <span>Đăng nhập với tài khoản Admin hoặc Manager</span>
                </div>
            </div>

            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="action" value="initialize">
                <button type="submit" class="btn btn-primary" style="width: 100%;">⚡ Bắt Đầu Cài Đặt</button>
            </form>

            <p style="text-align: center; margin-top: 16px; color: #718096; font-size: 12px;">
                ⚠️ Bấm nút trên để khởi tạo hệ thống. Chỉ cần chạy một lần.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
