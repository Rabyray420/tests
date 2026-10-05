<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/products.php');
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$pdo = get_pdo();

$stmt = $pdo->prepare('SELECT image_url FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if ($product) {
    try {
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        delete_uploaded_image($product['image_url']);
        flash_set('Товар удалён.', 'success');
    } catch (Throwable $e) {
        flash_set('Не удалось удалить товар — возможно, он есть в существующих заказах.', 'error');
    }
}

redirect('/admin/products.php');
