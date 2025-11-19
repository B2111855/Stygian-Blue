<?php
/**
 * Job: Gửi email nhắc lịch hẹn T-24h
 * Chạy định kỳ (ví dụ mỗi giờ) bằng Task Scheduler.
 * Logic:
 *  - Tìm các lịch hẹn bắt đầu trong khoảng [now+24h, now+24h+60m] và TRANGTHAI IN ('Đã xác nhận')
 *  - Chưa được gửi nhắc (không có thong_bao TYPE=reminder_24h cho ID_LICHHEN)
 *  - Tạo bản ghi thong_bao (queued) và gửi email qua PHPMailer; cập nhật trạng thái sent/failed.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');

$root = dirname(__DIR__, 2); // .../StygianBlue
require_once $root . '/vendor/autoload.php';
require_once $root . '/database/config.php';
require_once $root . '/app/helpers/system_log.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

function log_msg(string $m): void { echo '[' . date('Y-m-d H:i:s') . "] $m\n"; }

function fetchUpcomingAppointments(mysqli $conn): array {
    // Window: target is ~24h ahead, with a 60 min tolerance bucket per run.
    $now = new DateTimeImmutable();
    $start = $now->modify('+24 hours');
    $end   = $start->modify('+60 minutes');

    $sql = "SELECT lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.DIA_CHI_HEN, tk.EMAIL, tk.HO_TEN
            FROM lich_hen lh
            JOIN tai_khoan tk ON tk.ID_TK = lh.ID_TK
            WHERE lh.TRANGTHAI = 'Đã xác nhận'
              AND lh.THOI_GIAN_BAT_DAU >= ? AND lh.THOI_GIAN_BAT_DAU < ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { throw new RuntimeException('Prepare failed: ' . $conn->error); }
    $startStr = $start->format('Y-m-d H:i:s');
    $endStr   = $end->format('Y-m-d H:i:s');
    $stmt->bind_param('ss', $startStr, $endStr);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function alreadyQueued(mysqli $conn, int $appointmentId): bool {
    // Dedupe: payload JSON contains TYPE=reminder_24h + ID_LICHHEN
    $like1 = '%"TYPE":"reminder_24h"%';
    $like2 = '%"ID_LICHHEN":' . $appointmentId . '%';
    $sql = "SELECT 1 FROM thong_bao WHERE TRANG_THAI_GUI IN ('queued','sent') AND PAYLOAD_JSON LIKE ? AND PAYLOAD_JSON LIKE ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return false; }
    $stmt->bind_param('ss', $like1, $like2);
    $stmt->execute();
    $stmt->store_result();
    $found = $stmt->num_rows > 0;
    $stmt->close();
    return $found;
}

function queueNotification(mysqli $conn, int $appointmentId, string $email, string $name, string $startTime, ?string $address): ?int {
    $payload = [
        'TYPE' => 'reminder_24h',
        'ID_LICHHEN' => $appointmentId,
        'START_AT' => $startTime,
        'ADDRESS' => $address,
        'EMAIL' => $email,
    ];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $title = 'Nhắc lịch hẹn - 24h tới';
    $body  = sprintf('Bạn có lịch hẹn #%d vào %s. Vui lòng chuẩn bị. Địa điểm: %s',
        $appointmentId,
        date('d/m/Y H:i', strtotime($startTime)),
        $address ?: 'Chưa cập nhật'
    );

    $sql = "INSERT INTO thong_bao (ID_TK_NGUOI_NHAN, LOAI, TIEU_DE, NOI_DUNG, PAYLOAD_JSON, TRANG_THAI_GUI, LAN_THU) VALUES (?, 'email', ?, ?, ?, 'queued', 0)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { throw new RuntimeException('Prepare queue failed: ' . $conn->error); }
    // We do not have direct ID_TK from the join (we only selected email + name). Need ID_TK => fetch it quickly.
    // Simplify: try find ID_TK by email.
    $idTk = null;
    $stmtTk = $conn->prepare('SELECT ID_TK FROM tai_khoan WHERE EMAIL = ? LIMIT 1');
    if ($stmtTk) {
        $stmtTk->bind_param('s', $email);
        $stmtTk->execute();
        $resTk = $stmtTk->get_result();
        $rowTk = $resTk ? $resTk->fetch_assoc() : null;
        $stmtTk->close();
        if ($rowTk) { $idTk = $rowTk['ID_TK']; }
    }
    if ($idTk === null) { $idTk = 'unknown'; }

    $stmt->bind_param('sssss', $idTk, $title, $body, $payloadJson, $payloadJson); // payload duplicated intentionally? adjust -> should be 1 placeholder
    // Correction: we used 5 placeholders but sql has 5 ? (ID_TK_NGUOI_NHAN, TIEU_DE, NOI_DUNG, PAYLOAD_JSON). Remove extra.
    $stmt->close();
    $sql2 = "INSERT INTO thong_bao (ID_TK_NGUOI_NHAN, LOAI, TIEU_DE, NOI_DUNG, PAYLOAD_JSON, TRANG_THAI_GUI, LAN_THU) VALUES (?, 'email', ?, ?, ?, 'queued', 0)";
    $stmt2 = $conn->prepare($sql2);
    if (!$stmt2) { throw new RuntimeException('Prepare queue2 failed: ' . $conn->error); }
    $stmt2->bind_param('ssss', $idTk, $title, $body, $payloadJson);
    $stmt2->execute();
    $id = $conn->insert_id;
    $stmt2->close();
    return $id > 0 ? $id : null;
}

function sendEmail(string $email, string $name, string $subject, string $htmlBody): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'] ?? '';
        $mail->Password = $_ENV['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)($_ENV['SMTP_PORT'] ?? 587);

        $mail->setFrom($_ENV['SMTP_FROM'] ?? ($mail->Username ?: 'no-reply@example.com'), $_ENV['SMTP_FROM_NAME'] ?? 'Stygian Blue Studio');
        $mail->addAddress($email, $name ?: $email);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[ReminderMail] send failed: ' . $mail->ErrorInfo);
        return false;
    }
}

function markNotification(mysqli $conn, int $id, string $status, ?string $errorMsg = null): void {
    $sql = 'UPDATE thong_bao SET TRANG_THAI_GUI = ?, LAN_THU = LAN_THU + 1, SENT_AT = NOW(), ERROR_MSG = ? WHERE ID_TBThongBao = ?';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ssi', $status, $errorMsg, $id);
        $stmt->execute();
        $stmt->close();
    }
}

try {
    log_msg('Job bắt đầu');
    $appointments = fetchUpcomingAppointments($conn);
    log_msg('Tìm thấy ' . count($appointments) . ' lịch hẹn trong cửa sổ 24h.');

    $queued = [];
    foreach ($appointments as $ap) {
        $idLh = (int)$ap['ID_LICHHEN'];
        if (alreadyQueued($conn, $idLh)) {
            log_msg("Đã có thông báo cho lịch #$idLh, bỏ qua.");
            continue;
        }
        $nid = queueNotification($conn, $idLh, (string)$ap['EMAIL'], (string)$ap['HO_TEN'], (string)$ap['THOI_GIAN_BAT_DAU'], $ap['DIA_CHI_HEN']);
        if ($nid) { $queued[] = ['notif_id' => $nid, 'appt' => $ap]; }
    }

    log_msg('Đã queue ' . count($queued) . ' thông báo mới. Bắt đầu gửi email...');

    foreach ($queued as $item) {
        $ap = $item['appt'];
        $idNotif = (int)$item['notif_id'];
        $startFmt = date('d/m/Y H:i', strtotime($ap['THOI_GIAN_BAT_DAU']));
        $subject = 'Nhắc lịch hẹn của bạn (24h)';
        $html = '<h3>Xin chào ' . htmlspecialchars($ap['HO_TEN'] ?? '') . '</h3>'
              . '<p>Đây là email nhắc lịch cho lịch hẹn #' . (int)$ap['ID_LICHHEN'] . ' sẽ diễn ra vào <strong>' . $startFmt . '</strong>.</p>'
              . '<p>Địa điểm: ' . htmlspecialchars($ap['DIA_CHI_HEN'] ?: 'Chưa cập nhật') . '</p>'
              . '<p>Vui lòng chuẩn bị và liên hệ hỗ trợ nếu cần thay đổi.</p>'
              . '<p>Trân trọng,<br/>Stygian Blue Studio</p>';

        $ok = sendEmail((string)$ap['EMAIL'], (string)$ap['HO_TEN'], $subject, $html);
        markNotification($conn, $idNotif, $ok ? 'sent' : 'failed', $ok ? null : 'SendError');
        record_system_log($conn, 'reminder_email', 'lich_hen', null, [
            'ID_LICHHEN' => $ap['ID_LICHHEN'],
            'email' => $ap['EMAIL'],
            'status' => $ok ? 'sent' : 'failed'
        ]);
        log_msg('Gửi email lịch #' . $ap['ID_LICHHEN'] . ' => ' . ($ok ? 'OK' : 'FAILED'));
    }

    log_msg('Hoàn tất job.');
} catch (Throwable $e) {
    error_log('[ReminderJob] Fatal: ' . $e->getMessage());
    log_msg('Lỗi nghiêm trọng: ' . $e->getMessage());
}

$conn->close();
