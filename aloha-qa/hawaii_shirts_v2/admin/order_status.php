<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/orders.php');
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

if (array_key_exists($status, STATUS_LABELS)) {
    $stmt = get_pdo()->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
}

redirect('/admin/orders.php');
