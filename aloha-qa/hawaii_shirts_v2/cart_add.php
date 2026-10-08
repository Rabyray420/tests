<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/index.php');
csrf_check();

$productId = (int) ($_POST['product_id'] ?? 0);
$size = trim((string) ($_POST['size'] ?? ''));
$quantity = max(1, (int) ($_POST['quantity'] ?? 1));
$redirectTo = safe_redirect_path($_POST['redirect'] ?? null, '/cart.php');

$result = cart_add($productId, $size, $quantity);

if (!$result['ok']) {
    flash_set($result['error'], 'error');
} elseif ($result['clamped']) {
    flash_set('В корзину добавлено максимально доступное количество: ' . $result['quantity'] . ' шт.', 'success');
} else {
    flash_set('Товар добавлен в корзину.', 'success');
}

redirect($redirectTo);
