<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/index.php');
csrf_check();

$productId = (int) ($_POST['product_id'] ?? 0);
$quantity = max(1, (int) ($_POST['quantity'] ?? 1));
$redirectTo = $_POST['redirect'] ?? '/cart.php';

$stmt = get_pdo()->prepare('SELECT id, stock FROM products WHERE id = ? AND active = 1');
$stmt->execute([$productId]);
$product = $stmt->fetch();

if ($product) {
    cart_add($productId, $quantity);
    flash_set('Товар добавлен в корзину.', 'success');
} else {
    flash_set('Товар недоступен.', 'error');
}

redirect($redirectTo);
