# VNPay Auto-Payment Flow

This document captures how invoices are now reconciled automatically through VNPay for both the customer surface (`app/Pages/components/thanh_toan.php`) and the admin back office.

## End-to-end timeline

1. **Invoice creation** – bookings still produce a `hoa_don` record.
2. **Customer selects VNPay** – `vnpay_create_payment.php` logs a `thanh_toan_truc_tuyen` row with `TRANG_THAI=pending`.
3. **VNPay return + IPN** – both `vnpay_return.php` and `vnpay_ipn.php` verify signatures, write the callback, and immediately mark `hoa_don.TRANGTHAI_THANHTOAN='Đã thanh toán'` on success.
4. **UI updates**
   - Customer invoice list: `Chờ xác minh` has been replaced by *VNPay đang xử lý* badges that read the latest transaction (`tt.TRANG_THAI`).
   - Schedule view: modal + CTAs are blocked while VNPay is pending to avoid duplicate manual uploads.
   - Admin payments dashboard: pending filters and warning rows now pivot on `tt.TRANG_THAI='pending'`.
5. **Admin monitoring** – overview widgets and invoice detail pages surface VNPay reference, last callback timestamp, and disable manual confirmation while an IPN is awaited.

## Customer touchpoints

- **Filters/statistics** now use the transaction state (`success`, `pending`, `failed`). Manual verification requests are no longer exposed.
- **Timeline cards** show "VNPay đang xử lý" whenever the latest transaction is pending; CTA blocks render inline instructions depending on whether VNPay succeeded or failed.
- **Schedule modal** highlights VNPay status and blocks manual upload if a gateway transaction is already pending.

## Admin tooling

- **`admin_confirm_payments.php`** highlights rows with pending VNPay callbacks, adds reference/time metadata, and updates the dropdown option to "VNPay đang xử lý".
- **`hoa_don_chi_tiet.php`** includes a dedicated VNPay card plus safeguards against manual confirmation when an automatic callback is expected.
- **`admin_overview.php`** replaces the pending counter and task copy so that alerts mention VNPay IPNs instead of manual confirmation requests.

## Operational notes

- Manual confirmation is still possible for offline transfers, but buttons are disabled whenever VNPay reports `pending` to avoid double-booking.
- Failed transactions remain visible with explicit badges so staff can ask the customer to retry.
- To reconcile issues, filter Admin → Hóa đơn with "VNPay đang xử lý" and compare the reference in `thanh_toan_truc_tuyen`.
