<?php
/**
 * Invoices API
 * RESTful API endpoints for invoice management
 * 
 * Endpoints:
 * GET    /api/invoices              - List invoices with filters & pagination
 * GET    /api/invoices/{id}         - Get invoice details
 * POST   /api/invoices/{id}/confirm - Confirm payment manually
 * POST   /api/invoices/{id}/refund  - Process refund request
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    include '../../database/config.php';
    date_default_timezone_set('Asia/Ho_Chi_Minh');

    // Parse request
    $request_method = $_SERVER['REQUEST_METHOD'];
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path_parts = array_filter(explode('/', trim($request_uri, '/')));
    
    // Extract endpoint parts
    $invoice_id = isset($path_parts[3]) && is_numeric($path_parts[3]) ? (int)$path_parts[3] : null;
    $action = isset($path_parts[4]) ? $path_parts[4] : null;

    // Permission check function
    function checkAdminPermission() {
        $user_role = isset($_SESSION['ROLE']) ? $_SESSION['ROLE'] : null;
        
        if ($user_role !== 'admin' && $user_role !== 'manager') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập']);
            exit;
        }
    }

    // ===== Helper function to calculate total price =====
    function calculateTotalPrice($idLichHen, $conn) {
        $totalDV = 0;
        $totalTB = 0;
        $travelFee = 0;

        // Ưu tiên tính theo BOOKING_ITEM (đa dịch vụ)
        $stmtBI = mysqli_prepare($conn, "SELECT DON_GIA, SO_LUONG FROM BOOKING_ITEM WHERE ID_LICHHEN = ? AND ITEM_TYPE = 'service'");
        if ($stmtBI) {
            mysqli_stmt_bind_param($stmtBI, 'i', $idLichHen);
            if (mysqli_stmt_execute($stmtBI)) {
                $resBI = mysqli_stmt_get_result($stmtBI);
                while ($resBI && $bi = mysqli_fetch_assoc($resBI)) {
                    $totalDV += ((float)$bi['DON_GIA']) * ((int)$bi['SO_LUONG']);
                }
            }
            mysqli_stmt_close($stmtBI);
        }

        // Nếu không có BOOKING_ITEM dịch vụ -> fallback theo dịch vụ chính
        if ($totalDV <= 0) {
            $queryDV = "SELECT dv.thoi_gian, dg.DON_GIA 
                        FROM lich_hen lh 
                        JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV 
                        JOIN (
                            SELECT ID_DV, DON_GIA 
                            FROM don_gia_dich_vu dg1 
                            WHERE NGAY_GIO = (
                                SELECT MAX(NGAY_GIO) 
                                FROM don_gia_dich_vu dg2 
                                WHERE dg2.ID_DV = dg1.ID_DV
                            )
                        ) dg ON dv.ID_DV = dg.ID_DV 
                        WHERE lh.ID_LICHHEN = ?";
            $stmtDV = mysqli_prepare($conn, $queryDV);
            if ($stmtDV) {
                mysqli_stmt_bind_param($stmtDV, 'i', $idLichHen);
                if (mysqli_stmt_execute($stmtDV)) {
                    $resultDV = mysqli_stmt_get_result($stmtDV);
                    if ($rowDV = mysqli_fetch_assoc($resultDV)) {
                        $soPhut = (int)$rowDV['thoi_gian'];
                        $soGio = $soPhut / 60;
                        $totalDV = $soGio * $rowDV['DON_GIA'];
                    }
                }
                mysqli_stmt_close($stmtDV);
            }
        }

        // Giá thiết bị mới nhất
        $queryTB = "SELECT dgtb.DON_GIA, lhtb.SO_LUONG 
                    FROM lich_hen_thiet_bi lhtb 
                    JOIN trang_thiet_bi tb ON lhtb.ID_TB = tb.ID_TB 
                    JOIN (
                        SELECT ID_TB, DON_GIA 
                        FROM don_gia_trang_thiet_bi dgtb1 
                        WHERE NGAY_GIO = (
                            SELECT MAX(NGAY_GIO) 
                            FROM don_gia_trang_thiet_bi dgtb2 
                            WHERE dgtb2.ID_TB = dgtb1.ID_TB
                        )
                    ) dgtb ON tb.ID_TB = dgtb.ID_TB 
                    WHERE lhtb.ID_LICHHEN = ?";
        $stmtTB = mysqli_prepare($conn, $queryTB);
        
        if ($stmtTB) {
            mysqli_stmt_bind_param($stmtTB, 'i', $idLichHen);
            if (mysqli_stmt_execute($stmtTB)) {
                $resultTB = mysqli_stmt_get_result($stmtTB);
                while ($rowTB = mysqli_fetch_assoc($resultTB)) {
                    $totalTB += $rowTB['DON_GIA'] * $rowTB['SO_LUONG'];
                }
            }
            mysqli_stmt_close($stmtTB);
        }

        // Phụ phí di chuyển nếu có
        $colTF = mysqli_query($conn, "SHOW COLUMNS FROM lich_hen LIKE 'TRAVEL_FEE'");
        if ($colTF && mysqli_num_rows($colTF) > 0) {
            $stmtTF = mysqli_prepare($conn, "SELECT COALESCE(TRAVEL_FEE,0) AS TF FROM lich_hen WHERE ID_LICHHEN = ?");
            if ($stmtTF) {
                mysqli_stmt_bind_param($stmtTF, 'i', $idLichHen);
                mysqli_stmt_execute($stmtTF);
                $resTF = mysqli_stmt_get_result($stmtTF);
                $rowTF = $resTF ? mysqli_fetch_assoc($resTF) : null;
                $travelFee = (float)($rowTF['TF'] ?? 0);
                mysqli_stmt_close($stmtTF);
            }
        }
        if ($colTF) { mysqli_free_result($colTF); }

        return [
            'totalDV' => round($totalDV, 2),
            'totalTB' => round($totalTB, 2),
            'total' => round($totalDV + $totalTB + $travelFee, 2)
        ];
    }

    // ===== GET /api/invoices - List invoices with filters =====
    if ($request_method === 'GET' && !$invoice_id) {
        checkAdminPermission();

        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = ($page - 1) * $limit;

        $scheduleWhere = "WHERE 1";
        $rentalWhere = "WHERE 1";

        // Filters
        if (!empty($_GET['ten_kh'])) {
            $ten_kh = mysqli_real_escape_string($conn, $_GET['ten_kh']);
            $scheduleWhere .= " AND kh.HO_TEN LIKE '%$ten_kh%'";
            $rentalWhere .= " AND kh.HO_TEN LIKE '%$ten_kh%'";
        }
        if (!empty($_GET['ten_dv'])) {
            $ten_dv = mysqli_real_escape_string($conn, $_GET['ten_dv']);
            $scheduleWhere .= " AND dv.TEN_DV LIKE '%$ten_dv%'";
            $rentalWhere .= " AND (ic.item_names LIKE '%$ten_dv%' OR CONCAT('Thuê trang phục') LIKE '%$ten_dv%')";
        }
        if (!empty($_GET['ma_hd'])) {
            $ma_hd = (int)$_GET['ma_hd'];
            $scheduleWhere .= " AND h.ID_HD = $ma_hd";
            $rentalWhere .= " AND h.ID_HD = $ma_hd";
        }
        if (!empty($_GET['trangthai'])) {
            $trangthai = mysqli_real_escape_string($conn, $_GET['trangthai']);
            if ($trangthai === 'pending_confirm') {
                $pendingCondition = " AND h.TRANGTHAI_THANHTOAN <> 'Đã thanh toán' AND tt.VNPAY_TRANG_THAI = 'pending'";
                $scheduleWhere .= $pendingCondition;
                $rentalWhere .= $pendingCondition;
            } else {
                $scheduleWhere .= " AND h.TRANGTHAI_THANHTOAN = '$trangthai'";
                $rentalWhere .= " AND h.TRANGTHAI_THANHTOAN = '$trangthai'";
            }
        }
        if (!empty($_GET['date_from'])) {
            $date_from = mysqli_real_escape_string($conn, $_GET['date_from']);
            $scheduleWhere .= " AND DATE(h.NGAY_GIO) >= '$date_from'";
            $rentalWhere .= " AND DATE(h.NGAY_GIO) >= '$date_from'";
        }
        if (!empty($_GET['date_to'])) {
            $date_to = mysqli_real_escape_string($conn, $_GET['date_to']);
            $scheduleWhere .= " AND DATE(h.NGAY_GIO) <= '$date_to'";
            $rentalWhere .= " AND DATE(h.NGAY_GIO) <= '$date_to'";
        }

        $latestVnpayJoin = "
          LEFT JOIN (
              SELECT t1.ID_HD,
                     t1.TRANG_THAI AS VNPAY_TRANG_THAI,
                     t1.MA_THAM_CHIEU AS VNPAY_MA_THAM_CHIEU,
                     t1.CREATED_AT AS VNPAY_UPDATED_AT
              FROM thanh_toan_truc_tuyen t1
              JOIN (
                  SELECT ID_HD, MAX(CREATED_AT) AS latest_created
                  FROM thanh_toan_truc_tuyen
                  WHERE GATEWAY = 'vnpay'
                  GROUP BY ID_HD
              ) latest ON latest.ID_HD = t1.ID_HD AND latest.latest_created = t1.CREATED_AT
              WHERE t1.GATEWAY = 'vnpay'
          ) tt ON tt.ID_HD = h.ID_HD
        ";

        // Get total count
        $countQuery = "
          SELECT COUNT(*) AS total FROM (
            SELECT h.ID_HD
            FROM hoa_don h
            JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
            JOIN tai_khoan kh ON l.ID_TK = kh.ID_TK
            JOIN dich_vu dv ON l.ID_DV = dv.ID_DV
            " . $latestVnpayJoin . "
            $scheduleWhere
            UNION ALL
            SELECT h.ID_HD
            FROM hoa_don h
            JOIN don_thue_trang_phuc ttp ON h.ID_TTP = ttp.ID_TTP
            JOIN tai_khoan kh ON ttp.ID_TK = kh.ID_TK
            LEFT JOIN (
              SELECT ct.ID_TTP, GROUP_CONCAT(tp.TEN ORDER BY tp.TEN SEPARATOR ', ') AS item_names
              FROM don_thue_trang_phuc_ct ct
              LEFT JOIN trang_phuc tp ON ct.ID_TP = tp.ID_TRANG_PHUC
              GROUP BY ct.ID_TTP
            ) ic ON ic.ID_TTP = ttp.ID_TTP
            " . $latestVnpayJoin . "
            $rentalWhere
          ) merged
        ";

        $countResult = mysqli_query($conn, $countQuery);
        $totalRows = $countResult ? mysqli_fetch_assoc($countResult)['total'] : 0;
        $totalPages = ceil($totalRows / $limit);

        // Get data
        $query = "
            SELECT * FROM (
                SELECT 
                  h.ID_HD, h.NGAY_GIO, h.TONG_TIEN, h.TRANGTHAI_THANHTOAN,
                  h.PHUONGTHUC_THANHTOAN,
                  tt.VNPAY_TRANG_THAI, tt.VNPAY_MA_THAM_CHIEU, tt.VNPAY_UPDATED_AT,
                  kh.HO_TEN AS TEN_KH,
                  dv.TEN_DV AS TEN_DV,
                  'schedule' AS KIND,
                  NULL AS RENTAL_SUMMARY
                FROM hoa_don h
                JOIN lich_hen l ON h.ID_LICHHEN = l.ID_LICHHEN
                JOIN tai_khoan kh ON l.ID_TK = kh.ID_TK
                JOIN dich_vu dv ON l.ID_DV = dv.ID_DV
                " . $latestVnpayJoin . "
                $scheduleWhere
                UNION ALL
                SELECT 
                  h.ID_HD, h.NGAY_GIO, h.TONG_TIEN, h.TRANGTHAI_THANHTOAN,
                  h.PHUONGTHUC_THANHTOAN,
                  tt.VNPAY_TRANG_THAI, tt.VNPAY_MA_THAM_CHIEU, tt.VNPAY_UPDATED_AT,
                  kh.HO_TEN AS TEN_KH,
                  CONCAT('Thuê trang phục (', COALESCE(icCnt.item_count,0), ' món)') AS TEN_DV,
                  'rental' AS KIND,
                  icCnt.item_names AS RENTAL_SUMMARY
                FROM hoa_don h
                JOIN don_thue_trang_phuc ttp ON h.ID_TTP = ttp.ID_TTP
                JOIN tai_khoan kh ON ttp.ID_TK = kh.ID_TK
                LEFT JOIN (
                    SELECT ct.ID_TTP, COUNT(*) AS item_count,
                           GROUP_CONCAT(tp.TEN ORDER BY tp.TEN SEPARATOR ', ') AS item_names
                    FROM don_thue_trang_phuc_ct ct
                    LEFT JOIN trang_phuc tp ON ct.ID_TP = tp.ID_TRANG_PHUC
                    GROUP BY ct.ID_TTP
                ) icCnt ON icCnt.ID_TTP = ttp.ID_TTP
                " . $latestVnpayJoin . "
                $rentalWhere
            ) merged
            ORDER BY NGAY_GIO DESC
            LIMIT $limit OFFSET $offset
        ";

        $result = mysqli_query($conn, $query);
        $invoices = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $invoices[] = [
                'id_hd' => (int)$row['ID_HD'],
                'ten_kh' => $row['TEN_KH'],
                'ten_dv' => $row['TEN_DV'],
                'ngay_gio' => $row['NGAY_GIO'],
                'tong_tien' => (float)$row['TONG_TIEN'],
                'trangthai_thanhtoan' => $row['TRANGTHAI_THANHTOAN'],
                'phuongthuc' => $row['PHUONGTHUC_THANHTOAN'] ? strtoupper($row['PHUONGTHUC_THANHTOAN']) : 'Chưa ghi nhận',
                'vnpay_trang_thai' => $row['VNPAY_TRANG_THAI'],
                'kind' => $row['KIND'],
                'rental_summary' => $row['RENTAL_SUMMARY']
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $invoices,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'total_records' => $totalRows
            ]
        ]);
        exit;
    }

    // ===== GET /api/invoices/{id} - Get invoice details =====
    if ($request_method === 'GET' && $invoice_id && !$action) {
        checkAdminPermission();

        $stmt = mysqli_prepare($conn, "
            SELECT 
                hd.ID_HD, hd.NGAY_GIO, hd.TRANGTHAI_THANHTOAN, hd.PHUONGTHUC_THANHTOAN, hd.TONG_TIEN,
                tt.VNPAY_TRANG_THAI, tt.VNPAY_MA_THAM_CHIEU, tt.VNPAY_UPDATED_AT,
                kh.HO_TEN, kh.EMAIL, kh.SDT,
                lh.ID_LICHHEN, dv.TEN_DV, dv.thoi_gian, dgdv.DON_GIA AS GIA_DV,
                ttp.ID_TTP, ttp.NGAY_NHAN, ttp.NGAY_TRA_DK, ttp.NGAY_TRA_TT, ttp.TRANG_THAI AS TTP_TRANGTHAI,
                ttp.TIEN_COC, ttp.TONG_TIEN_DU_KIEN, ttp.TONG_TIEN_THUC_TE
            FROM hoa_don hd
            LEFT JOIN lich_hen lh ON hd.ID_LICHHEN = lh.ID_LICHHEN
            LEFT JOIN dich_vu dv ON lh.ID_DV = dv.ID_DV
            LEFT JOIN don_gia_dich_vu dgdv ON dv.ID_DV = dgdv.ID_DV
            LEFT JOIN don_thue_trang_phuc ttp ON hd.ID_TTP = ttp.ID_TTP
            LEFT JOIN tai_khoan kh ON COALESCE(lh.ID_TK, ttp.ID_TK) = kh.ID_TK
            LEFT JOIN (
                SELECT t1.ID_HD,
                       t1.TRANG_THAI AS VNPAY_TRANG_THAI,
                       t1.MA_THAM_CHIEU AS VNPAY_MA_THAM_CHIEU,
                       t1.CREATED_AT AS VNPAY_UPDATED_AT
                FROM thanh_toan_truc_tuyen t1
                JOIN (
                    SELECT ID_HD, MAX(CREATED_AT) AS latest_created
                    FROM thanh_toan_truc_tuyen
                    WHERE GATEWAY = 'vnpay'
                    GROUP BY ID_HD
                ) latest ON latest.ID_HD = t1.ID_HD AND latest.latest_created = t1.CREATED_AT
                WHERE t1.GATEWAY = 'vnpay'
            ) tt ON tt.ID_HD = hd.ID_HD
            WHERE hd.ID_HD = ?");

        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi truy vấn cơ sở dữ liệu']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, "i", $invoice_id);
        mysqli_stmt_execute($stmt);
        $invoice = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$invoice) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn']);
            exit;
        }

        $isRental = !empty($invoice['ID_TTP']);
        
        // Get line items
        $lineItems = [];
        $lineStmt = mysqli_prepare($conn, "SELECT MO_TA, SO_LUONG, DON_GIA, THANH_TIEN FROM chi_tiet_hoa_don WHERE ID_HD = ? ORDER BY ID_CTHD ASC");
        if ($lineStmt) {
            mysqli_stmt_bind_param($lineStmt, 'i', $invoice_id);
            if (mysqli_stmt_execute($lineStmt)) {
                $resLines = mysqli_stmt_get_result($lineStmt);
                while ($li = mysqli_fetch_assoc($resLines)) {
                    $lineItems[] = [
                        'mo_ta' => $li['MO_TA'],
                        'so_luong' => (int)$li['SO_LUONG'],
                        'don_gia' => (float)$li['DON_GIA'],
                        'thanh_tien' => (float)$li['THANH_TIEN']
                    ];
                }
            }
            mysqli_stmt_close($lineStmt);
        }

        // Get rental items if applicable
        $rentalItems = [];
        if ($isRental) {
            $riStmt = mysqli_prepare($conn, "
                SELECT ct.SO_LUONG, ct.DON_GIA_AP_DUNG, tp.TEN
                FROM don_thue_trang_phuc_ct ct
                JOIN trang_phuc tp ON ct.ID_TP = tp.ID_TRANG_PHUC
                WHERE ct.ID_TTP = ?");
            if ($riStmt) {
                mysqli_stmt_bind_param($riStmt, 'i', $invoice['ID_TTP']);
                if (mysqli_stmt_execute($riStmt)) {
                    $riRes = mysqli_stmt_get_result($riStmt);
                    while ($r = mysqli_fetch_assoc($riRes)) {
                        $rentalItems[] = [
                            'ten' => $r['TEN'],
                            'so_luong' => (int)$r['SO_LUONG'],
                            'don_gia_ap_dung' => (float)$r['DON_GIA_AP_DUNG'],
                            'thanh_tien' => (int)$r['SO_LUONG'] * (float)$r['DON_GIA_AP_DUNG']
                        ];
                    }
                }
                mysqli_stmt_close($riStmt);
            }
        }

        // Get equipment
        $equipments = [];
        $eqStmt = mysqli_prepare($conn, "
            SELECT tb.TEN_TB, dgtb.DON_GIA, lhtb.SO_LUONG
            FROM lich_hen_thiet_bi lhtb
            JOIN trang_thiet_bi tb ON lhtb.ID_TB = tb.ID_TB
            JOIN (
            SELECT ID_TB, DON_GIA 
            FROM don_gia_trang_thiet_bi dgtb1 
            WHERE NGAY_GIO = (
                SELECT MAX(NGAY_GIO) 
                FROM don_gia_trang_thiet_bi dgtb2 
                WHERE dgtb2.ID_TB = dgtb1.ID_TB
            )
        ) dgtb ON tb.ID_TB = dgtb.ID_TB
            WHERE lhtb.ID_LICHHEN = ?");
        if ($eqStmt) {
            mysqli_stmt_bind_param($eqStmt, "i", $invoice['ID_LICHHEN']);
            if (mysqli_stmt_execute($eqStmt)) {
                $eqRes = mysqli_stmt_get_result($eqStmt);
                while ($eq = mysqli_fetch_assoc($eqRes)) {
                    $equipments[] = [
                        'ten_tb' => $eq['TEN_TB'],
                        'don_gia' => (float)$eq['DON_GIA'],
                        'so_luong' => (int)$eq['SO_LUONG'],
                        'thanh_tien' => (float)$eq['DON_GIA'] * (int)$eq['SO_LUONG']
                    ];
                }
            }
            mysqli_stmt_close($eqStmt);
        }

        // Calculate totals
        $invoiceTotals = $isRental 
            ? ['totalDV' => 0, 'totalTB' => 0, 'total' => (float)$invoice['TONG_TIEN']]
            : calculateTotalPrice($invoice['ID_LICHHEN'], $conn);

        $methodLabel = $invoice['PHUONGTHUC_THANHTOAN'] ? strtoupper($invoice['PHUONGTHUC_THANHTOAN']) : 'Chưa ghi nhận';
        $isPaid = $invoice['TRANGTHAI_THANHTOAN'] === 'Đã thanh toán';
        $gatewayStatus = $invoice['VNPAY_TRANG_THAI'] ?? null;

        echo json_encode([
            'success' => true,
            'data' => [
                'id_hd' => (int)$invoice['ID_HD'],
                'ngay_gio' => $invoice['NGAY_GIO'],
                'trangthai_thanhtoan' => $invoice['TRANGTHAI_THANHTOAN'],
                'phuongthuc_thanhtoan' => $methodLabel,
                'tong_tien' => (float)$invoice['TONG_TIEN'],
                'is_paid' => $isPaid,
                'vnpay_trang_thai' => $gatewayStatus,
                'vnpay_ma_tham_chieu' => $invoice['VNPAY_MA_THAM_CHIEU'],
                'vnpay_updated_at' => $invoice['VNPAY_UPDATED_AT'],
                'khach_hang' => [
                    'ho_ten' => $invoice['HO_TEN'],
                    'email' => $invoice['EMAIL'],
                    'sdt' => $invoice['SDT']
                ],
                'is_rental' => $isRental,
                'schedule_info' => !$isRental ? [
                    'id_lichhen' => (int)$invoice['ID_LICHHEN'],
                    'ten_dv' => $invoice['TEN_DV'],
                    'thoi_luong' => (int)$invoice['thoi_gian'],
                    'gia_dv' => (float)$invoice['GIA_DV']
                ] : null,
                'rental_info' => $isRental ? [
                    'id_ttp' => (int)$invoice['ID_TTP'],
                    'ngay_nhan' => $invoice['NGAY_NHAN'],
                    'ngay_tra_dk' => $invoice['NGAY_TRA_DK'],
                    'ngay_tra_tt' => $invoice['NGAY_TRA_TT'],
                    'trang_thai' => $invoice['TTP_TRANGTHAI'],
                    'tien_coc' => (float)$invoice['TIEN_COC'],
                    'tong_tien_du_kien' => (float)$invoice['TONG_TIEN_DU_KIEN'],
                    'tong_tien_thuc_te' => (float)($invoice['TONG_TIEN_THUC_TE'] ?? 0)
                ] : null,
                'line_items' => $lineItems,
                'rental_items' => $rentalItems,
                'equipments' => $equipments,
                'totals' => $invoiceTotals
            ]
        ]);
        exit;
    }

    // ===== POST /api/invoices/{id}/confirm - Confirm payment =====
    if ($request_method === 'POST' && $invoice_id && $action === 'confirm') {
        checkAdminPermission();

        $stmt = mysqli_prepare($conn, "UPDATE hoa_don SET TRANGTHAI_THANHTOAN = 'Đã thanh toán' WHERE ID_HD = ?");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật hóa đơn']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, 'i', $invoice_id);
        if (!mysqli_stmt_execute($stmt)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Không thể cập nhật trạng thái hóa đơn']);
            mysqli_stmt_close($stmt);
            exit;
        }

        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Hóa đơn không tồn tại hoặc đã được xác nhận']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Đã xác nhận thanh toán thành công',
            'id_hd' => $invoice_id
        ]);
        exit;
    }

    // ===== POST /api/invoices/{id}/refund - Process refund =====
    if ($request_method === 'POST' && $invoice_id && $action === 'refund') {
        checkAdminPermission();

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['refund_type']) || !isset($data['amount'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dữ liệu hoàn tiền không hợp lệ']);
            exit;
        }

        $refund_type = $data['refund_type'];
        $amount = (float)$data['amount'];
        $reason = isset($data['reason']) ? trim($data['reason']) : '';

        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Số tiền hoàn phải lớn hơn 0']);
            exit;
        }

        // Verify invoice exists and is paid
        $verifyStmt = mysqli_prepare($conn, "SELECT ID_HD, TONG_TIEN, PHUONGTHUC_THANHTOAN FROM hoa_don WHERE ID_HD = ?");
        if (!$verifyStmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi truy vấn cơ sở dữ liệu']);
            exit;
        }

        mysqli_stmt_bind_param($verifyStmt, 'i', $invoice_id);
        mysqli_stmt_execute($verifyStmt);
        $invoice = mysqli_fetch_assoc(mysqli_stmt_get_result($verifyStmt));
        mysqli_stmt_close($verifyStmt);

        if (!$invoice) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn']);
            exit;
        }

        if (strtoupper($invoice['PHUONGTHUC_THANHTOAN'] ?? '') !== 'VNPAY') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Chỉ hoàn tiền cho các giao dịch qua VNPay']);
            exit;
        }

        if ($amount > $invoice['TONG_TIEN']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Số tiền hoàn vượt quá tổng tiền hóa đơn']);
            exit;
        }

        // Create refund record
        $insertStmt = mysqli_prepare($conn, "
            INSERT INTO hoan_tien_vnpay (ID_HD, SO_TIEN, KIỂU_HOAN, LY_DO, TRANG_THAI, NGAY_TAO)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");

        if (!$insertStmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi tạo yêu cầu hoàn tiền']);
            exit;
        }

        mysqli_stmt_bind_param($insertStmt, 'idss', $invoice_id, $amount, $refund_type, $reason);
        if (!mysqli_stmt_execute($insertStmt)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Không thể tạo yêu cầu hoàn tiền']);
            mysqli_stmt_close($insertStmt);
            exit;
        }

        $refund_id = mysqli_insert_id($conn);
        mysqli_stmt_close($insertStmt);

        echo json_encode([
            'success' => true,
            'message' => 'Yêu cầu hoàn tiền đã được tạo',
            'id_hd' => $invoice_id,
            'refund_id' => $refund_id,
            'amount' => $amount,
            'status' => 'pending'
        ]);
        exit;
    }

    // Invalid request
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi máy chủ: ' . $e->getMessage()
    ]);
    exit;
}
