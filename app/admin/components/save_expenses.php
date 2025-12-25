<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include '../../../database/config.php';

function jsonResponse(string $status, string $message, int $code = 200): void
{
  http_response_code($code);
  echo json_encode(['status' => $status, 'message' => $message]);
  exit;
}

function redirectResponse(string $target, array $params): void
{
  $separator = str_contains($target, '?') ? '&' : '?';
  $query = http_build_query($params);
  header("Location: {$target}{$separator}{$query}");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse('error', 'Phương thức không được hỗ trợ.', 405);
}

$branchParam = $_POST['branch'] ?? '';
$monthParam = $_POST['month'] ?? date('Y-m');
$redirectTo = $_POST['redirect_to'] ?? '';
$isStaffContext = isset($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], 'staff_dashboard.php');

function finalizeResponse(bool $success, string $message, string $monthParam, string $redirectTo, bool $isStaffContext): void
{
  $target = '';
  if ($redirectTo && !preg_match('#^https?://#i', $redirectTo)) {
    $target = $redirectTo;
  } elseif ($isStaffContext) {
    $target = '../staff_dashboard.php?page=staff_expense';
  }

  if ($target) {
    $params = ['month' => $monthParam];
    if ($success) {
      $params['success'] = 1;
    } else {
      $params['error'] = 1;
      $params['msg'] = $message;
    }

    redirectResponse($target, $params);
  }

  jsonResponse($success ? 'success' : 'error', $message, $success ? 200 : 400);
}

if (!$branchParam || !preg_match('/^cn(\d+)$/i', $branchParam, $matches)) {
  finalizeResponse(false, 'Chi nhánh không hợp lệ.', $monthParam, $redirectTo, $isStaffContext);
}

if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
  $monthParam = date('Y-m');
}

$branchId = (int)$matches[1];
$monthDate = DateTime::createFromFormat('Y-m', $monthParam) ?: new DateTime('first day of this month');
$firstOfMonth = $monthDate->format('Y-m-01 00:00:00');

$names = $_POST['expense_name'] ?? [];
$amounts = $_POST['expense_amount'] ?? [];
$notes = $_POST['expense_note'] ?? [];
$submissionNote = trim($_POST['submission_note'] ?? '');

$expenses = [];
foreach ($names as $index => $name) {
  $name = trim((string)$name);
  $amount = isset($amounts[$index]) ? (float)$amounts[$index] : 0;
  $note = isset($notes[$index]) ? trim((string)$notes[$index]) : '';

  if ($name === '' || $amount <= 0) {
    continue;
  }

  $expenses[] = [
    'name' => $name,
    'amount' => $amount,
    'note' => $note,
  ];
}

if (empty($expenses)) {
  finalizeResponse(false, 'Vui lòng nhập ít nhất một khoản chi hợp lệ.', $monthParam, $redirectTo, $isStaffContext);
}

$taxMode = $_POST['tax_mode'] ?? 'auto';
$taxValue = max(0, (float)($_POST['tax_value'] ?? 0));

if ($taxMode === 'auto') {
  $revenueStmt = $conn->prepare("SELECT SUM(SO_TIEN) AS total FROM tai_chinh WHERE ID_CN = ? AND LOAI_GIAO_DICH = 'doanh thu' AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = ?");
  $revenueStmt->bind_param('is', $branchId, $monthParam);
  $revenueStmt->execute();
  $revenueRow = $revenueStmt->get_result()->fetch_assoc();
  $revenue = (float)($revenueRow['total'] ?? 0);
  $taxValue = round($revenue * 0.1);
}

$conn->begin_transaction();

try {
  $deleteExpenseStmt = $conn->prepare("DELETE FROM chi_phi_phat_sinh WHERE ID_CN = ? AND DATE_FORMAT(NGAY_GIO, '%Y-%m') = ?");
  $deleteExpenseStmt->bind_param('is', $branchId, $monthParam);
  $deleteExpenseStmt->execute();

  $deleteFinanceStmt = $conn->prepare("DELETE FROM tai_chinh WHERE ID_CN = ? AND DATE_FORMAT(NGAY_GIAO_DICH, '%Y-%m') = ? AND LOAI_GIAO_DICH IN ('chi phí', 'thuế')");
  $deleteFinanceStmt->bind_param('is', $branchId, $monthParam);
  $deleteFinanceStmt->execute();

  $expenseInsertStmt = $conn->prepare("INSERT INTO chi_phi_phat_sinh (TEN_CP, MOTA_CP, GIA_TRI, NGAY_GIO, ID_CN) VALUES (?, ?, ?, ?, ?)");
  $financeInsertStmt = $conn->prepare("INSERT INTO tai_chinh (LOAI_GIAO_DICH, SO_TIEN, LOAI_CHI_TIET, NGAY_GIAO_DICH, ID_CN) VALUES (?, ?, ?, ?, ?)");

  foreach ($expenses as $expense) {
    $description = $expense['note'] ?: "Chi phí {$expense['name']} tháng " . $monthDate->format('m/Y');
    if ($submissionNote) {
      $description .= ' - ' . $submissionNote;
    }

    $expenseName = $expense['name'];
    $expenseAmount = $expense['amount'];
    $expenseDate = $firstOfMonth;

    $expenseInsertStmt->bind_param('ssdsi', $expenseName, $description, $expenseAmount, $expenseDate, $branchId);
    $expenseInsertStmt->execute();

    $financeType = 'chi phí';
    $financeAmount = $expense['amount'];
    $financeLoaiChiTiet = 'Chi phí phát sinh';
    $financeDate = $firstOfMonth;

    $financeInsertStmt->bind_param('sdssi', $financeType, $financeAmount, $financeLoaiChiTiet, $financeDate, $branchId);
    $financeInsertStmt->execute();
  }

  if ($taxValue > 0) {
    $taxName = 'Thuế VAT';
    $taxDescription = "Thuế 10% doanh thu tháng " . $monthDate->format('m/Y');
    if ($taxMode === 'manual' && $submissionNote) {
      $taxDescription .= ' - ' . $submissionNote;
    }

    $taxAmount = $taxValue;
    $taxDate = $firstOfMonth;

    $expenseInsertStmt->bind_param('ssdsi', $taxName, $taxDescription, $taxAmount, $taxDate, $branchId);
    $expenseInsertStmt->execute();

    $financeType = 'chi phí';
    $financeAmount = $taxValue;
    $financeLoaiChiTiet = 'Thuế VAT';
    $financeDate = $firstOfMonth;

    $financeInsertStmt->bind_param('sdssi', $financeType, $financeAmount, $financeLoaiChiTiet, $financeDate, $branchId);
    $financeInsertStmt->execute();
  }

  $conn->commit();
} catch (Throwable $throwable) {
  $conn->rollback();
  finalizeResponse(false, 'Không thể lưu chi phí. Vui lòng thử lại.', $monthParam, $redirectTo, $isStaffContext);
}

finalizeResponse(true, 'Lưu chi phí thành công.', $monthParam, $redirectTo, $isStaffContext);
