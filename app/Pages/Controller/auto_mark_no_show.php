<?php
/**
 * Auto-mark no-show for appointments
 * Run this script via cron job every 5-10 minutes
 * Example: */5 * * * * php /path/to/auto_mark_no_show.php
 */

include '../../database/config.php';

try {
    $now = new DateTime('now');
    
    // Find appointments that:
    // 1. Started more than 1 hour ago
    // 2. Status is not "Da hoan thanh" or "Da huy"
    // 3. NOT already marked as no-show
    $query = "
        SELECT lh.ID_LICHHEN, lh.THOI_GIAN_BAT_DAU, lh.TRANGTHAI, tk.EMAIL, tk.HO_TEN, dv.TEN_DV
        FROM lich_hen lh
        JOIN tai_khoan tk ON lh.ID_TK = tk.ID_TK
        JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
        LEFT JOIN lich_hen_no_show ns ON lh.ID_LICHHEN = ns.ID_LICHHEN
        WHERE 
            DATE_ADD(lh.THOI_GIAN_BAT_DAU, INTERVAL 1 HOUR) <= NOW()
            AND lh.TRANGTHAI NOT IN ('Da hoan thanh', 'Da huy')
            AND ns.ID_LICHHEN IS NULL
    ";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        error_log("Auto no-show query failed: " . mysqli_error($conn));
        http_response_code(500);
        exit("Database error");
    }
    
    $appointments = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
    
    $markedCount = 0;
    
    foreach ($appointments as $appt) {
        $idLichHen = (int)$appt['ID_LICHHEN'];
        
        // Insert no-show record
        $insertQuery = "
            INSERT INTO lich_hen_no_show 
            (ID_LICHHEN, KHACH_XUA_HIEN, LI_DO_KHONG_DEN, THOI_GIAN_KIEM_TRA_KHONG_DEN, CREATED_AT)
            VALUES (?, 1, 'Tu dong phat hien (qua han 1 gio)', NOW(), NOW())
        ";
        
        $stmt = mysqli_prepare($conn, $insertQuery);
        if (!$stmt) {
            error_log("Insert no-show prepare failed: " . mysqli_error($conn));
            continue;
        }
        
        mysqli_stmt_bind_param($stmt, 'i', $idLichHen);
        
        if (mysqli_stmt_execute($stmt)) {
            $markedCount++;
            
            // Update appointment status to "Không đến"
            $updateStatusQuery = "UPDATE lich_hen SET TRANGTHAI = 'Không đến' WHERE ID_LICHHEN = ?";
            $statusStmt = mysqli_prepare($conn, $updateStatusQuery);
            if ($statusStmt) {
                mysqli_stmt_bind_param($statusStmt, 'i', $idLichHen);
                mysqli_stmt_execute($statusStmt);
                mysqli_stmt_close($statusStmt);
            }
            
            // Log notification
            $logQuery = "
                INSERT INTO lich_hen_notification_log 
                (ID_LICHHEN, LOAI_THONG_BAO, EMAIL_NHAN, TRANG_THAI_GUI, RESPONSE, THOI_GIAN_GUI)
                VALUES (?, 'auto_no_show', ?, 'sent', 'Auto-marked after 1 hour', NOW())
            ";
            
            $logStmt = mysqli_prepare($conn, $logQuery);
            if ($logStmt) {
                $email = $appt['EMAIL'];
                mysqli_stmt_bind_param($logStmt, 'is', $idLichHen, $email);
                mysqli_stmt_execute($logStmt);
                mysqli_stmt_close($logStmt);
            }
        } else {
            error_log("Insert no-show execute failed for ID {$idLichHen}: " . mysqli_stmt_error($stmt));
        }
        
        mysqli_stmt_close($stmt);
    }
    
    // Log execution
    error_log("Auto no-show completed: {$markedCount} appointments marked");
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => "Marked {$markedCount} appointments as no-show"
    ]);
    
} catch (Exception $e) {
    error_log("Auto no-show error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
