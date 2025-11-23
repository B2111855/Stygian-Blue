# Rollback / Tắt tính năng gói + trang phục

Nếu cần tạm thời vô hiệu hóa toàn bộ logic liên quan tới package + trang phục:

## 1. Biến môi trường
Đặt trong `.env` hoặc cấu hình server:
```
ENABLE_PACKAGE_COSTUME=0
```
Khi =0:
- `quote_preview.php` không chạy nhánh package (trả về logic dịch vụ cũ).
- Form vẫn có thể hiển thị select gói nhưng báo giá sẽ không tính trang phục. (Có thể ẩn select gói tuỳ chỉnh thêm JS nếu muốn.)

## 2. Ẩn UI nhanh
Thêm snippet JS (tạm) sau khi load trang đặt lịch:
```js
if (typeof ENABLE_PACKAGE_COSTUME !== 'undefined' && ENABLE_PACKAGE_COSTUME === '0') {
  const pkgWrap = document.querySelector('[data-package-field]');
  const pkgCostume = document.querySelector('[data-package-costume-field]');
  if (pkgWrap) pkgWrap.classList.add('hidden');
  if (pkgCostume) pkgCostume.classList.add('hidden');
}
```
Hoặc xoá các option gói khỏi select bằng PHP nếu biến flag =0.

## 3. Bảng dữ liệu
- Các bảng mới (`TRANG_PHUC`, `GOI_TRANG_PHUC`, `BOOKING_ITEM`) là additive nên không cần rollback cấp tốc.
- Không xoá dữ liệu line item để tránh mất thông tin báo cáo.

## 4. Kiểm tra sau khi tắt
- Đặt thử booking gói (nếu UI vẫn mở) sẽ tạo lịch hẹn nhưng **không** ghi line items (do flag chặn nhánh package).
- `BOOKING_ITEM` cũ vẫn còn nhưng không ảnh hưởng booking mới.

## 5. Khôi phục
Đặt lại:
```
ENABLE_PACKAGE_COSTUME=1
```
Clear OPcache (nếu dùng) hoặc reload PHP-FPM để biến môi trường có hiệu lực.

## 6. Tùy chọn nâng cao
Muốn disable insert line items nhưng vẫn báo giá: chỉnh điều kiện trong `process_schedule.php` phần:
```php
if ($bookingType === 'package' && $enablePackageCostume !== '0' && $packageId) {
    // ... insert line items ...
}
```
Chỉ cần đổi `$enablePackageCostume !== '0'` thành `false`.

## 7. An toàn dữ liệu
Không xóa bảng trừ khi đã backup:
```sql
-- Backup nhanh
CREATE TABLE BOOKING_ITEM_BACKUP AS SELECT * FROM BOOKING_ITEM;
```
Rồi mới cân nhắc thao tác destructive.

## 8. Giám sát
Khi bật lại, nên kiểm thử:
1. Chọn gói -> tải trang phục -> tick optional -> báo giá tổng đúng.
2. Submit -> kiểm tra `BOOKING_ITEM` có dòng package + mandatory + optional.
3. Tắt flag -> lặp lại booking -> không có dòng package/costume mới.

---
Tài liệu này giúp rollback khẩn cấp tránh phải revert code hoặc xóa dữ liệu.
