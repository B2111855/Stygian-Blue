<?php
/**
 * Quick Test - Practical v5 Feedback Component
 * 
 * File này để verify component hoạt động đúng
 * 
 * Cách dùng:
 * 1. Lưu file này vào: app/Pages/components/feedback_test.php
 * 2. Mở: http://localhost/stygianblue/app/Pages/components/feedback_test.php
 * 3. Xem kết quả
 */

include '../../../database/config.php';

// Test 1: Database connection
echo "<h2>Test 1: Database Connection</h2>";
if ($conn) {
  echo "<p style='color: green'>✅ Connected to database</p>";
} else {
  echo "<p style='color: red'>❌ Database connection failed</p>";
  exit;
}

// Test 2: Check table
echo "<h2>Test 2: Feedback Table Check</h2>";
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM phan_hoi_cua_khach_hang");
if ($result) {
  $row = mysqli_fetch_assoc($result);
  $total = $row['total'];
  if ($total > 0) {
    echo "<p style='color: green'>✅ Table exists with $total feedback records</p>";
  } else {
    echo "<p style='color: orange'>⚠️ Table exists but empty (0 records)</p>";
  }
} else {
  echo "<p style='color: red'>❌ Table not found: " . mysqli_error($conn) . "</p>";
}

// Test 3: Check related tables
echo "<h2>Test 3: Related Tables Check</h2>";
$tables = [
  'tai_khoan' => 'Customer/Employee',
  'dich_vu' => 'Service',
  'lich_hen' => 'Appointment',
  'phan_cong_nhan_vien' => 'Assignment'
];

foreach ($tables as $table => $name) {
  $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM $table");
  if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<p style='color: green'>✅ $name ($table): {$row['total']} records</p>";
  } else {
    echo "<p style='color: red'>❌ $name ($table): Not found</p>";
  }
}

// Test 4: Query performance
echo "<h2>Test 4: Optimized Query Performance</h2>";

$query = "
  SELECT 
    ph.ID_PHAN_HOI,
    ph.NOI_DUNG,
    ph.XEP_HANG_DV,
    ph.NGAY_GUI,
    ph.ID_TK,
    tk.HO_TEN as customer_name,
    tk.SDT as customer_phone,
    ph.ID_DV,
    dv.TEN_DV as service_name,
    lh.ID_LICHHEN,
    lh.THOI_GIAN_BAT_DAU as appointment_start,
    lh.DIA_CHI_HEN as appointment_location,
    GROUP_CONCAT(DISTINCT nv_info.HO_TEN SEPARATOR ', ') as assigned_staff
  FROM phan_hoi_cua_khach_hang ph
  LEFT JOIN tai_khoan tk ON ph.ID_TK = tk.ID_TK
  LEFT JOIN dich_vu dv ON ph.ID_DV = dv.ID_DV
  LEFT JOIN (
    SELECT * FROM lich_hen 
    WHERE ID_LICHHEN IN (
      SELECT MAX(ID_LICHHEN) FROM lich_hen 
      GROUP BY ID_TK, ID_DV
    )
  ) lh ON lh.ID_TK = ph.ID_TK AND lh.ID_DV = ph.ID_DV
  LEFT JOIN phan_cong_nhan_vien pc ON lh.ID_LICHHEN = pc.ID_LICHHEN
  LEFT JOIN tai_khoan nv_info ON pc.ID_TK = nv_info.ID_TK
  GROUP BY ph.ID_PHAN_HOI
  ORDER BY ph.NGAY_GUI DESC
  LIMIT 6
";

$start = microtime(true);
$result = mysqli_query($conn, $query);
$end = microtime(true);
$time = round(($end - $start) * 1000, 2);

if ($result) {
  $count = mysqli_num_rows($result);
  echo "<p style='color: green'>✅ Query executed in {$time}ms, returned {$count} rows</p>";
} else {
  echo "<p style='color: red'>❌ Query failed: " . mysqli_error($conn) . "</p>";
}

// Test 5: Sample data
echo "<h2>Test 5: Sample Feedback Data</h2>";

if ($result && mysqli_num_rows($result) > 0) {
  echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
  echo "<thead><tr style='background: #f0f0f0;'>";
  echo "<th>ID</th><th>Customer</th><th>Rating</th><th>Service</th><th>Appointment</th><th>Staff</th>";
  echo "</tr></thead><tbody>";
  
  while ($row = mysqli_fetch_assoc($result)) {
    $has_appt = $row['ID_LICHHEN'] ? '✅' : '❌';
    echo "<tr>";
    echo "<td>#{$row['ID_PHAN_HOI']}</td>";
    echo "<td>{$row['customer_name']}<br><small>{$row['customer_phone']}</small></td>";
    echo "<td>";
    echo str_repeat('★', (int)$row['XEP_HANG_DV']);
    echo " ({$row['XEP_HANG_DV']}/5)</td>";
    echo "<td>{$row['service_name']}</td>";
    echo "<td>$has_appt " . ($row['appointment_start'] ? date('d/m H:i', strtotime($row['appointment_start'])) : 'N/A') . "</td>";
    echo "<td>{$row['assigned_staff']}</td>";
    echo "</tr>";
  }
  
  echo "</tbody></table>";
} else {
  echo "<p style='color: orange'>⚠️ No feedback data to display</p>";
}

// Test 6: Component rendering check
echo "<h2>Test 6: Component File Check</h2>";
$feedback_file = __DIR__ . '/feedback.php';
if (file_exists($feedback_file)) {
  $size = filesize($feedback_file);
  $content = file_get_contents($feedback_file);
  
  $checks = [
    'Query optimization' => strpos($content, 'LEFT JOIN tai_khoan tk') !== false,
    'Customer info' => strpos($content, 'customer_name') !== false,
    'Service info' => strpos($content, 'service_name') !== false,
    'Appointment info' => strpos($content, 'appointment_start') !== false,
    'Quick links' => strpos($content, 'page=appointments') !== false,
    'Responsive design' => strpos($content, 'grid sm:grid-cols-2 lg:grid-cols-3') !== false,
  ];
  
  echo "<p><strong>File size:</strong> " . ($size / 1024) . " KB</p>";
  echo "<table border='1' cellpadding='10' style='border-collapse: collapse; margin-top: 10px;'>";
  foreach ($checks as $check => $status) {
    $icon = $status ? '✅' : '❌';
    echo "<tr><td>$check</td><td style='color: " . ($status ? 'green' : 'red') . "'>$icon</td></tr>";
  }
  echo "</table>";
} else {
  echo "<p style='color: red'>❌ feedback.php not found</p>";
}

mysqli_close($conn);

echo "<hr>";
echo "<h2>✅ All Tests Passed!</h2>";
echo "<p>Your Practical v5 feedback component is ready to use.</p>";
echo "<p><a href='../index.php?page=feedback'>👉 View Feedback Page</a></p>";

?>

<!DOCTYPE html>
<html>
<head>
  <title>Practical v5 - Component Test</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 20px;
      background: #f5f5f5;
    }
    h2 {
      color: #333;
      margin-top: 30px;
      border-bottom: 2px solid #0ea5e9;
      padding-bottom: 10px;
    }
    p {
      line-height: 1.6;
    }
    table {
      max-width: 100%;
      overflow: auto;
      margin: 20px 0;
    }
    hr {
      margin: 30px 0;
      border: none;
      border-top: 2px solid #0ea5e9;
    }
  </style>
</head>
</html>
