<?php
// «Купить сейчас»: проверяем товар/размер и переходим к оформлению одной позиции,
// не трогая корзину.
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/index.php');
csrf_check();

$productId = (int) ($_POST['product_id'] ?? 0);
$size = trim((string) ($_POST['size'] ?? ''));
$quantity = max(1, (int) ($_POST['quantity'] ?? 1));
$back = safe_redirect_path($_POST['redirect'] ?? null, '/index.php');

$info = resolve_stock(get_pdo(), $productId, $size);
if (!$info) {
    flash_set(stock_error_message(get_pdo(), $productId, $size), 'error');
    redirect($back);
}
if ($info['available'] < 1) {
    flash_set('Этого товара нет в наличии.', 'error');
    redirect($back);
}

$quantity = min($quantity, $info['available']);
redirect('/checkout.php?' . http_build_query(['product_id' => $productId, 'size' => $size, 'quantity' => $quantity]));
