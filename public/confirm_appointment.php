<?php
// No login required - customer confirmation page
session_start();

// Include database connection
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AppointmentNotificationService;

// Initialize notification service
$notificationService = new AppointmentNotificationService($conn);

// Get token from URL
$token = $_GET['token'] ?? '';
$message = '';
$success = false;
$appointmentId = '';

if (empty($token)) {
    $message = 'Token không được cung cấp';
} else {
    // Confirm appointment using token
    $result = $notificationService->confirmAppointmentByToken($token);
    $success = $result['success'];
    $message = $result['message'];
    $appointmentId = $result['id'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $success ? 'Xác Nhận Thành Công' : 'Lỗi Xác Nhận'; ?> - Stygian Blue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .success-animation {
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md success-animation">
            <!-- Card -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                
                <!-- Header -->
                <div class="gradient-bg text-white p-8 text-center">
                    <?php if ($success): ?>
                        <div class="w-16 h-16 mx-auto mb-4 bg-white rounded-full flex items-center justify-center">
                            <svg class="w-10 h-10 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold">Xác Nhận Thành Công!</h1>
                    <?php else: ?>
                        <div class="w-16 h-16 mx-auto mb-4 bg-white rounded-full flex items-center justify-center">
                            <svg class="w-10 h-10 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold">Lỗi Xác Nhận</h1>
                    <?php endif; ?>
                </div>
                
                <!-- Content -->
                <div class="p-8">
                    <!-- Message -->
                    <div class="mb-6">
                        <p class="text-gray-700 text-center">
                            <?php echo htmlspecialchars($message); ?>
                        </p>
                    </div>
                    
                    <!-- Details (if success) -->
                    <?php if ($success && !empty($appointmentId)): ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <p class="text-sm text-blue-800">
                                <strong>Mã Lịch Hẹn:</strong> #<?php echo $appointmentId; ?>
                            </p>
                            <p class="text-sm text-blue-800 mt-2">
                                Chúng tôi sẽ gửi thông báo lịch nhắc nhở cho bạn 24 giờ trước lịch hẹn.
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Button -->
                    <div class="flex gap-3">
                        <a href="/" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg text-center transition duration-200">
                            Về Trang Chủ
                        </a>
                        <?php if ($success): ?>
                            <a href="/stygianblue/app/admin/components/manage_appointments.php" class="flex-1 gradient-bg text-white font-semibold py-2 px-4 rounded-lg text-center transition duration-200 hover:shadow-lg">
                                Quản Lý Lịch Hẹn
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Support Info -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <p class="text-xs text-gray-600 text-center">
                            Có vấn đề? Liên hệ: 
                            <a href="tel:0123456789" class="text-blue-600 hover:underline">0123 456 789</a> | 
                            <a href="mailto:support@stygianblue.vn" class="text-blue-600 hover:underline">support@stygianblue.vn</a>
                        </p>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="bg-gray-50 px-8 py-4 text-center">
                    <p class="text-xs text-gray-500">
                        © 2025 Stygian Blue Studio. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Additional Info (Desktop) -->
    <div class="hidden md:block fixed bottom-8 right-8">
        <div class="bg-white rounded-lg shadow-lg p-4 max-w-xs">
            <h3 class="font-semibold text-gray-800 mb-2">💡 Gợi Ý</h3>
            <p class="text-sm text-gray-600">
                <?php if ($success): ?>
                    Lịch hẹn của bạn đã được xác nhận. Vui lòng đợi thư nhắn nhở từ studio.
                <?php else: ?>
                    Nếu link đã hết hạn, vui lòng yêu cầu gửi lại link xác nhận từ studio.
                <?php endif; ?>
            </p>
        </div>
    </div>
</body>
</html>
