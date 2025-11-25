# Hướng dẫn sử dụng các chức năng mới - Quản lý Dịch vụ

## Tổng quan
Đã triển khai thành công **5 chức năng mức cao** cho trang quản lý dịch vụ.

---

## 1. ✅ Bulk Actions - Thao tác hàng loạt

### Tính năng:
- **Checkbox chọn nhiều**: Mỗi dịch vụ có checkbox để chọn
- **Select All**: Checkbox ở header để chọn tất cả
- **Toolbar động**: Hiển thị khi có dịch vụ được chọn
- **3 hành động hàng loạt**:
  - Kích hoạt (active)
  - Chuyển sang Draft
  - Ngừng hoạt động (retire)

### Cách sử dụng:
1. Check vào các dịch vụ muốn thao tác
2. Toolbar sẽ xuất hiện ở trên cùng
3. Chọn hành động mong muốn
4. Xác nhận trong popup
5. Hệ thống xử lý và reload trang

### Technical:
- Backend: POST request với `bulk_action` parameter
- Validation: Kiểm tra quyền retire qua `DeletionPolicyService`
- Logging: Tất cả actions đều được ghi log

---

## 2. ✅ Export Excel/CSV

### Tính năng:
- **Xuất Excel (.xlsx)**: Format đẹp với màu sắc, border
- **Xuất CSV (.csv)**: Format đơn giản cho import vào hệ thống khác
- **Áp dụng filter hiện tại**: Export chỉ những dịch vụ đang hiển thị sau khi lọc
- **Dropdown menu**: Giao diện đẹp với icon

### Cách sử dụng:
1. Áp dụng các filter nếu cần (tìm kiếm, trạng thái, giá, thời gian)
2. Click nút "Xuất dữ liệu"
3. Chọn định dạng Excel hoặc CSV
4. File sẽ tự động download

### Dữ liệu xuất:
- ID, Tên dịch vụ, Mô tả
- Thời gian (phút), Giá (VNĐ)
- Trạng thái, Đường dẫn hình ảnh

### Technical:
- Library: PhpSpreadsheet
- File lưu tạm: `/tmp/exports/`
- Auto cleanup sau download

---

## 3. ✅ Sao chép dịch vụ (Duplicate)

### Tính năng:
- **Clone nhanh**: Tạo bản sao dịch vụ với 1 click
- **Tự động thêm "(Sao chép)"**: Phân biệt với bản gốc
- **Copy hình ảnh**: Tự động copy file ảnh
- **Giữ nguyên metadata**: Category, tags, giá, thời lượng

### Cách sử dụng:
1. Click nút "Sao chép" màu xanh dương ở hàng dịch vụ
2. Xác nhận trong popup
3. Dịch vụ mới được tạo với tên có "(Sao chép)"
4. Có thể vào edit để sửa tên và thông tin khác

### Use case:
- Tạo dịch vụ tương tự nhanh chóng
- Tạo variations của dịch vụ (VD: Massage 60p, 90p, 120p)
- Backup trước khi chỉnh sửa lớn

---

## 4. ✅ Tags & Categories

### Tính năng:
- **Categories (Danh mục)**: Phân loại dịch vụ theo loại hình
  - Spa & Chăm sóc da
  - Tóc & Tạo mẫu
  - Trang điểm
  - Móng
  - Mi & Lông mày
  - Khác
  
- **Tags (Thẻ)**: Đánh dấu đặc điểm dịch vụ
  - Hot, New, Trending
  - Seasonal, Premium, Sale
  - Mỗi tag có màu riêng

### Cách sử dụng:
1. Khi tạo/sửa dịch vụ:
   - Chọn 1 category từ dropdown
   - Check vào các tags phù hợp (có thể chọn nhiều)
2. Tags hiển thị với màu sắc đặc trưng
3. Dễ dàng filter và tìm kiếm sau này

### Database:
- Cần chạy migration: `database/migrations/add_service_categories_tags.sql`
- Bảng mới: `danh_muc_dich_vu`, `the_dich_vu`, `dich_vu_the`
- Foreign key constraints đã được setup

### Technical:
- Many-to-many relationship cho tags
- One-to-many cho categories
- Cascade delete cho relationship tables

---

## 5. ✅ Thống kê cơ bản

### Tính năng:
- **Overview cards**: 4 card tổng quan
  - Số dịch vụ active/draft/retired
  - Tổng số lịch hẹn
  
- **Top 5 dịch vụ phổ biến**: Theo số lượng booking
- **Top 5 dịch vụ doanh thu cao**: Theo tổng doanh thu
- **Cảnh báo dịch vụ ít dùng**: Dịch vụ không có booking trong 30 ngày

### Cách sử dụng:
1. Click nút "Thống kê" màu tím
2. Dashboard thống kê sẽ hiển thị ở đầu trang
3. Xem các chỉ số và ranking
4. Click "× Đóng" để quay lại danh sách

### Metrics:
- **Booking count**: Tổng số lịch hẹn (bao gồm cả đã hoàn thành)
- **Revenue**: Tổng doanh thu từ hóa đơn
- **Last booking**: Lần đặt lịch gần nhất
- **Unused period**: 30 ngày

### Use case:
- Đánh giá hiệu quả dịch vụ
- Quyết định retire/promote dịch vụ
- Tối ưu menu dịch vụ
- Lập kế hoạch marketing

---

# 🟡 Tính năng mức trung bình (Medium Priority)

## 6. ✅ Advanced Image Management

**Xử lý ảnh chuyên nghiệp với auto-resize và thumbnail**

### Tính năng:
- Upload với preview trực tiếp
- Auto-resize về max 1920x1920px
- Tạo thumbnail 300x300px
- Placeholder SVG khi không có ảnh
- Validate định dạng JPG/PNG/WEBP/GIF
- Giới hạn 5MB
- Xóa ảnh cũ khi upload mới

### File helper:
`app/helpers/image_helper.php` với methods: `uploadImage()`, `resizeImage()`, `createThumbnail()`, `deleteImage()`, `getPlaceholder()`

---

## 7. ✅ Flexible Status Transitions

**Chuyển đổi trạng thái linh hoạt với lý do**

### Tính năng:
- Dropdown ngay trong cột trạng thái (table view)
- Modal nhập lý do thay đổi
- Chuyển đổi hai chiều: active ↔ draft ↔ retired
- Logging chi tiết với lý do
- Business rules validation

### Business rules:
- Không thể chuyển Retired → Active trực tiếp
- Bắt buộc nhập lý do
- Validate dependencies (bookings, packages)

---

## 8. ✅ View Mode Toggle

**Chuyển đổi giữa bảng và lưới**

### Tính năng:
- 2 chế độ: Table (bảng) và Grid (cards)
- Grid responsive: 1-4 cột tùy màn hình
- Card design đẹp với ảnh, badges, actions
- Lưu preference trong localStorage

### Responsive:
- Mobile: 1 cột
- Tablet: 2-3 cột  
- Desktop: 4 cột

---

## 9. ✅ Dynamic Sorting

**Sắp xếp động với 8 tùy chọn**

### Options:
- Tên A-Z / Z-A
- Giá thấp → cao / cao → thấp
- Thời lượng ngắn → dài / dài → ngắn
- Ngày mới nhất / cũ nhất

### Technical:
- URL params: `?sort=name&order=asc`
- Backend validation: Whitelist fields
- ServiceRepository: `searchServices($filters, $sortBy, $sortOrder)`

---

## 10. ✅ View Related Packages

**Xem gói dịch vụ chứa service này**

### Tính năng:
- Nút "Gói" (tím) trong actions
- Modal hiển thị tất cả packages
- Chi tiết: giá, số lượng, hiệu lực, ghi chú
- Badge đếm gói active/total
- Warning gói hết hiệu lực

### Repository:
`app/repositories/PackageServiceRepository.php` với methods:
- `getPackagesContainingService($serviceId)`
- `isServiceInActivePackage($serviceId)` 
- `getServicePackageStats($serviceId)`

### Use case:
- Kiểm tra trước khi retire service
- Xem giá trong các gói khác nhau
- Phân tích gói popularity
- Marketing planning

---

## Cài đặt & Migration

### Bước 1: Chạy Database Migration
```sql
-- Chạy file này trong phpMyAdmin hoặc MySQL client
source database/migrations/add_service_categories_tags.sql;
```

### Bước 2: Kiểm tra Dependencies
```bash
# Đảm bảo PhpSpreadsheet đã được cài
composer require phpoffice/phpspreadsheet
```

### Bước 3: Tạo thư mục exports
```bash
mkdir tmp/exports
chmod 777 tmp/exports
```

### Bước 4: Test các chức năng
1. ✓ Bulk actions: Chọn nhiều và kích hoạt
2. ✓ Export: Xuất Excel
3. ✓ Duplicate: Sao chép dịch vụ
4. ✓ Categories: Tạo dịch vụ mới với category
5. ✓ Statistics: Xem dashboard thống kê

---

## File Structure

```
app/
├── admin/components/
│   └── manage_services.php (Updated - Main UI)
├── repositories/
│   ├── ServiceRepository.php (Updated - Category support)
│   └── ServiceCategoryTagRepository.php (New)
├── helpers/
│   ├── export_services.php (New)
│   └── service_statistics.php (New)
database/migrations/
└── add_service_categories_tags.sql (New)
```

---

## Performance Notes

- **Bulk actions**: Xử lý tuần tự, có thể chậm với >50 items
- **Export**: Limit 9999 records, nên add pagination nếu dataset lớn
- **Statistics**: Cache recommended nếu >1000 services
- **AJAX search**: Debounced 350ms

---

## Security

- ✅ Input validation cho tất cả POST requests
- ✅ Prepared statements (SQL injection safe)
- ✅ XSS protection với htmlspecialchars()
- ✅ CSRF protection (nên thêm tokens)
- ✅ File upload validation (mime type check)
- ✅ Permission check cho bulk retire

---

## Future Enhancements (Not implemented yet)

- Import CSV/Excel
- Advanced filtering by category/tags
- Price change history chart
- Booking calendar view
- SEO metadata fields
- Image gallery (multiple images)
- Service templates

---

**Tất cả 5 chức năng mức cao đã được triển khai hoàn chỉnh!** 🎉
