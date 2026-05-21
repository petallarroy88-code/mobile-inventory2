<?php
// modules/orders/create.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$supplierId   = (int)($_POST['supplier_id'] ?? 0) ?: null;
$expectedDate = !empty($_POST['expected_date']) ? $_POST['expected_date'] : null;
$notes        = trim($_POST['notes'] ?? '');
$productIds   = $_POST['product_id'] ?? [];
$quantities   = $_POST['quantity'] ?? [];
$unitCosts    = $_POST['unit_cost'] ?? [];
$userId       = currentUser()['id'];
$poNumber     = generatePONumber();

$totalAmount  = 0.0;
$items        = [];
foreach ($productIds as $i => $pid) {
    $pid  = (int)$pid;
    $qty  = (int)($quantities[$i] ?? 0);
    $cost = (float)($unitCosts[$i] ?? 0);
    if ($pid > 0 && $qty > 0) {
        $items[]      = [$pid, $qty, $cost];
        $totalAmount += $qty * $cost;
    }
}

if (empty($items)) {
    flashMessage('danger', 'Add at least one product.');
    header('Location: index.php'); exit;
}

$status    = 'ordered';
$orderDate = date('Y-m-d');
$stmt = $conn->prepare("INSERT INTO purchase_orders (po_number,supplier_id,status,order_date,expected_date,total_amount,notes,user_id) VALUES (?,?,?,?,?,?,?,?)");
$stmt->bind_param('sisssdsi', $poNumber, $supplierId, $status, $orderDate, $expectedDate, $totalAmount, $notes, $userId);
// Fix: use correct type string
$stmt = $conn->prepare("INSERT INTO purchase_orders (po_number,supplier_id,status,order_date,expected_date,total_amount,notes,user_id) VALUES (?,?,?,?,?,?,?,?)");
$stmt->bind_param('sisssdsi', $poNumber, $supplierId, $status, $orderDate, $expectedDate, $totalAmount, $notes, $userId);
$stmt->execute();
$poId = $conn->insert_id;

foreach ($items as [$pid, $qty, $cost]) {
    $iStmt = $conn->prepare("INSERT INTO purchase_order_items (po_id,product_id,quantity,unit_cost) VALUES (?,?,?,?)");
    $iStmt->bind_param('iiid', $poId, $pid, $qty, $cost);
    $iStmt->execute();
}

flashMessage('success', "Purchase order $poNumber created successfully.");
header('Location: index.php');
exit;
