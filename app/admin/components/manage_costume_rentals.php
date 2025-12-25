<?php
include '../../database/config.php';

// PHPMailer for email notifications when rental approved
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Ensure Composer autoload path resolves regardless of including context
require_once __DIR__ . '/../../../vendor/autoload.php';
function tp_configure_mail(PHPMailer $mail): void {
    // Mirror appointment confirmation setup (known-good credentials)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'trongnghiann4911@gmail.com';
    $mail->Password   = 'boyw rfke ahjp trlx';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('trongnghiann4911@gmail.com', 'Stygian Blue Studio');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Auto-mark overdue rentals as late to keep the list up to date
function tp_auto_mark_overdue(mysqli $conn): void
{
    $now = date('Y-m-d H:i:s');
    $sql = "UPDATE don_thue_trang_phuc\n            SET TRANG_THAI = 'tre_hen',\n                TRE_HEN_NGAY = GREATEST(1, CEIL(TIMESTAMPDIFF(HOUR, NGAY_TRA_DK, ?) / 24)),\n                PHI_TRE_HEN = GREATEST(0, ROUND(TONG_TIEN_DU_KIEN * 0.02 * CEIL(TIMESTAMPDIFF(HOUR, NGAY_TRA_DK, ?) / 24))),\n                UPDATED_AT = NOW()\n            WHERE TRANG_THAI IN ('cho_duyet','da_duyet','dang_thue')\n              AND NGAY_TRA_DK IS NOT NULL\n              AND NGAY_TRA_DK < ?\n              AND (NGAY_TRA_THAT IS NULL AND NGAY_TRA_TT IS NULL)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sss', $now, $now, $now);
        $stmt->execute();
        $stmt->close();
    }
}

tp_auto_mark_overdue($conn);

$STATUS_META = [
    'cho_duyet' => [
        'label' => 'Chờ duyệt',
        'badge' => 'bg-amber-100 text-amber-700',
        'dot'   => 'bg-amber-500',
        'desc'  => 'Khách vừa gửi yêu cầu, cần kiểm tra lịch và tồn kho.'
    ],
    'da_duyet' => [
        'label' => 'Đã duyệt',
        'badge' => 'bg-blue-100 text-blue-700',
        'dot'   => 'bg-blue-500',
        'desc'  => 'Yêu cầu đã được xác nhận, chờ khách nhận đồ.'
    ],
    'dang_thue' => [
        'label' => 'Đang thuê',
        'badge' => 'bg-indigo-100 text-indigo-700',
        'dot'   => 'bg-indigo-500',
        'desc'  => 'Khách đã nhận trang phục và đang trong lịch thuê.'
    ],
    'da_tra' => [
        'label' => 'Đã trả',
        'badge' => 'bg-emerald-100 text-emerald-700',
        'dot'   => 'bg-emerald-500',
        'desc'  => 'Đơn đã hoàn tất và đóng tiền đủ.'
    ],
    'tre_hen' => [
        'label' => 'Trễ hạn',
        'badge' => 'bg-rose-100 text-rose-700',
        'dot'   => 'bg-rose-500',
        'desc'  => 'Khách đang trả trễ, cần theo sát và tính phụ phí.'
    ],
    'huy' => [
        'label' => 'Đã hủy',
        'badge' => 'bg-gray-200 text-gray-600',
        'dot'   => 'bg-gray-500',
        'desc'  => 'Khách hoặc quản trị đã hủy yêu cầu.'
    ],
];

$ALLOWED_STATUSES = array_keys($STATUS_META);

$TRANSITIONS = [
    'cho_duyet' => ['da_duyet', 'huy'],
    'da_duyet'  => ['dang_thue', 'huy'],
    'dang_thue' => ['da_tra', 'tre_hen'],
    'tre_hen'   => ['da_tra'],
    'da_tra'    => [],
    'huy'       => [],
];

$isAdmin         = (($_SESSION['ID_QUYEN'] ?? '') === '1');
$branchScopeId   = $isAdmin ? 0 : (int) ($_SESSION['branch_id'] ?? 0);
$currentUserName = $_SESSION['HO_TEN'] ?? ($_SESSION['ID_TK'] ?? 'Quản trị viên');

function tp_rental_redirect(): void
{
    if (!headers_sent()) {
        header('Location: ?page=costume_rentals');
        exit;
    }
    echo '<script>window.location.href = "?page=costume_rentals";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=?page=costume_rentals"></noscript>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rental_action'])) {
    $orderId   = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
    $newStatus = $_POST['new_status'] ?? '';
    $returnedAtInput = trim($_POST['returned_at'] ?? '');
    $actualTotalInput = trim($_POST['actual_total'] ?? '');
    $managerNote = trim($_POST['manager_note'] ?? '');

    if ($orderId <= 0 || !in_array($newStatus, $ALLOWED_STATUSES, true)) {
        $_SESSION['costume_rental_flash'] = ['type' => 'error', 'message' => 'Thông tin cập nhật không hợp lệ.'];
        tp_rental_redirect();
    }

    $orderStmt = $conn->prepare('SELECT ID_CN, TRANG_THAI, GHI_CHU, TONG_TIEN_DU_KIEN, TIEN_COC, ID_TK, NGAY_TRA_DK, COALESCE(NGAY_TRA_THAT, NGAY_TRA_TT) AS NGAY_TRA_THAT_CUR, TRE_HEN_NGAY, PHI_TRE_HEN FROM don_thue_trang_phuc WHERE ID_TTP = ? LIMIT 1');
    if (!$orderStmt) {
        $_SESSION['costume_rental_flash'] = ['type' => 'error', 'message' => 'Không thể tải thông tin đơn thuê.'];
        tp_rental_redirect();
    }
    $orderStmt->bind_param('i', $orderId);
    $orderStmt->execute();
    $orderStmt->bind_result($orderBranch, $currentStatus, $currentNote, $expectedTotal, $depositAmount, $accountId, $expectedReturnDate, $currentReturnActual, $currentLateDays, $currentLateFee);
    $found = $orderStmt->fetch();
    $orderStmt->close();

    if (!$found) {
        $_SESSION['costume_rental_flash'] = ['type' => 'error', 'message' => 'Không tìm thấy đơn thuê.'];
        tp_rental_redirect();
    }

    if (!$isAdmin && $branchScopeId > 0 && (int) $orderBranch !== $branchScopeId) {
        $_SESSION['costume_rental_flash'] = ['type' => 'error', 'message' => 'Bạn không có quyền thao tác đơn thuê thuộc chi nhánh khác.'];
        tp_rental_redirect();
    }

    if ($newStatus === $currentStatus) {
        $_SESSION['costume_rental_flash'] = ['type' => 'info', 'message' => 'Đơn thuê đã ở đúng trạng thái bạn chọn.'];
        tp_rental_redirect();
    }

    $allowedTargets = $TRANSITIONS[$currentStatus] ?? [];
    if (!$isAdmin && !in_array($newStatus, $allowedTargets, true)) {
        $_SESSION['costume_rental_flash'] = ['type' => 'error', 'message' => 'Trạng thái mới không hợp lệ cho đơn hiện tại.'];
        tp_rental_redirect();
    }

    $expectedReturnTs = $expectedReturnDate ? strtotime($expectedReturnDate) : false;
    $isExpiredWindow = $expectedReturnTs && $expectedReturnTs < time() && !in_array($currentStatus, ['da_tra', 'huy'], true);
    if ($isExpiredWindow && $newStatus === 'da_duyet') {
        $_SESSION['costume_rental_flash'] = ['type' => 'error', 'message' => 'Đơn thuê đã quá hạn dự kiến, không thể duyệt thêm. Vui lòng cập nhật lịch hoặc tạo yêu cầu mới.'];
        tp_rental_redirect();
    }

    $requiresReturn = in_array($newStatus, ['da_tra', 'tre_hen'], true);
    $returnedAt = null;
    if ($returnedAtInput !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $returnedAtInput);
        if ($dt !== false) {
            $returnedAt = $dt->format('Y-m-d H:i:s');
        }
    } elseif ($requiresReturn) {
        $returnedAt = date('Y-m-d H:i:s');
    }

    $actualTotal = null;
    if ($actualTotalInput !== '') {
        $actualTotal = max(0, (int) preg_replace('/[^0-9]/', '', $actualTotalInput));
    } elseif ($requiresReturn) {
        $actualTotal = (int) $expectedTotal;
    }

    $noteToSave = null;
    if ($managerNote !== '') {
        $entry = sprintf('[%s] %s: %s', date('d/m H:i'), $currentUserName, $managerNote);
        $noteToSave = $currentNote ? ($currentNote . "\n" . $entry) : $entry;
    }

    $lateDays = 0;
    $lateFeeCalc = 0;
    if ($requiresReturn && $returnedAt !== null && $expectedReturnDate) {
        $tsActual = strtotime($returnedAt);
        $tsExpected = strtotime($expectedReturnDate);
        if ($tsActual !== false && $tsExpected !== false && $tsActual > $tsExpected) {
            $lateDays = (int) ceil(($tsActual - $tsExpected) / 86400);
            $lateFeeCalc = (int) round($expectedTotal * 0.02 * $lateDays);
        }
    }

    $fields = ['TRANG_THAI = ?', 'UPDATED_AT = NOW()'];
    $params = [$newStatus];
    $types  = 's';

    if ($returnedAt !== null) {
        $fields[] = 'NGAY_TRA_THAT = ?';
        $params[] = $returnedAt;
        $types   .= 's';
        $fields[] = 'NGAY_TRA_TT = ?';
        $params[] = $returnedAt;
        $types   .= 's';
    }

    if ($requiresReturn) {
        $fields[] = 'TRE_HEN_NGAY = ?';
        $params[] = $lateDays;
        $types   .= 'i';
        $fields[] = 'PHI_TRE_HEN = ?';
        $params[] = $lateFeeCalc;
        $types   .= 'i';
    }

    if ($actualTotal !== null) {
        $fields[] = 'TONG_TIEN_THUC_TE = ?';
        $params[] = $actualTotal;
        $types   .= 'i';
    }

    if ($noteToSave !== null) {
        $fields[] = 'GHI_CHU = ?';
        $params[] = $noteToSave;
        $types   .= 's';
    }

    $params[] = $orderId;
    $types   .= 'i';

    $sql = 'UPDATE don_thue_trang_phuc SET ' . implode(', ', $fields) . ' WHERE ID_TTP = ?';
    $updateStmt = $conn->prepare($sql);
    if (!$updateStmt) {
        $_SESSION['costume_rental_flash'] = ['type' => 'error', 'message' => 'Không thể cập nhật trạng thái đơn thuê.'];
        tp_rental_redirect();
    }

    $updateStmt->bind_param($types, ...$params);
    $ok = $updateStmt->execute();
    $updateStmt->close();

    if ($ok) {
        $label = $STATUS_META[$newStatus]['label'] ?? 'mới';
        $_SESSION['costume_rental_flash'] = ['type' => 'success', 'message' => "Đã cập nhật đơn #$orderId sang trạng thái {$label}."];

        // Phase 1: approval -> create initial invoice (if missing) and send rental activation email (not deposit receipt)
        if ($currentStatus === 'cho_duyet' && $newStatus === 'da_duyet') {
            // Prevent duplicate initial rental email by marker in note
            $alreadyMarked = $currentNote && str_contains($currentNote, '[RENTAL_CONF_SENT]');
            // Check & create initial invoice for this rental if not exists yet
            $invoiceIdInitial = null; $hasInitial = false; $existingInitialId = null;
            $checkInit = $conn->prepare('SELECT ID_HD FROM hoa_don WHERE ID_TTP = ? LIMIT 1');
            if ($checkInit) {
                $checkInit->bind_param('i', $orderId);
                $checkInit->execute();
                $checkInit->bind_result($existingInitialId);
                $hasInitial = $checkInit->fetch();
                $checkInit->close();
            }
            if (!$hasInitial) {
                $createInit = $conn->prepare('INSERT INTO hoa_don (NGAY_GIO, ID_LICHHEN, ID_TTP, TONG_TIEN, TRANGTHAI_THANHTOAN, PHUONGTHUC_THANHTOAN, YEU_CAU_XAC_NHAN) VALUES (NOW(), NULL, ?, ?, "Chưa thanh toán", "Chuyển khoản ngân hàng", 0)');
                if ($createInit) {
                    $createInit->bind_param('ii', $orderId, $expectedTotal);
                    if ($createInit->execute()) {
                        $invoiceIdInitial = $createInit->insert_id;
                    }
                    $createInit->close();
                }
                if ($invoiceIdInitial) {
                    // Fetch items for invoice line insertion
                    $itemsInit = [];
                    $detailInit = $conn->prepare('SELECT tp.TEN, ct.SO_LUONG, ct.DON_GIA_AP_DUNG FROM don_thue_trang_phuc_ct ct JOIN trang_phuc tp ON tp.ID_TRANG_PHUC = ct.ID_TP WHERE ct.ID_TTP = ?');
                    if ($detailInit) {
                        $detailInit->bind_param('i', $orderId);
                        $detailInit->execute();
                        $detailInit->bind_result($tenInit, $soLuongInit, $donGiaInit);
                        while ($detailInit->fetch()) {
                            $itemsInit[] = [ 'TEN' => $tenInit, 'SO_LUONG' => (int)$soLuongInit, 'DON_GIA' => (int)$donGiaInit ];
                        }
                        $detailInit->close();
                    }
                    $lineInit = $conn->prepare('INSERT INTO chi_tiet_hoa_don (ID_HD, LOAI, ID_THAM_CHIEU, TEN_MUC, DON_GIA) VALUES (?, ?, ?, ?, ?)');
                    if ($lineInit) {
                        foreach ($itemsInit as $itLine) {
                            $loai='costume'; $idRef=0; $tenMuc=$itLine['TEN'].' x'.$itLine['SO_LUONG']; $donGiaLine=$itLine['DON_GIA']*$itLine['SO_LUONG'];
                            $lineInit->bind_param('isisi', $invoiceIdInitial, $loai, $idRef, $tenMuc, $donGiaLine);
                            $lineInit->execute();
                        }
                        if ((int)$depositAmount > 0) {
                            $loai='deposit'; $idRef=0; $tenMuc='Tiền cọc'; $donGiaLine=(int)$depositAmount;
                            $lineInit->bind_param('isisi', $invoiceIdInitial, $loai, $idRef, $tenMuc, $donGiaLine);
                            $lineInit->execute();
                        }
                        $lineInit->close();
                    }
                    // Mark note to indicate initial invoice creation
                    $markInv = $conn->prepare("UPDATE don_thue_trang_phuc SET GHI_CHU = CONCAT(IFNULL(GHI_CHU,''), '\n[INITIAL_INVOICE_CREATED:" . date('d/m H:i') . "]') WHERE ID_TTP = ?");
                    if ($markInv) { $markInv->bind_param('i', $orderId); $markInv->execute(); $markInv->close(); }
                    if (isset($_SESSION['costume_rental_flash'])) {
                        $_SESSION['costume_rental_flash']['message'] .= ' (Đã tạo hóa đơn tạm #' . $invoiceIdInitial . ')';
                    }
                }
            } else {
                $invoiceIdInitial = $existingInitialId; // reuse existing
            }
            // Option A: Ghi doanh thu vào tai_chinh ngay khi tạo hóa đơn (chờ thanh toán)
            if ($invoiceIdInitial) {
                $revCheck = $conn->prepare("SELECT 1 FROM tai_chinh WHERE ID_HD = ? AND LOAI_GIAO_DICH = 'doanh thu' LIMIT 1");
                if ($revCheck) {
                    $revCheck->bind_param('i', $invoiceIdInitial);
                    $revCheck->execute();
                    $revCheck->store_result();
                    $exists = $revCheck->num_rows > 0;
                    $revCheck->close();
                    if (!$exists) {
                        $insRev = $conn->prepare("INSERT INTO tai_chinh (ID_HD, NGAY_GIAO_DICH, SO_TIEN, LOAI_GIAO_DICH, LOAI_CHI_TIET, ID_CN, TRANG_THAI) VALUES (?, NOW(), ?, 'doanh thu', 'Thuê trang phục', ?, 'chờ thanh toán')");
                        if ($insRev) {
                            $insRev->bind_param('iii', $invoiceIdInitial, $expectedTotal, $orderBranch);
                            if (!$insRev->execute()) { error_log('Insert revenue (pending) failed: '.$insRev->error); }
                            $insRev->close();
                        }
                    }
                }
            }
            $infoStmt = $conn->prepare('SELECT tk.EMAIL, tk.HO_TEN FROM tai_khoan tk WHERE tk.ID_TK = ? LIMIT 1');
            $email = $hoTen = '';
            if ($infoStmt) {
                $infoStmt->bind_param('s', $accountId);
                $infoStmt->execute();
                $infoStmt->bind_result($email, $hoTen);
                $infoStmt->fetch();
                $infoStmt->close();
            }
            if ($email && !$alreadyMarked) {
                // Fetch items for display only
                $items = [];
                $detailStmt = $conn->prepare('SELECT tp.TEN, ct.SO_LUONG, ct.DON_GIA_AP_DUNG FROM don_thue_trang_phuc_ct ct JOIN trang_phuc tp ON tp.ID_TRANG_PHUC = ct.ID_TP WHERE ct.ID_TTP = ?');
                if ($detailStmt) {
                    $detailStmt->bind_param('i', $orderId);
                    $detailStmt->execute();
                    $detailStmt->bind_result($tenTp, $soLuongTp, $donGiaTp);
                    while ($detailStmt->fetch()) {
                        $items[] = [ 'TEN' => $tenTp, 'SO_LUONG' => (int)$soLuongTp, 'DON_GIA' => (int)$donGiaTp ];
                    }
                    $detailStmt->close();
                }
                $rowsHtml = '';
                foreach ($items as $it) {
                    $lineTotal = $it['DON_GIA'] * $it['SO_LUONG'];
                    $rowsHtml .= '<tr><td style="padding:6px 8px;border:1px solid #ddd">'.htmlspecialchars($it['TEN']).'</td><td style="padding:6px 8px;border:1px solid #ddd;text-align:center">x'.(int)$it['SO_LUONG'].'</td><td style="padding:6px 8px;border:1px solid #ddd;text-align:right">'.number_format($lineTotal,0,',','.').' ₫</td></tr>';
                }
                if ((int)$depositAmount > 0) {
                    $rowsHtml .= '<tr><td style="padding:6px 8px;border:1px solid #ddd">Tiền cọc</td><td style="padding:6px 8px;border:1px solid #ddd;text-align:center">-</td><td style="padding:6px 8px;border:1px solid #ddd;text-align:right">'.number_format((int)$depositAmount,0,',','.').' ₫</td></tr>';
                }
                $remaining = (int)$expectedTotal - (int)$depositAmount;
                if ($remaining > 0) {
                    $rowsHtml .= '<tr><td style="padding:6px 8px;border:1px solid #ddd">Dự kiến còn lại</td><td style="padding:6px 8px;border:1px solid #ddd;text-align:center">-</td><td style="padding:6px 8px;border:1px solid #ddd;text-align:right">'.number_format($remaining,0,',','.').' ₫</td></tr>';
                }
                $mail = new PHPMailer(true);
                try {
                    tp_configure_mail($mail);
                    $mail->addAddress($email, $hoTen ?: $email);
                    $mail->isHTML(true);
                    $mail->Subject = 'Xác nhận đơn thuê #' . $orderId . ' – Vui lòng thanh toán tiền cọc';
                    $pickupTime = '';
                    $pickupStmt = $conn->prepare('SELECT NGAY_NHAN, NGAY_TRA_DK FROM don_thue_trang_phuc WHERE ID_TTP = ? LIMIT 1');
                    if ($pickupStmt) { $pickupStmt->bind_param('i',$orderId); $pickupStmt->execute(); $pickupStmt->bind_result($ngayNhan,$ngayTraDK); if($pickupStmt->fetch()){ $pickupTime = $ngayNhan; } $pickupStmt->close(); }
                    $mail->Body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222">'
                        .'<h2 style="color:#4f46e5;margin:0 0 12px">Xác nhận đơn thuê #' . $orderId . '</h2>'
                        .'<p>Xin chào <strong>'.htmlspecialchars($hoTen ?: $email).'</strong>, chúng tôi đã ghi nhận đơn thuê trang phục của bạn gồm các mục bên dưới. Bạn có thể đến nhận trang phục vào lúc <strong>'
                        .($pickupTime ? date('d/m/Y H:i', strtotime($pickupTime)) : '—').'</strong>. Vui lòng thanh toán <strong>tiền cọc</strong> trước để giữ và đảm bảo trang phục.</p>'
                        .'<p><strong>Tổng chi phí dự kiến:</strong> '.number_format($expectedTotal,0,',','.').' ₫</p>'
                        .((int)$depositAmount > 0 ? '<p><strong>Tiền cọc cần thanh toán trước:</strong> '.number_format((int)$depositAmount,0,',','.').' ₫</p>' : '<p>Đơn thuê này <strong>không yêu cầu tiền cọc</strong>.</p>')
                        .'<table style="border-collapse:collapse;width:100%;margin:12px 0">'
                        .'<thead><tr><th style="padding:6px 8px;border:1px solid #ddd;background:#f9fafb;text-align:left">Mục</th>'
                        .'<th style="padding:6px 8px;border:1px solid #ddd;background:#f9fafb">SL</th>'
                        .'<th style="padding:6px 8px;border:1px solid #ddd;background:#f9fafb;text-align:right">Thành tiền</th></tr></thead><tbody>'
                        .$rowsHtml.'</tbody></table>'
                        .($invoiceIdInitial ? '<p style="margin-top:12px">Hóa đơn tạm đã tạo: <strong>#'.$invoiceIdInitial.'</strong> (chưa quyết toán).</p>' : '')
                        .((int)$depositAmount > 0
                            ? '<p style="margin-top:12px">Bạn có thể thanh toán tiền cọc online qua trang quản lý hóa đơn (VNPay) hoặc chuyển khoản. Sau khi hệ thống xác nhận tiền cọc thành công bạn sẽ nhận thêm email xác nhận để làm bằng chứng khi đến nhận đồ.</p>'
                            : '')
                        .'<hr style="margin:20px 0"><p style="font-size:12px;color:#555">Email tự động – vui lòng không trả lời trực tiếp.</p></div>';
                    if ($mail->send()) {
                        $markStmt = $conn->prepare("UPDATE don_thue_trang_phuc SET GHI_CHU = CONCAT(IFNULL(GHI_CHU,''), '\n[RENTAL_CONF_SENT]') WHERE ID_TTP = ?");
                        if ($markStmt) { $markStmt->bind_param('i', $orderId); $markStmt->execute(); $markStmt->close(); }
                        if (isset($_SESSION['costume_rental_flash'])) {
                            $_SESSION['costume_rental_flash']['message'] .= ' (Email xác nhận đơn thuê đã gửi tới khách)';
                        }
                    } else {
                        error_log('Send rental confirmation failed (no exception): '.$mail->ErrorInfo);
                    }
                } catch (Exception $e) {
                    $errSnippet = substr($mail->ErrorInfo ?? $e->getMessage(), 0, 120);
                    error_log('Send rental confirmation failed: '.$errSnippet);
                    // Removed legacy marker [RENTAL_MAIL_FAIL]; keep error only in server log.
                    if (isset($_SESSION['costume_rental_flash'])) {
                        $_SESSION['costume_rental_flash']['message'] .= ' (Gửi email xác nhận đơn thuê thất bại)';
                    }
                }
            }
        }

        // Phase 2: finalize existing invoice (add late fee, remaining/refund) when moved to da_tra or tre_hen
        if (in_array($newStatus, ['da_tra','tre_hen'], true)) {
            // Load existing invoice (fallback create if legacy record missing)
            $invoiceIdFinal = null; $finalExists = false; $invStmt = $conn->prepare('SELECT ID_HD, TONG_TIEN FROM hoa_don WHERE ID_TTP = ? LIMIT 1');
            $previousTotal = 0;
            if ($invStmt) {
                $invStmt->bind_param('i', $orderId);
                $invStmt->execute();
                $invStmt->bind_result($invoiceIdFinal, $previousTotal);
                $finalExists = $invStmt->fetch();
                $invStmt->close();
            }
            if (!$finalExists) {
                // Fallback create (legacy orders never approved under new logic)
                $baseTotal = $actualTotal ?? $expectedTotal;
                $createFallback = $conn->prepare('INSERT INTO hoa_don (NGAY_GIO, ID_LICHHEN, ID_TTP, TONG_TIEN, TRANGTHAI_THANHTOAN, PHUONGTHUC_THANHTOAN, YEU_CAU_XAC_NHAN) VALUES (NOW(), NULL, ?, ?, "Chưa thanh toán", "Chuyển khoản ngân hàng", 0)');
                if ($createFallback) {
                    $createFallback->bind_param('ii', $orderId, $baseTotal);
                    if ($createFallback->execute()) { $invoiceIdFinal = $createFallback->insert_id; }
                    $createFallback->close();
                    $previousTotal = $baseTotal;
                }
                // Also record pending revenue when creating fallback invoice
                if ($invoiceIdFinal) {
                    $revCheck2 = $conn->prepare("SELECT 1 FROM tai_chinh WHERE ID_HD = ? AND LOAI_GIAO_DICH = 'doanh thu' LIMIT 1");
                    if ($revCheck2) {
                        $revCheck2->bind_param('i', $invoiceIdFinal);
                        $revCheck2->execute();
                        $revCheck2->store_result();
                        $exists2 = $revCheck2->num_rows > 0;
                        $revCheck2->close();
                        if (!$exists2) {
                            $insRev2 = $conn->prepare("INSERT INTO tai_chinh (ID_HD, NGAY_GIAO_DICH, SO_TIEN, LOAI_GIAO_DICH, LOAI_CHI_TIET, ID_CN, TRANG_THAI) VALUES (?, NOW(), ?, 'doanh thu', 'Thuê trang phục', ?, 'chờ thanh toán')");
                            if ($insRev2) {
                                $insRev2->bind_param('iii', $invoiceIdFinal, $baseTotal, $orderBranch);
                                if (!$insRev2->execute()) { error_log('Insert revenue (pending/fallback) failed: '.$insRev2->error); }
                                $insRev2->close();
                            }
                        }
                    }
                }
            }
            if ($invoiceIdFinal) {
                // Use computed late metrics (fallback re-check if needed)
                $returnActual = $returnedAt ?? ($currentReturnActual ?? date('Y-m-d H:i:s'));
                $lateFee = $lateFeeCalc;
                $daysLate = $lateDays;
                if ($lateFee === 0 && $expectedReturnDate && $returnActual) {
                    $tsExpected = strtotime($expectedReturnDate);
                    $tsActual = strtotime($returnActual);
                    if ($tsExpected !== false && $tsActual !== false && $tsActual > $tsExpected) {
                        $hoursLate = (int) ceil(($tsActual - $tsExpected) / 3600);
                        $daysLate = (int) ceil($hoursLate / 24);
                        $lateFee = (int) round($expectedTotal * 0.02 * $daysLate);
                    }
                }
                // Determine final total before deposit adjustment
                $coreFinal = $actualTotal ?? $expectedTotal;
                $grandFinal = $coreFinal + $lateFee;
                // Update invoice total
                $updInv = $conn->prepare('UPDATE hoa_don SET TONG_TIEN = ? WHERE ID_HD = ?');
                if ($updInv) { $updInv->bind_param('ii', $grandFinal, $invoiceIdFinal); $updInv->execute(); $updInv->close(); }
                // Clean previous dynamic lines to avoid duplicates (remaining/refund/late fee)
                $cleanup = $conn->prepare("DELETE FROM chi_tiet_hoa_don WHERE ID_HD = ? AND LOAI IN ('phu_phi','remaining','refund')");
                if ($cleanup) { $cleanup->bind_param('i', $invoiceIdFinal); $cleanup->execute(); $cleanup->close(); }
                // Add late fee line if any
                $lineStmt2 = $conn->prepare('INSERT INTO chi_tiet_hoa_don (ID_HD, LOAI, ID_THAM_CHIEU, TEN_MUC, DON_GIA) VALUES (?, ?, ?, ?, ?)');
                if ($lineStmt2) {
                    if ($lateFee > 0) { $loai='phu_phi'; $idRef=0; $tenMuc='Phụ phí trễ hạn'; $donGiaLine=$lateFee; $lineStmt2->bind_param('isisi',$invoiceIdFinal,$loai,$idRef,$tenMuc,$donGiaLine); $lineStmt2->execute(); }
                    // Calculate remaining/refund after deposit
                    $remainingFinal = $grandFinal - (int)$depositAmount;
                    if ($remainingFinal > 0) { $loai='remaining'; $idRef=0; $tenMuc='Còn phải thanh toán'; $donGiaLine=$remainingFinal; $lineStmt2->bind_param('isisi',$invoiceIdFinal,$loai,$idRef,$tenMuc,$donGiaLine); $lineStmt2->execute(); }
                    elseif ($remainingFinal < 0) { $loai='refund'; $idRef=0; $tenMuc='Hoàn lại cho khách'; $donGiaLine=abs($remainingFinal); $lineStmt2->bind_param('isisi',$invoiceIdFinal,$loai,$idRef,$tenMuc,$donGiaLine); $lineStmt2->execute(); }
                    $lineStmt2->close();
                }
                // Send finalization email
                $info2 = $conn->prepare('SELECT tk.EMAIL, tk.HO_TEN FROM tai_khoan tk WHERE tk.ID_TK = ? LIMIT 1'); $email2=''; $hoTen2='';
                if ($info2){ $info2->bind_param('s',$accountId); $info2->execute(); $info2->bind_result($email2,$hoTen2); $info2->fetch(); $info2->close(); }
                if ($email2) {
                    // Build rows HTML from existing invoice lines for transparency
                    $rowsHtml2='';
                    $fetchLines = $conn->prepare('SELECT LOAI, TEN_MUC, DON_GIA FROM chi_tiet_hoa_don WHERE ID_HD = ? ORDER BY ID_CTHD');
                    if ($fetchLines) {
                        $fetchLines->bind_param('i',$invoiceIdFinal); $fetchLines->execute(); $fetchLines->bind_result($loaiL,$tenL,$giaL);
                        while ($fetchLines->fetch()) {
                            $rowsHtml2 .= '<tr><td style="padding:6px 8px;border:1px solid #ddd">'.htmlspecialchars($tenL).'</td><td style="padding:6px 8px;border:1px solid #ddd;text-align:center">-</td><td style="padding:6px 8px;border:1px solid #ddd;text-align:right">'.number_format((int)$giaL,0,',','.').' ₫</td></tr>';
                        }
                        $fetchLines->close();
                    }
                    $mail2 = new PHPMailer(true);
                    try {
                        tp_configure_mail($mail2);
                        $mail2->addAddress($email2,$hoTen2?:$email2);
                        $mail2->isHTML(true);
                        $mail2->Subject='Hóa đơn hoàn tất đơn thuê #'.$orderId;
                        $mail2->Body=
                            '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222">'
                            .'<h2 style="color:#4f46e5;margin:0 0 12px">Hóa đơn hoàn tất #'.$orderId.'</h2>'
                            .'<p>Xin chào <strong>'.htmlspecialchars($hoTen2?:$email2).'</strong>, đây là hóa đơn cuối cùng sau khi bạn trả trang phục. Các mục dưới phản ánh tiền cọc, phụ phí (nếu có) và số tiền còn phải thanh toán hoặc hoàn lại.</p>'
                            .'<p><strong>Tổng thực tế:</strong> '.number_format($grandFinal,0,',','.').' ₫</p>'
                            .'<table style="border-collapse:collapse;width:100%;margin:12px 0">'
                            .'<thead><tr>'
                                .'<th style="padding:6px 8px;border:1px solid #ddd;background:#f9fafb;text-align:left">Mục</th>'
                                .'<th style="padding:6px 8px;border:1px solid #ddd;background:#f9fafb">SL</th>'
                                .'<th style="padding:6px 8px;border:1px solid #ddd;background:#f9fafb;text-align:right">Thành tiền</th>'
                            .'</tr></thead><tbody>'
                            .$rowsHtml2
                            .'</tbody></table>'
                            .(($grandFinal - (int)$depositAmount) > 0
                                ? '<p><strong>Số tiền còn phải thanh toán:</strong> '.number_format($grandFinal - (int)$depositAmount,0,',','.').' ₫</p>'
                                : (($grandFinal - (int)$depositAmount) < 0
                                    ? '<p><strong>Số tiền cần hoàn lại cho bạn:</strong> '.number_format(abs($grandFinal - (int)$depositAmount),0,',','.').' ₫</p>'
                                    : '<p><strong>Thanh toán:</strong> Bạn đã thanh toán đủ sau khi trừ tiền cọc.</p>'))
                            .'<p style="margin-top:12px">Nếu có thắc mắc về hóa đơn, vui lòng liên hệ studio.</p>'
                            .'<hr style="margin:20px 0"><p style="font-size:12px;color:#555">Email tự động – vui lòng không trả lời trực tiếp.</p>'
                            .'</div>';
                        $mail2->send();
                    } catch (Exception $e) {
                        $errSnippet2 = substr($mail2->ErrorInfo ?? $e->getMessage(),0,120);
                        error_log('Send final invoice failed: '.$errSnippet2);
                        // Removed legacy marker [FINAL_INVOICE_MAIL_FAIL]; keep error only in server log.
                    }
                }
            }
        }
    } else {
        $_SESSION['costume_rental_flash'] = ['type' => 'error', 'message' => 'Không thể lưu thay đổi, vui lòng thử lại.'];
    }
    tp_rental_redirect();
}

$flash = $_SESSION['costume_rental_flash'] ?? null;
unset($_SESSION['costume_rental_flash']);

$branches = [];
$branchResult = $conn->query('SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN');
if ($branchResult) {
    while ($row = $branchResult->fetch_assoc()) {
        $branches[(int) $row['ID_CN']] = $row['TEN_CN'];
    }
}

$searchTerm   = trim($_GET['search'] ?? '');
$statusFilter = array_key_exists('status', $_GET) ? ($_GET['status'] ?? '') : 'cho_duyet';
$branchFilter = isset($_GET['branch']) ? (int) $_GET['branch'] : 0;
$fromDate     = trim($_GET['from_date'] ?? '');
$toDate       = trim($_GET['to_date'] ?? '');

if (!$isAdmin && $branchScopeId > 0) {
    $branchFilter = $branchScopeId;
}

$dataConditionsBase = [];
if ($branchFilter > 0) {
    $dataConditionsBase[] = 'ttp.ID_CN = ' . (int) $branchFilter;
}
if ($fromDate !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $fromDate);
    if ($dt) {
        $dataConditionsBase[] = "DATE(ttp.NGAY_NHAN) >= '" . $dt->format('Y-m-d') . "'";
        $fromDate = $dt->format('Y-m-d');
    } else {
        $fromDate = '';
    }
}
if ($toDate !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $toDate);
    if ($dt) {
        $dataConditionsBase[] = "DATE(ttp.NGAY_NHAN) <= '" . $dt->format('Y-m-d') . "'";
        $toDate = $dt->format('Y-m-d');
    } else {
        $toDate = '';
    }
}
if ($searchTerm !== '') {
    $escaped = $conn->real_escape_string($searchTerm);
    $like = "'%" . $escaped . "%'";
    $dataConditionsBase[] = "(tk.HO_TEN LIKE $like OR tk.EMAIL LIKE $like OR tk.SDT LIKE $like OR tp.TEN LIKE $like OR CAST(ttp.ID_TTP AS CHAR) = '" . $conn->real_escape_string($searchTerm) . "')";
}

$dataConditionsFiltered = $dataConditionsBase;
if ($statusFilter !== '' && isset($STATUS_META[$statusFilter])) {
    $dataConditionsFiltered[] = "ttp.TRANG_THAI = '" . $conn->real_escape_string($statusFilter) . "'";
} else {
    $statusFilter = '';
}

$whereSql       = $dataConditionsFiltered ? 'WHERE ' . implode(' AND ', $dataConditionsFiltered) : '';
$whereSqlNoStat = $dataConditionsBase ? 'WHERE ' . implode(' AND ', $dataConditionsBase) : '';

$baseFrom = ' FROM don_thue_trang_phuc ttp '
    . 'JOIN tai_khoan tk ON tk.ID_TK = ttp.ID_TK '
    . 'JOIN chi_nhanh cn ON cn.ID_CN = ttp.ID_CN '
    . 'LEFT JOIN don_thue_trang_phuc_ct ct ON ct.ID_TTP = ttp.ID_TTP '
    . 'LEFT JOIN trang_phuc tp ON tp.ID_TRANG_PHUC = ct.ID_TP ';

$countSql = 'SELECT COUNT(DISTINCT ttp.ID_TTP) AS total' . $baseFrom . ' ' . $whereSql;
$countResult = $conn->query($countSql);
$totalRows = $countResult ? (int) $countResult->fetch_assoc()['total'] : 0;

$statusSql = 'SELECT ttp.TRANG_THAI, COUNT(DISTINCT ttp.ID_TTP) AS total' . $baseFrom . ' ' . $whereSqlNoStat . ' GROUP BY ttp.TRANG_THAI';
$statusResult = $conn->query($statusSql);
$statusCounts = [];
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        $statusCounts[$row['TRANG_THAI']] = (int) $row['total'];
    }
}

$perPage = 10;
$page    = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$listSql = 'SELECT ttp.ID_TTP, ttp.NGAY_DAT, ttp.NGAY_NHAN, ttp.NGAY_TRA_DK, COALESCE(ttp.NGAY_TRA_THAT, ttp.NGAY_TRA_TT) AS NGAY_TRA_THAT, ttp.TRE_HEN_NGAY, ttp.PHI_TRE_HEN, ttp.TRANG_THAI,'
    . ' ttp.TIEN_COC, ttp.TONG_TIEN_DU_KIEN, ttp.TONG_TIEN_THUC_TE, ttp.GHI_CHU,'
    . ' tk.HO_TEN, tk.EMAIL, tk.SDT, cn.TEN_CN,'
    . " GROUP_CONCAT(DISTINCT CONCAT(tp.TEN, ' (x', ct.SO_LUONG, ')') ORDER BY tp.TEN SEPARATOR ', ') AS ITEM_LABELS"
    . $baseFrom . ' ' . $whereSql
    . ' GROUP BY ttp.ID_TTP'
    . ' ORDER BY ttp.NGAY_DAT DESC'
    . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

$listResult = $conn->query($listSql);
$orders = [];
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
        $orders[] = $row;
    }
}

$orderDetails = [];
if ($orders) {
    $ids = array_map(static fn($order) => (int) $order['ID_TTP'], $orders);
    $detailSql = 'SELECT ct.ID_TTP, tp.TEN, tp.SIZE, tp.MAU_SAC, ct.SO_LUONG, ct.DON_GIA_AP_DUNG '
        . 'FROM don_thue_trang_phuc_ct ct '
        . 'JOIN trang_phuc tp ON tp.ID_TRANG_PHUC = ct.ID_TP '
        . 'WHERE ct.ID_TTP IN (' . implode(',', $ids) . ') '
        . 'ORDER BY ct.ID_TTP, tp.TEN';
    $detailResult = $conn->query($detailSql);
    if ($detailResult) {
        while ($row = $detailResult->fetch_assoc()) {
            $orderDetails[(int) $row['ID_TTP']][] = $row;
        }
    }
}

function tp_format_currency(?int $value): string
{
    if ($value === null) {
        return '—';
    }
    return number_format($value, 0, ',', '.') . ' ₫';
}
?>
<div class="max-w-7xl mx-auto bg-white/90 backdrop-blur rounded-2xl shadow-xl p-6 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Quản lý yêu cầu thuê trang phục</h1>
        <p class="text-gray-500">Theo dõi trạng thái đơn thuê, phê duyệt và ghi nhận giao nhận tại chi nhánh.</p>
    </div>

    <?php if ($flash): ?>
        <?php
            $flashColors = [
                'success' => 'bg-emerald-50 border-emerald-200 text-emerald-700',
                'error'   => 'bg-rose-50 border-rose-200 text-rose-700',
                'info'    => 'bg-blue-50 border-blue-200 text-blue-700',
            ];
            $flashClass = $flashColors[$flash['type']] ?? 'bg-gray-50 border-gray-200 text-gray-700';
        ?>
        <div class="relative border <?php echo $flashClass; ?> px-4 py-3 rounded-xl flex items-start gap-3" role="alert" aria-live="polite">
            <span class="font-semibold text-sm">Thông báo:</span>
            <span class="text-sm flex-1"><?php echo htmlspecialchars($flash['message']); ?></span>
            <button type="button" data-flash-close class="text-sm font-bold leading-none select-none px-2 py-1 rounded hover:bg-white/40 focus:outline-none" aria-label="Đóng thông báo">×</button>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?php foreach ($STATUS_META as $code => $meta): ?>
            <a href="?page=costume_rentals&status=<?php echo $code; ?>" class="group border border-gray-200 rounded-2xl p-4 bg-white hover:border-indigo-300 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition" aria-label="Lọc trạng thái <?php echo htmlspecialchars($meta['label']); ?>">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2.5 h-2.5 rounded-full <?php echo $meta['dot']; ?>"></span>
                    <span class="text-sm font-semibold text-gray-700 group-hover:text-indigo-700"><?php echo htmlspecialchars($meta['label']); ?></span>
                </div>
                <div class="text-3xl font-bold text-gray-900 group-hover:text-indigo-600"><?php echo $statusCounts[$code] ?? 0; ?></div>
                <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?php echo htmlspecialchars($meta['desc']); ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="GET" data-action="filter_rentals" class="bg-gray-50 border border-gray-200 rounded-2xl p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 text-sm">
        <input type="hidden" name="page" value="costume_rentals">
        <div class="lg:col-span-2">
            <label class="block text-gray-600 font-medium mb-1">Tìm kiếm</label>
            <input id="rental-search" type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" data-action="search" placeholder="Tên khách, email, SĐT hoặc mã đơn" class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" autocomplete="off">
        </div>
        <div>
            <label class="block text-gray-600 font-medium mb-1">Trạng thái</label>
            <select name="status" class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Tất cả</option>
                <?php foreach ($STATUS_META as $code => $meta): ?>
                    <option value="<?php echo $code; ?>" <?php echo $statusFilter === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($meta['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-gray-600 font-medium mb-1">Từ ngày nhận</label>
            <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
            <label class="block text-gray-600 font-medium mb-1">Đến ngày nhận</label>
            <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
            <label class="block text-gray-600 font-medium mb-1">Chi nhánh</label>
            <?php if (!$isAdmin && $branchScopeId > 0): ?>
                <input type="text" readonly class="w-full border border-gray-200 bg-gray-100 rounded-xl px-3 py-2" value="<?php echo htmlspecialchars($branches[$branchScopeId] ?? 'Chi nhánh của bạn'); ?>">
            <?php else: ?>
                <select name="branch" class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="0">Tất cả</option>
                    <?php foreach ($branches as $id => $name): ?>
                        <option value="<?php echo $id; ?>" <?php echo $branchFilter === (int) $id ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        <div class="lg:col-span-5 flex justify-end gap-2">
            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition">Áp dụng</button>
            <a href="?page=costume_rentals" class="px-4 py-2 rounded-xl bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">Đặt lại</a>
        </div>
    </form>

    <div class="border border-gray-200 rounded-2xl overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Đơn thuê</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Khách hàng</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Chi nhánh</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Thời gian</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Chi phí</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Trạng thái</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Thao tác</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                <?php if (!$orders): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">Không tìm thấy đơn thuê nào khớp bộ lọc.</td>
                    </tr>
                <?php else: ?>
                    <?php $nowTs = time(); foreach ($orders as $order): 
                        $statusMeta = $STATUS_META[$order['TRANG_THAI']] ?? ['label' => $order['TRANG_THAI'], 'badge' => 'bg-gray-100 text-gray-600'];
                        $orderId = (int) $order['ID_TTP'];
                        $detailId = 'order-detail-' . $orderId;
                        $expectedReturnTs = strtotime($order['NGAY_TRA_DK']);
                        $receiveTs = strtotime($order['NGAY_NHAN']);
                        $durationDays = max(1, (int) ceil(($expectedReturnTs - $receiveTs) / 86400));
                        $hasExpiredWindow = ($expectedReturnTs && $expectedReturnTs < $nowTs && !in_array($order['TRANG_THAI'], ['da_tra', 'huy'], true));
                        $overdueHours = $hasExpiredWindow ? max(1, (int) floor(($nowTs - $expectedReturnTs) / 3600)) : 0;
                        $rowExtraClass = $hasExpiredWindow ? 'bg-rose-50/60' : '';
                        $approvalLocked = $hasExpiredWindow && in_array($order['TRANG_THAI'], ['cho_duyet', 'da_duyet'], true);
                    ?>
                        <tr class="hover:bg-gray-50 transition <?php echo $rowExtraClass; ?>"<?php if($hasExpiredWindow){ echo ' aria-label="Đơn quá hạn"'; } ?>>
                            <td class="px-4 py-2.5 align-top">
                                <div class="font-semibold text-gray-800">#<?php echo $orderId; ?></div>
                                <div class="text-xs text-gray-500">Đặt: <?php echo date('d/m/Y H:i', strtotime($order['NGAY_DAT'])); ?></div>
                                <button type="button" data-toggle-detail="<?php echo $detailId; ?>" class="text-xs text-indigo-600 mt-1 hover:underline">Xem chi tiết</button>
                            </td>
                            <td class="px-4 py-2.5 align-top">
                                <div class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($order['HO_TEN']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($order['EMAIL']); ?></div>
                            </td>
                            <td class="px-4 py-2.5 align-top">
                                <div class="text-sm text-gray-800"><?php echo htmlspecialchars($order['TEN_CN']); ?></div>
                                <div class="text-xs text-gray-500 line-clamp-1" title="<?php echo htmlspecialchars($order['ITEM_LABELS'] ?? ''); ?>"><?php echo htmlspecialchars($order['ITEM_LABELS'] ?? '—'); ?></div>
                            </td>
                            <td class="px-4 py-2.5 align-top text-sm text-gray-700">
                                <div class="font-semibold text-gray-900">Nhận: <?php echo date('d/m H:i', strtotime($order['NGAY_NHAN'])); ?> · Trả: <?php echo date('d/m H:i', $expectedReturnTs); ?></div>
                                <?php if ($hasExpiredWindow): ?>
                                    <div class="text-xs font-semibold text-rose-600">Quá hạn <?php echo $overdueHours; ?> giờ</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5 align-top text-sm text-gray-700 whitespace-nowrap">
                                <div class="font-semibold text-indigo-700"><?php echo tp_format_currency((int) $order['TONG_TIEN_DU_KIEN']); ?></div>
                                <div class="text-xs text-gray-500">Cọc: <?php echo tp_format_currency((int) $order['TIEN_COC']); ?></div>
                            </td>
                            <td class="px-4 py-2.5 align-top whitespace-nowrap">
                                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap <?php echo $statusMeta['badge']; ?>" title="<?php echo htmlspecialchars($STATUS_META[$order['TRANG_THAI']]['desc'] ?? ''); ?>">
                                    <span class="w-2 h-2 rounded-full <?php echo $statusMeta['dot'] ?? 'bg-gray-400'; ?>"></span>
                                    <?php echo htmlspecialchars($statusMeta['label']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-2.5 align-top text-right whitespace-nowrap">
                                <button type="button" data-toggle-detail="<?php echo $detailId; ?>" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-indigo-200 text-indigo-600 text-sm hover:bg-indigo-50 whitespace-nowrap">
                                    Cập nhật
                                </button>
                            </td>
                        </tr>
                        <tr id="<?php echo $detailId; ?>" class="hidden">
                            <td colspan="7" class="bg-gray-50 px-4 py-5">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <div class="space-y-4">
                                        <div>
                                            <h3 class="text-sm font-semibold text-gray-700">Chi tiết trang phục</h3>
                                            <div class="mt-2 space-y-2">
                                                <?php if (!empty($orderDetails[$orderId])): ?>
                                                    <?php foreach ($orderDetails[$orderId] as $item): ?>
                                                        <div class="flex justify-between text-sm text-gray-700 bg-white border border-gray-200 rounded-xl px-3 py-2">
                                                            <div>
                                                                <div class="font-medium"><?php echo htmlspecialchars($item['TEN']); ?></div>
                                                                <div class="text-xs text-gray-500">Size: <?php echo htmlspecialchars($item['SIZE'] ?: '—'); ?> · Màu: <?php echo htmlspecialchars($item['MAU_SAC'] ?: '—'); ?></div>
                                                            </div>
                                                            <div class="text-right">
                                                                <div class="font-semibold"><?php echo 'x' . (int) $item['SO_LUONG']; ?></div>
                                                                <div class="text-xs text-gray-500"><?php echo tp_format_currency((int) $item['DON_GIA_AP_DUNG']); ?></div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="text-sm text-gray-500">Chưa có dữ liệu chi tiết.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-semibold text-gray-700">Ghi chú</h3>
                                            <div class="mt-2 text-sm text-gray-600 bg-white border border-gray-200 rounded-xl px-3 py-2 min-h-[60px] whitespace-pre-wrap"><?php echo htmlspecialchars($order['GHI_CHU'] ?: 'Chưa có ghi chú.'); ?></div>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div class="bg-white border border-gray-200 rounded-xl p-3">
                                                <div class="text-xs font-semibold text-gray-600 mb-1">Thông tin khách</div>
                                                <div class="text-sm text-gray-800"><?php echo htmlspecialchars($order['HO_TEN']); ?></div>
                                                <div class="text-xs text-gray-500 break-words"><?php echo htmlspecialchars($order['EMAIL']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($order['SDT']); ?></div>
                                            </div>
                                            <div class="bg-white border border-gray-200 rounded-xl p-3">
                                                <div class="text-xs font-semibold text-gray-600 mb-1">Thanh toán</div>
                                                <div class="flex justify-between text-sm text-gray-700"><span>Dự kiến</span><span class="font-semibold"><?php echo tp_format_currency((int)$order['TONG_TIEN_DU_KIEN']); ?></span></div>
                                                <div class="flex justify-between text-sm text-gray-700"><span>Cọc</span><span><?php echo tp_format_currency((int)$order['TIEN_COC']); ?></span></div>
                                                <div class="flex justify-between text-sm text-gray-700"><span>Phụ phí trễ</span><span><?php echo tp_format_currency((int)$order['PHI_TRE_HEN']); ?></span></div>
                                                <div class="flex justify-between text-sm text-gray-700"><span>Tổng thực tế</span><span><?php echo tp_format_currency($order['TONG_TIEN_THUC_TE'] ? (int)$order['TONG_TIEN_THUC_TE'] : null); ?></span></div>
                                                <?php
                                                    $actual = $order['TONG_TIEN_THUC_TE'] ? (int)$order['TONG_TIEN_THUC_TE'] : null;
                                                    $lateFee = (int)$order['PHI_TRE_HEN'];
                                                    $expected = (int)$order['TONG_TIEN_DU_KIEN'];
                                                    $base = $actual ?? $expected;
                                                    $grand = $base + $lateFee;
                                                    $remaining = $grand - (int)$order['TIEN_COC'];
                                                ?>
                                                <div class="mt-2 text-xs text-gray-500">Còn thu/hoàn sau cọc:</div>
                                                <div class="text-sm font-semibold <?php echo $remaining > 0 ? 'text-indigo-700' : ($remaining < 0 ? 'text-emerald-700' : 'text-gray-700'); ?>">
                                                    <?php echo $remaining > 0 ? ('Cần thu ' . tp_format_currency($remaining)) : ($remaining < 0 ? ('Hoàn lại ' . tp_format_currency(abs($remaining))) : 'Đủ sau cọc'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-700 mb-2">Cập nhật trạng thái</h3>
                                        <form method="POST" data-action="update_rental_status" class="space-y-3 bg-white border border-gray-200 rounded-2xl p-4">
                                            <input type="hidden" name="rental_action" value="update">
                                            <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-1">Trạng thái mới</label>
                                                <?php
                                                    $allowedForThisOrder = $isAdmin ? $ALLOWED_STATUSES : array_unique(array_merge([$order['TRANG_THAI']], $TRANSITIONS[$order['TRANG_THAI']] ?? []));
                                                    $restrictedStatuses = $approvalLocked ? ['da_duyet'] : [];
                                                ?>
                                                <select name="new_status" class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500" aria-label="Chọn trạng thái mới">
                                                    <?php foreach ($ALLOWED_STATUSES as $status):
                                                        $disabled = (!in_array($status, $allowedForThisOrder, true) || (in_array($status, $restrictedStatuses, true) && $status !== $order['TRANG_THAI']));
                                                    ?>
                                                        <option value="<?php echo $status; ?>" <?php echo $status === $order['TRANG_THAI'] ? 'selected' : ''; ?> <?php echo $disabled ? 'disabled' : ''; ?>>
                                                            <?php echo htmlspecialchars($STATUS_META[$status]['label']); ?><?php echo $disabled ? ' (không hợp lệ)' : ''; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if($approvalLocked): ?>
                                                    <p class="mt-1 text-[11px] text-rose-600">Đơn đã quá hạn, không thể chuyển về trạng thái "Đã duyệt".</p>
                                                <?php elseif(!$isAdmin): ?>
                                                    <p class="mt-1 text-[11px] text-gray-500">Chỉ có trạng thái không bị mờ là chuyển đổi được.</p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Thời gian trả thực tế</label>
                                                    <input type="datetime-local" name="returned_at" class="w-full border border-gray-300 rounded-xl px-3 py-2" value="<?php echo $order['NGAY_TRA_THAT'] ? date('Y-m-d\TH:i', strtotime($order['NGAY_TRA_THAT'])) : ''; ?>">
                                                </div>
                                                <div>
                                                    <label class="text-xs font-semibold text-gray-600 mb-1 inline-flex items-center gap-1">Tổng tiền thực tế <span data-tip="Không cộng lại tiền cọc. Là tổng phí cuối cùng trước khi đối chiếu cọc." class="inline-flex items-center"><button type="button" class="text-[10px] text-gray-400 hover:text-indigo-500" aria-label="Giải thích">?</button></span></label>
                                                    <input type="number" min="0" name="actual_total" class="w-full border border-gray-300 rounded-xl px-3 py-2" value="<?php echo htmlspecialchars((string) ($order['TONG_TIEN_THUC_TE'] ?? '')); ?>" placeholder="theo VNĐ">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-1">Ghi chú bổ sung</label>
                                                <textarea name="manager_note" rows="3" class="w-full border border-gray-300 rounded-xl px-3 py-2" placeholder="Ví dụ: Khách muốn kéo dài thêm 1 ngày..."></textarea>
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl font-semibold hover:bg-indigo-700">Lưu cập nhật</button>
                                                <button type="button" data-toggle-detail="<?php echo $detailId; ?>" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-600 hover:bg-gray-100">Đóng</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalRows > $perPage): ?>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between text-sm text-gray-600 gap-2">
            <div>Hiển thị <?php echo count($orders); ?> / <?php echo $totalRows; ?> đơn thuê</div>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                    <a href="?page=costume_rentals&p=<?php echo $page - 1; ?>" class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">« Trước</a>
                <?php else: ?>
                    <span class="px-3 py-1 border border-gray-200 rounded-lg text-gray-400">« Trước</span>
                <?php endif; ?>
                <span class="px-3 py-1">Trang <?php echo $page; ?> / <?php echo $totalPages; ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=costume_rentals&p=<?php echo $page + 1; ?>" class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">Tiếp »</a>
                <?php else: ?>
                    <span class="px-3 py-1 border border-gray-200 rounded-lg text-gray-400">Tiếp »</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
        // Simple tooltip system
        const tipRoot = document.createElement('div');
        tipRoot.className = 'pointer-events-none fixed z-50 px-2 py-1 rounded bg-gray-800 text-[11px] text-white shadow opacity-0 transition-opacity duration-150';
        document.body.appendChild(tipRoot);
        let tipVisible = false; let hideTimeout;
        function showTip(el){
            const msg = el.getAttribute('data-tip');
            if(!msg) return;
            tipRoot.textContent = msg;
            const rect = el.getBoundingClientRect();
            tipRoot.style.top = (rect.top + window.scrollY - tipRoot.offsetHeight - 6) + 'px';
            tipRoot.style.left = (rect.left + window.scrollX) + 'px';
            tipRoot.classList.remove('opacity-0');
            tipRoot.classList.add('opacity-100');
            tipVisible = true;
        }
        function hideTip(){
            tipRoot.classList.add('opacity-0');
            tipRoot.classList.remove('opacity-100');
            tipVisible = false;
        }
        document.addEventListener('mouseover', (e)=>{
            const t = e.target.closest('[data-tip]');
            if(t){
                clearTimeout(hideTimeout);
                showTip(t);
            } else if(tipVisible){
                hideTimeout = setTimeout(hideTip, 120);
            }
        });
        document.addEventListener('scroll', ()=>{ if(tipVisible) hideTip(); }, {passive:true});
    // Flash dismiss & auto-hide
    const flashEl = document.querySelector('[role="alert"]');
    if (flashEl) {
        const closeBtn = flashEl.querySelector('[data-flash-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => flashEl.remove());
        }
        setTimeout(() => {
            if (flashEl) {
                flashEl.classList.add('opacity-0','transition');
                setTimeout(() => flashEl.remove(), 400);
            }
        }, 5000);
    }

    // Auto focus search if empty
    const searchInput = document.getElementById('rental-search');
    if (searchInput && !searchInput.value) {
        searchInput.focus();
    }
});
</script>

<!-- API Client Utilities -->
<script src="../../public/assets/js/api-client.js"></script>

<!-- Page-Specific Rental Management -->
<script src="../../public/assets/js/manage-costume-rentals.js"></script>
