<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AppointmentNotificationService {
    
    private $conn;
    private $mail;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initMailer();
    }
    
    private function initMailer() {
        $this->mail = new PHPMailer(true);
        $this->mail->isSMTP();
        $this->mail->Host = 'smtp.gmail.com';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'trongnghiann4911@gmail.com';
        $this->mail->Password = 'boyw rfke ahjp trlx';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;
        $this->mail->CharSet = 'UTF-8';
        $this->mail->setFrom('trongnghiann4911@gmail.com', 'Stygian Blue Studio');
    }
    
    public function generateToken() {
        return bin2hex(random_bytes(32));
    }
    
    public function sendConfirmationEmail($appointmentId) {
        try {
            $appointment = $this->getAppointmentDetails($appointmentId);
            if (!$appointment) {
                throw new Exception("Lich hen khong ton tai: $appointmentId");
            }

            // Ensure token table exists
            $this->ensureTokenTableExists();
            
            $token = $this->generateToken();
            $expire = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            // Store token in lich_hen_tokens table
            $stmt = $this->conn->prepare(
                "INSERT INTO lich_hen_tokens (ID_LICHHEN, TOKEN, EXPIRE_AT) VALUES (?, ?, ?)"
            );
            if ($stmt) {
                $stmt->bind_param('iss', $appointmentId, $token, $expire);
                $stmt->execute();
                $stmt->close();
            } else {
                error_log('Failed to insert token: ' . $this->conn->error);
            }
            
            $confirmUrl = 'http://localhost:8080/StygianBlue/public/confirm_appointment.php?token=' . urlencode($token);
            $customerName = htmlspecialchars($appointment['HO_TEN']);
            $serviceNameRaw = !empty($appointment['SERVICE_LIST']) ? $appointment['SERVICE_LIST'] : ($appointment['TEN_DV'] ?? 'Dịch vụ');
            $normalized = str_replace(' + ', ',', (string)$serviceNameRaw);
            $serviceParts = array_values(array_unique(array_filter(array_map('trim', explode(',', $normalized)))));
            $serviceName = htmlspecialchars(implode(' + ', $serviceParts));
            $appointmentTime = date('d/m/Y H:i', strtotime($appointment['THOI_GIAN_BAT_DAU']));
            $appointmentDate = date('d/m/Y', strtotime($appointment['THOI_GIAN_BAT_DAU']));
            $appointmentHour = date('H:i', strtotime($appointment['THOI_GIAN_BAT_DAU']));
            $branchName = htmlspecialchars($appointment['TEN_CN'] ?? 'Stygian Blue Studio');
            $branchAddress = htmlspecialchars($appointment['DIA_CHI_CN'] ?? '');
            $branchPhone = htmlspecialchars($appointment['SDT_CN'] ?? '');
            
            $this->mail->addAddress($appointment['EMAIL'], $customerName);
            $this->mail->Subject = '✓ Xác nhận lịch hẹn - Stygian Blue Studio';
            $this->mail->isHTML(true);
            $this->mail->Body = $this->getConfirmationEmailTemplate($customerName, $serviceName, $appointmentTime, $appointmentDate, $appointmentHour, $branchName, $branchAddress, $branchPhone, $confirmUrl);
            
            $this->mail->send();
            $this->mail->clearAddresses();
            $this->logNotification($appointmentId, 'confirmation', $appointment['EMAIL'], 'sent');
            return true;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            error_log("Gui email xac nhan that bai: $errorMsg");
            if (isset($appointment)) {
                $this->logNotification($appointmentId, 'confirmation', $appointment['EMAIL'], 'failed', $errorMsg);
            }
            return false;
        }
    }
    
    public function sendCancellationEmail($appointmentId, $reason = '') {
        try {
            $appointment = $this->getAppointmentDetails($appointmentId);
            if (!$appointment) {
                throw new Exception("Lich hen khong ton tai: $appointmentId");
            }
            
            $customerName = htmlspecialchars($appointment['HO_TEN']);
            $serviceNameRaw = !empty($appointment['SERVICE_LIST']) ? $appointment['SERVICE_LIST'] : ($appointment['TEN_DV'] ?? 'Dịch vụ');
            $normalized = str_replace(' + ', ',', (string)$serviceNameRaw);
            $serviceParts = array_values(array_unique(array_filter(array_map('trim', explode(',', $normalized)))));
            $serviceName = htmlspecialchars(implode(' + ', $serviceParts));
            $appointmentTime = date('d/m/Y H:i', strtotime($appointment['THOI_GIAN_BAT_DAU']));
            $branchName = htmlspecialchars($appointment['TEN_CN'] ?? 'Stygian Blue Studio');
            
            $this->mail->addAddress($appointment['EMAIL'], $customerName);
            $this->mail->Subject = '⚠️ Thông báo hủy lịch hẹn - Stygian Blue Studio';
            $this->mail->isHTML(true);
            $this->mail->Body = $this->getCancellationEmailTemplate($customerName, $serviceName, $appointmentTime, $reason, $branchName);
            
            $this->mail->send();
            $this->mail->clearAddresses();
            $this->logNotification($appointmentId, 'cancel', $appointment['EMAIL'], 'sent');
            return true;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            error_log("Gui email huy lich that bai: $errorMsg");
            if (isset($appointment)) {
                $this->logNotification($appointmentId, 'cancel', $appointment['EMAIL'], 'failed', $errorMsg);
            }
            return false;
        }
    }
    
    public function sendRescheduleEmail($appointmentId, $changes = []) {
        try {
            $appointment = $this->getAppointmentDetails($appointmentId);
            if (!$appointment) {
                throw new Exception("Lich hen khong ton tai: $appointmentId");
            }
            
            $customerName = htmlspecialchars($appointment['HO_TEN']);
            $serviceNameRaw = !empty($appointment['SERVICE_LIST']) ? $appointment['SERVICE_LIST'] : ($appointment['TEN_DV'] ?? 'Dịch vụ');
            $normalized = str_replace(' + ', ',', (string)$serviceNameRaw);
            $serviceParts = array_values(array_unique(array_filter(array_map('trim', explode(',', $normalized)))));
            $serviceName = htmlspecialchars(implode(' + ', $serviceParts));
            $appointmentTime = date('d/m/Y H:i', strtotime($appointment['THOI_GIAN_BAT_DAU']));
            $appointmentDate = date('d/m/Y', strtotime($appointment['THOI_GIAN_BAT_DAU']));
            $appointmentHour = date('H:i', strtotime($appointment['THOI_GIAN_BAT_DAU']));
            $branchName = htmlspecialchars($appointment['TEN_CN'] ?? 'Stygian Blue Studio');
            $branchAddress = htmlspecialchars($appointment['DIA_CHI_CN'] ?? '');
            $branchPhone = htmlspecialchars($appointment['SDT_CN'] ?? '');
            
            $this->mail->addAddress($appointment['EMAIL'], $customerName);
            $this->mail->Subject = '📅 Thông báo thay đổi lịch hẹn - Stygian Blue Studio';
            $this->mail->isHTML(true);
            $this->mail->Body = $this->getRescheduleEmailTemplate($customerName, $serviceName, $appointmentTime, $appointmentDate, $appointmentHour, $changes, $branchName, $branchAddress, $branchPhone);
            
            $this->mail->send();
            $this->mail->clearAddresses();
            $this->logNotification($appointmentId, 'reschedule', $appointment['EMAIL'], 'sent');
            return true;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            error_log("Gui email thay doi lich that bai: $errorMsg");
            if (isset($appointment)) {
                $this->logNotification($appointmentId, 'reschedule', $appointment['EMAIL'], 'failed', $errorMsg);
            }
            return false;
        }
    }
    
    private function logNotification($appointmentId, $type, $email, $status, $response = '') {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO lich_hen_notification_log 
                 (ID_LICHHEN, LOAI_THONG_BAO, EMAIL_NHAN, TRANG_THAI_GUI, RESPONSE, THOI_GIAN_GUI) 
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('issss', $appointmentId, $type, $email, $status, $response);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Loi ghi log thong bao: " . $e->getMessage());
        }
    }
    
    public function confirmAppointmentByToken($token) {
        try {
            if (empty($token)) {
                return [
                    'success' => false,
                    'message' => 'Token không được cung cấp',
                    'id' => ''
                ];
            }

            // Ensure token tables exist
            $this->ensureTokenTableExists();

            // Get appointment by token from lich_hen_tokens table
            $stmt = $this->conn->prepare(
                "SELECT lt.ID_LICHHEN, lt.EXPIRE_AT 
                 FROM lich_hen_tokens lt
                 WHERE lt.TOKEN = ? AND lt.EXPIRE_AT > NOW() AND lt.USED_AT IS NULL"
            );
            
            if (!$stmt) {
                error_log('Prepare failed: ' . $this->conn->error);
                return [
                    'success' => false,
                    'message' => 'Lỗi hệ thống: ' . $this->conn->error,
                    'id' => ''
                ];
            }

            $stmt->bind_param('s', $token);
            if (!$stmt->execute()) {
                error_log('Execute failed: ' . $stmt->error);
                return [
                    'success' => false,
                    'message' => 'Lỗi hệ thống: ' . $stmt->error,
                    'id' => ''
                ];
            }

            $result = $stmt->get_result();
            $tokenRecord = $result->fetch_assoc();
            $stmt->close();

            if (!$tokenRecord) {
                return [
                    'success' => false,
                    'message' => 'Link xác nhận không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu gửi lại link xác nhận từ studio.',
                    'id' => ''
                ];
            }

            $appointmentId = $tokenRecord['ID_LICHHEN'];

            // Mark token as used
            $markUsedStmt = $this->conn->prepare(
                "UPDATE lich_hen_tokens SET USED_AT = NOW() WHERE TOKEN = ?"
            );
            if ($markUsedStmt) {
                $markUsedStmt->bind_param('s', $token);
                $markUsedStmt->execute();
                $markUsedStmt->close();
            }

            // Update appointment to mark as confirmed by customer
            $updateStmt = $this->conn->prepare(
                "UPDATE lich_hen 
                 SET KHACH_XAC_NHAN = 1, 
                     THOI_GIAN_KHACH_XAC_NHAN = NOW()
                 WHERE ID_LICHHEN = ?"
            );

            if (!$updateStmt) {
                return [
                    'success' => false,
                    'message' => 'Lỗi hệ thống khi cập nhật: ' . $this->conn->error,
                    'id' => $appointmentId
                ];
            }

            $updateStmt->bind_param('i', $appointmentId);
            if (!$updateStmt->execute()) {
                return [
                    'success' => false,
                    'message' => 'Lỗi hệ thống khi cập nhật: ' . $updateStmt->error,
                    'id' => $appointmentId
                ];
            }

            $updateStmt->close();

            // Send confirmation email to studio
            $this->sendConfirmationAcknowledgmentToStudio($appointmentId);

            return [
                'success' => true,
                'message' => 'Xác nhận lịch hẹn thành công! Cảm ơn bạn đã xác nhận. Chúng tôi sẽ gửi thông báo nhắc nhở cho bạn trước lịch hẹn.',
                'id' => $appointmentId
            ];
        } catch (Exception $e) {
            error_log('Lỗi trong confirmAppointmentByToken: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage(),
                'id' => ''
            ];
        }
    }

    private function ensureTokenTableExists() {
        $createTableSql = "
            CREATE TABLE IF NOT EXISTS lich_hen_tokens (
                ID INT AUTO_INCREMENT PRIMARY KEY,
                ID_LICHHEN INT NOT NULL,
                TOKEN VARCHAR(255) UNIQUE NOT NULL,
                CREATED_AT DATETIME DEFAULT CURRENT_TIMESTAMP,
                EXPIRE_AT DATETIME NOT NULL,
                USED_AT DATETIME NULL,
                FOREIGN KEY (ID_LICHHEN) REFERENCES lich_hen(ID_LICHHEN) ON DELETE CASCADE,
                INDEX idx_token (TOKEN),
                INDEX idx_expire (EXPIRE_AT)
            )
        ";
        
        if (!$this->conn->query($createTableSql)) {
            error_log('Failed to create lich_hen_tokens table: ' . $this->conn->error);
        }
    }

    private function sendConfirmationAcknowledgmentToStudio($appointmentId) {
        try {
            $appointment = $this->getAppointmentDetails($appointmentId);
            if (!$appointment) {
                return false;
            }

            $customerName = htmlspecialchars($appointment['HO_TEN']);
            $serviceNameRaw = !empty($appointment['SERVICE_LIST']) ? $appointment['SERVICE_LIST'] : ($appointment['TEN_DV'] ?? 'Dịch vụ');
            $normalized = str_replace(' + ', ',', (string)$serviceNameRaw);
            $serviceParts = array_values(array_unique(array_filter(array_map('trim', explode(',', $normalized)))));
            $serviceName = htmlspecialchars(implode(' + ', $serviceParts));
            $appointmentTime = date('d/m/Y H:i', strtotime($appointment['THOI_GIAN_BAT_DAU']));

            $studioMail = new PHPMailer(true);
            $studioMail->isSMTP();
            $studioMail->Host = 'smtp.gmail.com';
            $studioMail->SMTPAuth = true;
            $studioMail->Username = 'trongnghiann4911@gmail.com';
            $studioMail->Password = 'boyw rfke ahjp trlx';
            $studioMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $studioMail->Port = 587;
            $studioMail->CharSet = 'UTF-8';
            $studioMail->setFrom('trongnghiann4911@gmail.com', 'Stygian Blue Studio');

            $studioMail->addAddress('trongnghiann4911@gmail.com', 'Stygian Blue Studio');
            $studioMail->Subject = '[Xác Nhận] Khách hàng ' . $customerName . ' xác nhận lịch hẹn #' . $appointmentId;
            $studioMail->isHTML(true);
            $studioMail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 8px;'>
                        <h1 style='color: white; margin: 0;'>Khách hàng đã xác nhận lịch hẹn</h1>
                    </div>
                    <div style='background: #f8f9fa; padding: 30px; border: 1px solid #e0e0e0;'>
                        <p style='color: #333; font-size: 16px;'><strong>Thông báo hệ thống</strong></p>
                        <p>Khách hàng <strong>$customerName</strong> vừa xác nhận lịch hẹn.</p>
                        <div style='background: #fff; padding: 15px; border-left: 4px solid #667eea; margin: 15px 0;'>
                            <p><strong>Mã Lịch Hẹn:</strong> #$appointmentId</p>
                            <p><strong>Tên khách:</strong> $customerName</p>
                            <p><strong>Dịch vụ:</strong> $serviceName</p>
                            <p><strong>Thời gian:</strong> $appointmentTime</p>
                        </div>
                        <p>Vui lòng chuẩn bị đầy đủ để phục vụ khách hàng.</p>
                    </div>
                </div>
            ";

            $studioMail->send();
            $this->logNotification($appointmentId, 'confirmation_ack', 'trongnghiann4911@gmail.com', 'sent');
            return true;
        } catch (Exception $e) {
            error_log('Lỗi gửi email thông báo xác nhận: ' . $e->getMessage());
            return false;
        }
    }

    private function getAppointmentDetails($appointmentId) {
        $stmt = $this->conn->prepare(
            "SELECT lh.*, tk.EMAIL, tk.HO_TEN, dv.TEN_DV, cn.TEN_CN 
             FROM lich_hen lh
             JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
             JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
             LEFT JOIN chi_nhanh cn ON lh.ID_CHINHANH = cn.ID_CN
             WHERE lh.ID_LICHHEN = ?"
        );
        $stmt->bind_param('i', $appointmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        // Enrich with multi-service list from BOOKING_ITEM
        if ($row) {
            $stmt2 = $this->conn->prepare(
                "SELECT GROUP_CONCAT(DISTINCT dv.TEN_DV ORDER BY dv.TEN_DV SEPARATOR ' + ') AS SERVICE_LIST,
                        COUNT(DISTINCT bi.REF_ID) AS SERVICE_COUNT
                 FROM BOOKING_ITEM bi
                 JOIN DICH_VU dv ON dv.ID_DV = bi.REF_ID
                 WHERE bi.ID_LICHHEN = ? AND bi.ITEM_TYPE = 'service'"
            );
            if ($stmt2) {
                $stmt2->bind_param('i', $appointmentId);
                $stmt2->execute();
                $rs2 = $stmt2->get_result();
                $agg = $rs2->fetch_assoc();
                $stmt2->close();
                if ($agg) {
                    $row['SERVICE_LIST'] = $agg['SERVICE_LIST'];
                    $row['SERVICE_COUNT'] = (int)$agg['SERVICE_COUNT'];
                }
            }
        }

        return $row;
    }

    /**
     * Generate professional confirmation email template
     */
    private function getConfirmationEmailTemplate($customerName, $serviceName, $appointmentTime, $appointmentDate, $appointmentHour, $branchName, $branchAddress, $branchPhone, $confirmUrl) {
        $currentYear = date('Y');
        return "
<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='font-family: Segoe UI, Arial, sans-serif; color: #333; line-height: 1.6;'>
    <div style='max-width: 600px; margin: 0 auto; background: #ffffff;'>
        <!-- Header with Logo -->
        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;'>
            <h1 style='margin: 0; color: white; font-size: 28px; font-weight: 600;'>Xác nhận lịch hẹn</h1>
            <p style='margin: 10px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;'>Stygian Blue Studio</p>
        </div>

        <!-- Main Content -->
        <div style='padding: 40px 30px; background: #f8f9fa;'>
            <!-- Greeting -->
            <p style='font-size: 16px; color: #333; margin: 0 0 20px 0;'>
                Xin chào <strong>$customerName</strong>,
            </p>
            
            <p style='font-size: 14px; color: #666; margin: 0 0 25px 0;'>
                Cảm ơn bạn đã chọn dịch vụ của chúng tôi. Vui lòng xác nhận lịch hẹn của bạn bằng cách nhấn nút dưới đây trong vòng 7 ngày.
            </p>

            <!-- Appointment Details Box -->
            <div style='background: #ffffff; border: 2px solid #667eea; border-radius: 8px; padding: 25px; margin: 25px 0;'>
                <h2 style='color: #667eea; font-size: 18px; margin: 0 0 20px 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 15px;'>Chi tiết lịch hẹn</h2>
                
                <div style='margin: 15px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Dịch vụ</p>
                    <p style='margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #333;'>$serviceName</p>
                </div>

                <div style='margin: 20px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Ngày thực hiện</p>
                    <p style='margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #333;'>$appointmentDate</p>
                </div>

                <div style='margin: 20px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Thời gian</p>
                    <p style='margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #333;'>$appointmentHour</p>
                </div>

                <div style='margin: 20px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Địa điểm thực hiện</p>
                    <p style='margin: 5px 0 0 0; font-size: 14px; font-weight: 600; color: #333;'>$branchName</p>
                    " . ($branchAddress ? "<p style='margin: 5px 0 0 0; font-size: 13px; color: #666;'>📍 $branchAddress</p>" : "") . "
                    " . ($branchPhone ? "<p style='margin: 5px 0 0 0; font-size: 13px; color: #666;'>📞 $branchPhone</p>" : "") . "
                </div>
            </div>

            <!-- Confirmation Button -->
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$confirmUrl' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px 40px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: 600; font-size: 15px; transition: all 0.3s ease;'>
                    XÁC NHẬN LỊCH HẸN
                </a>
            </div>

            <p style='font-size: 12px; color: #999; text-align: center; margin: 20px 0;'>
                Link xác nhận sẽ hết hạn sau 7 ngày
            </p>

            <!-- Important Note -->
            <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 25px 0; border-radius: 4px;'>
                <p style='margin: 0; font-size: 13px; color: #856404;'>
                    <strong>Lưu ý quan trọng:</strong> Vui lòng xác nhận lịch hẹn để hoàn tất quy trình đặt lịch. Điều này giúp chúng tôi chuẩn bị tốt nhất cho buổi làm việc của bạn.
                </p>
            </div>

            <!-- Contact Info -->
            <div style='background: #f0f4ff; padding: 15px; margin: 25px 0; border-radius: 6px; border-left: 4px solid #667eea;'>
                <p style='margin: 0 0 10px 0; font-size: 13px; color: #333; font-weight: 600;'>Cần giúp đỡ?</p>
                <p style='margin: 5px 0; font-size: 12px; color: #666;'>Gọi chúng tôi: <strong>0123 456 789</strong></p>
                <p style='margin: 5px 0; font-size: 12px; color: #666;'>Email: <strong>support@stygianblue.vn</strong></p>
                <p style='margin: 5px 0; font-size: 12px; color: #666;'>Chat với chúng tôi qua website</p>
            </div>
        </div>

        <!-- Footer -->
        <div style='background: #2c3e50; color: #ecf0f1; padding: 30px; text-align: center; border-top: 3px solid #667eea;'>
            <p style='margin: 0 0 10px 0; font-size: 14px; font-weight: 600;'>Stygian Blue Studio</p>
            <p style='margin: 0 0 15px 0; font-size: 12px; color: #bdc3c7;'>Chuyên cung cấp dịch vụ chụp ảnh và quần áo phục vụ sự kiện</p>
            
            <!-- Social Links -->
            <div style='margin: 15px 0;'>
                <a href='https://facebook.com/stygianblue' style='color: #3b5998; text-decoration: none; margin: 0 10px; font-size: 12px;'>Facebook</a> | 
                <a href='https://instagram.com/stygianblue' style='color: #e4405f; text-decoration: none; margin: 0 10px; font-size: 12px;'>Instagram</a> | 
                <a href='https://stygianblue.vn' style='color: #667eea; text-decoration: none; margin: 0 10px; font-size: 12px;'>Website</a>
            </div>

            <hr style='border: none; border-top: 1px solid #34495e; margin: 15px 0;'>
            
            <p style='margin: 0; font-size: 11px; color: #7f8c8d;'>
                © $currentYear Stygian Blue Studio. All rights reserved.<br>
                Thư này được gửi tự động, vui lòng không trả lời trực tiếp.
            </p>
        </div>
    </div>
</body>
</html>
        ";
    }

    /**
     * Generate professional cancellation email template
     */
    private function getCancellationEmailTemplate($customerName, $serviceName, $appointmentTime, $reason, $branchName) {
        $currentYear = date('Y');
        $reasonText = !empty($reason) ? htmlspecialchars($reason) : 'Không có thông tin lý do';
        
        return "
<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='font-family: Segoe UI, Arial, sans-serif; color: #333; line-height: 1.6;'>
    <div style='max-width: 600px; margin: 0 auto; background: #ffffff;'>
        <!-- Header -->
        <div style='background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 40px 30px; text-align: center;'>
            <h1 style='margin: 0; color: white; font-size: 28px; font-weight: 600;'>Thông báo hủy lịch hẹn</h1>
            <p style='margin: 10px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;'>Stygian Blue Studio</p>
        </div>

        <!-- Main Content -->
        <div style='padding: 40px 30px; background: #f8f9fa;'>
            <p style='font-size: 16px; color: #333; margin: 0 0 20px 0;'>
                Xin chào <strong>$customerName</strong>,
            </p>
            
            <p style='font-size: 14px; color: #666; margin: 0 0 25px 0;'>
                Rất tiếc, lịch hẹn của bạn đã được hủy. Nếu bạn muốn đặt lịch mới, vui lòng liên hệ với chúng tôi.
            </p>

            <!-- Cancelled Appointment Details -->
            <div style='background: #ffffff; border: 2px solid #dc3545; border-radius: 8px; padding: 25px; margin: 25px 0;'>
                <h2 style='color: #dc3545; font-size: 18px; margin: 0 0 20px 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 15px;'>Lịch hẹn đã hủy</h2>
                
                <div style='margin: 15px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Dịch vụ</p>
                    <p style='margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #333;'>$serviceName</p>
                </div>

                <div style='margin: 20px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Thời gian ban đầu</p>
                    <p style='margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #333;'>$appointmentTime</p>
                </div>

                <div style='margin: 20px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Lý do hủy</p>
                    <p style='margin: 5px 0 0 0; font-size: 14px; color: #dc3545; font-weight: 500;'>$reasonText</p>
                </div>
            </div>

            <!-- Rescheduling Info -->
            <div style='background: #e7f3ff; border-left: 4px solid #0066cc; padding: 15px; margin: 25px 0; border-radius: 4px;'>
                <p style='margin: 0 0 10px 0; font-size: 13px; color: #004085; font-weight: 600;'>
                    Muốn đặt lịch mới?
                </p>
                <p style='margin: 5px 0; font-size: 12px; color: #004085;'>
                    Bạn có thể dễ dàng đặt lịch mới thông qua website hoặc liên hệ trực tiếp với chúng tôi.
                </p>
            </div>

            <!-- Contact Info -->
            <div style='background: #f0f4ff; padding: 15px; margin: 25px 0; border-radius: 6px; border-left: 4px solid #667eea;'>
                <p style='margin: 0 0 10px 0; font-size: 13px; color: #333; font-weight: 600;'>Liên hệ với chúng tôi</p>
                <p style='margin: 5px 0; font-size: 12px; color: #666;'>Gọi: <strong>0123 456 789</strong></p>
                <p style='margin: 5px 0; font-size: 12px; color: #666;'>Email: <strong>support@stygianblue.vn</strong></p>
                <p style='margin: 5px 0; font-size: 12px; color: #666;'>Website: <strong>stygianblue.vn</strong></p>
            </div>
        </div>

        <!-- Footer -->
        <div style='background: #2c3e50; color: #ecf0f1; padding: 30px; text-align: center; border-top: 3px solid #dc3545;'>
            <p style='margin: 0 0 10px 0; font-size: 14px; font-weight: 600;'>Stygian Blue Studio</p>
            <p style='margin: 0 0 15px 0; font-size: 12px; color: #bdc3c7;'>Chuyên cung cấp dịch vụ chụp ảnh và quần áo phục vụ sự kiện</p>
            
            <hr style='border: none; border-top: 1px solid #34495e; margin: 15px 0;'>
            
            <p style='margin: 0; font-size: 11px; color: #7f8c8d;'>
                © " . date('Y') . " Stygian Blue Studio. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
        ";
    }

    /**
     * Generate professional reschedule email template
     */
    private function getRescheduleEmailTemplate($customerName, $serviceName, $appointmentTime, $appointmentDate, $appointmentHour, $changes, $branchName, $branchAddress, $branchPhone) {
        $currentYear = date('Y');
        
        $changesHtml = '<div style=\"background: #f0f4ff; border-left: 4px solid #667eea; padding: 15px; margin: 15px 0; border-radius: 4px;\">';
        $changesHtml .= '<p style=\"margin: 0 0 10px 0; font-size: 13px; color: #333; font-weight: 600;\">Những thay đổi:</p>';
        
        if (!empty($changes['service'])) {
            $old = htmlspecialchars($changes['service']['old']);
            $new = htmlspecialchars($changes['service']['new']);
            $changesHtml .= "<p style=\"margin: 5px 0; font-size: 12px; color: #666;\"><strong>Dịch vụ:</strong> $old → $new</p>";
        }
        if (!empty($changes['time'])) {
            $old = htmlspecialchars($changes['time']['old']);
            $new = htmlspecialchars($changes['time']['new']);
            $changesHtml .= "<p style=\"margin: 5px 0; font-size: 12px; color: #666;\"><strong>Thời gian:</strong> $old → $new</p>";
        }
        if (!empty($changes['address'])) {
            $old = htmlspecialchars($changes['address']['old']);
            $new = htmlspecialchars($changes['address']['new']);
            $changesHtml .= "<p style=\"margin: 5px 0; font-size: 12px; color: #666;\"><strong>Địa chỉ:</strong> $old → $new</p>";
        }
        $changesHtml .= '</div>';
        
        return "
<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='font-family: Segoe UI, Arial, sans-serif; color: #333; line-height: 1.6;'>
    <div style='max-width: 600px; margin: 0 auto; background: #ffffff;'>
        <!-- Header -->
        <div style='background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); padding: 40px 30px; text-align: center;'>
            <h1 style='margin: 0; color: white; font-size: 28px; font-weight: 600;'>Thông báo thay đổi lịch hẹn</h1>
            <p style='margin: 10px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;'>Stygian Blue Studio</p>
        </div>

        <!-- Main Content -->
        <div style='padding: 40px 30px; background: #f8f9fa;'>
            <p style='font-size: 16px; color: #333; margin: 0 0 20px 0;'>
                Xin chào <strong>$customerName</strong>,
            </p>
            
            <p style='font-size: 14px; color: #666; margin: 0 0 25px 0;'>
                Lịch hẹn của bạn đã được cập nhật. Vui lòng xem lại thông tin chi tiết dưới đây.
            </p>

            <!-- Changes -->
            $changesHtml

            <!-- New Appointment Details -->
            <div style='background: #ffffff; border: 2px solid #667eea; border-radius: 8px; padding: 25px; margin: 25px 0;'>
                <h2 style='color: #667eea; font-size: 18px; margin: 0 0 20px 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 15px;'>Chi tiết lịch hẹn mới</h2>
                
                <div style='margin: 15px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Dịch vụ</p>
                    <p style='margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #333;'>$serviceName</p>
                </div>

                <div style='margin: 20px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Ngày thực hiện</p>
                    <p style='margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #333;'>$appointmentDate</p>
                </div>

                <div style='margin: 20px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Thời gian</p>
                    <p style='margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #333;'>$appointmentHour</p>
                </div>

                <div style='margin: 20px 0;'>
                    <p style='margin: 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Địa điểm</p>
                    <p style='margin: 5px 0 0 0; font-size: 14px; font-weight: 600; color: #333;'>$branchName</p>
                    " . ($branchAddress ? "<p style='margin: 5px 0 0 0; font-size: 13px; color: #666;'>📍 $branchAddress</p>" : "") . "
                </div>
            </div>

            <!-- Contact Info -->
            <div style='background: #f0f4ff; padding: 15px; margin: 25px 0; border-radius: 6px; border-left: 4px solid #667eea;'>
                <p style='margin: 0 0 10px 0; font-size: 13px; color: #333; font-weight: 600;'>Có câu hỏi?</p>
                <p style='margin: 5px 0; font-size: 12px; color: #666;'>Gọi: <strong>0123 456 789</strong></p>
                <p style='margin: 5px 0; font-size: 12px; color: #666;'>Email: <strong>support@stygianblue.vn</strong></p>
            </div>
        </div>

        <!-- Footer -->
        <div style='background: #2c3e50; color: #ecf0f1; padding: 30px; text-align: center; border-top: 3px solid #ffc107;'>
            <p style='margin: 0 0 10px 0; font-size: 14px; font-weight: 600;'>Stygian Blue Studio</p>
            <p style='margin: 0 0 15px 0; font-size: 12px; color: #bdc3c7;'>Chuyên cung cấp dịch vụ chụp ảnh và quần áo phục vụ sự kiện</p>
            
            <hr style='border: none; border-top: 1px solid #34495e; margin: 15px 0;'>
            
            <p style='margin: 0; font-size: 11px; color: #7f8c8d;'>
                © $currentYear Stygian Blue Studio. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
        ";
    }
}
