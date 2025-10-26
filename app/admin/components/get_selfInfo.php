<?php
// get_selfInfo.php
// Mục tiêu: Lấy toàn bộ thông tin hồ sơ người dùng hiện tại (thông tin cá nhân + vai trò hệ thống + chi nhánh)
// Dùng chung cho header, sidebar, trang hồ sơ cá nhân,...

// GỢI Ý: file này nên được include ở nơi đã có session_start() và $conn.
// Ví dụ: include '../../database/config.php'; đã chạy ở file gọi nó.
// => Ở đây KHÔNG tự session_start() để tránh double-start.

function getUserInfo(mysqli $conn, $idTk = null) {
    // 1. Xác định ID_TK
    if ($idTk === null) {
        if (!isset($_SESSION['ID_TK'])) {
            // chưa đăng nhập hoặc session hết hạn
            return null;
        }
        $idTk = $_SESSION['ID_TK'];
    }

    // 2. Chuẩn bị truy vấn
    // Giải thích:
    // - tai_khoan tk: thông tin cơ bản
    // - nhan_vien nv: thông tin nhân sự (loại nhân viên, chuyên môn, chi nhánh)
    // - chi_nhanh cn: tên chi nhánh
    // - quyen_truy_cap qtc: tên quyền/role
    //
    // LEFT JOIN để tài khoản KHÔNG phải nhân viên (khách hàng) vẫn trả dữ liệu.
    $sql = "
        SELECT 
            tk.ID_TK,
            tk.HO_TEN,
            tk.NGAY_SINH,
            tk.DIA_CHI,
            tk.EMAIL,
            tk.SDT,

            tk.ID_QUYEN,
            qtc.TEN_QUYEN,

            nv.LOAI_NV,
            nv.CHUYEN_MON,
            nv.ID_CN,
            cn.TEN_CN

        FROM tai_khoan tk
        LEFT JOIN nhan_vien nv 
               ON tk.ID_TK = nv.ID_TK
        LEFT JOIN chi_nhanh cn 
               ON nv.ID_CN = cn.ID_CN
        LEFT JOIN quyen_truy_cap qtc 
               ON tk.ID_QUYEN = qtc.ID_QUYEN
        WHERE tk.ID_TK = ?
        LIMIT 1
    ";

    // 3. Thực thi truy vấn an toàn
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Nếu lỗi prepare (ví dụ sai tên cột), ta trả về null để tránh crash toàn trang
        return null;
    }

    // ID_TK là varchar(20) -> bind kiểu "s"
    $stmt->bind_param("s", $idTk);
    $stmt->execute();
    $result = $stmt->get_result();

    // 4. Lấy dữ liệu
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Chuẩn hoá output một chút cho UI:
        // - Gộp thông tin vị trí/chức vụ
        // - Đảm bảo luôn có key, tránh undefined index khi echo
        $userData = [
            // Thông tin định danh
            'ID_TK'         => $row['ID_TK']          ?? null,
            'HO_TEN'        => $row['HO_TEN']         ?? null,
            'NGAY_SINH'     => $row['NGAY_SINH']      ?? null,
            'DIA_CHI'       => $row['DIA_CHI']        ?? null,
            'EMAIL'         => $row['EMAIL']          ?? null,
            'SDT'           => $row['SDT']            ?? null,

            // Quyền truy cập
            'ID_QUYEN'      => $row['ID_QUYEN']       ?? null,
            'TEN_QUYEN'     => $row['TEN_QUYEN']      ?? null, // ví dụ: 'Admin', 'Nhân viên', 'Khách hàng'

            // Thông tin nhân sự
            'LOAI_NV'       => $row['LOAI_NV']        ?? null, // ví dụ: 'chuyen_trach' | 'quan_ly'
            'CHUYEN_MON'    => $row['CHUYEN_MON']     ?? null, // ví dụ: 'Nhiếp ảnh gia', 'Quản lý chi nhánh'
            'ID_CN'         => $row['ID_CN']          ?? null,

            // Chi nhánh
            'TEN_CN'        => $row['TEN_CN']         ?? null,
        ];

        $stmt->close();
        return $userData;
    }

    $stmt->close();
    return null;
}
