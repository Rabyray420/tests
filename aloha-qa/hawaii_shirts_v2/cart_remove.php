<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/cart.php');
csrf_check();

$productId = (int) ($_POST['product_id'] ?? 0);
if ($productId > 0) {
    cart_remove($productId);
}

redirect('/cart.php');
