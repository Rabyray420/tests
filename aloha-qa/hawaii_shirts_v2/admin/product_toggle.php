<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/products.php');
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$stmt = get_pdo()->prepare('UPDATE products SET active = NOT active WHERE id = ?');
$stmt->execute([$id]);

redirect('/admin/products.php');
