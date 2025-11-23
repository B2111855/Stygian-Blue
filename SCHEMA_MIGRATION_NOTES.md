# Schema Migration Notes (Updated) - stygianblue_dbv4_moi.sql

## 1. Thay đổi cột / bảng chính

### Bảng `trang_phuc` (hiện hành)
- `ID_TP` cũ → `ID_TRANG_PHUC` (khóa chính)
- `TEN_TP` → `TEN`
- `MAU` → `MAU_SAC`
- `TINH_TRANG` → `TRANG_THAI` (giá trị mới: `available`, `maintenance`, `rented`, `retired`)
- Bảng lịch sử giá vẫn dùng: `don_gia_trang_phuc` (ghi nhận thay đổi đơn giá); GIA_THUE vẫn là đơn giá hiện tại snapshot.
- Các trường legacy đã loại bỏ: `NGAY_GIAT_CUOI`, `IS_ACTIVE` (trạng thái hoạt động được suy từ `TRANG_THAI`).

### Bảng `trang_phuc_loai`
Giữ lại để phân loại (cột `TRANG_THAI` dùng giá trị `active` / khác). Không xóa.

### Bảng `chi_nhanh`
Giữ nguyên tên lowercase: `chi_nhanh`.

### Bảng legacy `trang_phuc_legacy`
Tạm thời vẫn tồn tại để tham chiếu dữ liệu cũ. Không nên dùng trong chức năng mới.

## 2. Khóa ngoại hình ảnh BỊ SAI & Cách sửa
Trong file dump hiện tại: FK bảng `trang_phuc_hinh_anh` trỏ tới `trang_phuc_legacy` (`ID_TP`). Điều này làm INSERT ảnh mới thất bại (vi phạm FK) khi chúng ta chỉ thêm vào bảng `trang_phuc`.

### Cần chạy migration sửa FK:
```sql
ALTER TABLE `trang_phuc_hinh_anh` DROP FOREIGN KEY `fk_tpha_tp`;
ALTER TABLE `trang_phuc_hinh_anh`
  ADD CONSTRAINT `fk_tpha_tp` FOREIGN KEY (`ID_TP`) REFERENCES `trang_phuc`(`ID_TRANG_PHUC`) ON DELETE CASCADE ON UPDATE CASCADE;
```
File đã thêm: `database/migrations/20251124_fix_image_fk.sql`.

Sau khi sửa, kiểm tra:
```sql
SELECT ha.ID_HA
FROM trang_phuc_hinh_anh ha
LEFT JOIN trang_phuc tp ON tp.ID_TRANG_PHUC = ha.ID_TP
WHERE tp.ID_TRANG_PHUC IS NULL;
```
Nếu kết quả rỗng => FK hợp lệ.

## 3. Các truy vấn tiêu chuẩn hiện dùng
```sql
SELECT 
  tp.ID_TRANG_PHUC AS ID_TP,
  tp.TEN AS TEN_TP,
  tp.SIZE,
  tp.MAU_SAC AS MAU,
  tp.TRANG_THAI AS TINH_TRANG,
  tp.GHI_CHU,
  tp.ID_CN,
  cn.TEN_CN,
  COALESCE(price.DON_GIA, tp.GIA_THUE) AS DON_GIA
FROM trang_phuc tp
JOIN chi_nhanh cn ON cn.ID_CN = tp.ID_CN
LEFT JOIN (
  SELECT d1.ID_TP, d1.DON_GIA, d1.NGAY_GIO
  FROM don_gia_trang_phuc d1
  INNER JOIN (
    SELECT ID_TP, MAX(NGAY_GIO) AS NGAY_GIO
    FROM don_gia_trang_phuc
    GROUP BY ID_TP
  ) latest ON latest.ID_TP = d1.ID_TP AND latest.NGAY_GIO = d1.NGAY_GIO
) price ON price.ID_TP = tp.ID_TRANG_PHUC
WHERE tp.ID_TRANG_PHUC = ?;
```

## 4. Ghi chú chuyển đổi tiếp theo
- Dọn dần `trang_phuc_legacy` sau khi chắc chắn không còn FK tham chiếu.
- Chuẩn hóa giá trị trạng thái (`retired` thêm vào để thay thế logic IS_ACTIVE).
- Thêm migration chuyển toàn bộ FK khác (nếu còn) khỏi bảng legacy.

## 5. Cần rà soát thêm
- `trang_phuc_datthue.php`, `process_costume_booking.php` vẫn dùng tên cột cũ? (Chưa hoàn tất.)
- Đảm bảo mọi SELECT ảnh dùng điều kiện `IS_ACTIVE = 1` và thứ tự `IS_COVER DESC, THU_TU ASC`.
