<?php
include $_SERVER['DOCUMENT_ROOT'] . '/stygianblue/database/config.php';

$sql = "SELECT ID_TB, TEN_TB FROM TRANG_THIET_BI";
$result = $conn->query($sql);

$devices = [];
while ($row = $result->fetch_assoc()) {
    $devices[] = $row;
}

header('Content-Type: application/json');
echo json_encode($devices);
?>

