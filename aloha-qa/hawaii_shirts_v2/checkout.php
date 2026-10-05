<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = get_pdo();
$user = current_user();
$errors = [];

function checkout_resolve_items(PDO $pdo, string $mode, int $productId = 0, int $quantity = 1): array {
    if ($mode === 'buynow') {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND active = 1');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) return [];
        $quantity = max(1, min($quantity, (int) $product['stock']));
        return [['product_id' => (int) $product['id'], 'quantity' => $quantity, 'product' => $product]];
    }
    return cart_items();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $mode = ($_POST['mode'] ?? '') === 'buynow' ? 'buynow' : 'cart';
    $items = checkout_resolve_items($pdo, $mode, (int) ($_POST['product_id'] ?? 0), (int) ($_POST['quantity'] ?? 1));

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $zip = trim($_POST['zip'] ?? '');

    if (empty($items)) $errors[] = 'Корзина пуста или товар недоступен.';
    if ($name === '' || $email === '' || $phone === '') $errors[] = 'Заполните контактные данные.';
    if ($country === '' || $city === '' || $street === '' || $zip === '') $errors[] = 'Заполните адрес доставки.';

    foreach ($items as $item) {
        if ($item['quantity'] > (int) $item['product']['stock']) {
            $errors[] = 'Недостаточно товара на складе: ' . $item['product']['name'];
        }
    }

    if (empty($errors)) {
        $total = 0.0;
        foreach ($items as $item) $total += $item['quantity'] * (float) $item['product']['price'];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO orders (user_id, status, total, contact_name, contact_email, contact_phone,
                    shipping_country, shipping_city, shipping_street, shipping_zip)
                 VALUES (?, \'PAID\', ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$user['id'] ?? null, $total, $name, $email, $phone, $country, $city, $street, $zip]);
            $orderId = (int) $pdo->lastInsertId();

            $orderItemsForConfirmation = [];
            foreach ($items as $item) {
                $p = $item['product'];
                $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, price, quantity) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$orderId, $p['id'], $p['name'], $p['price'], $item['quantity']]);

                $stmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                $stmt->execute([$item['quantity'], $p['id']]);

                $orderItemsForConfirmation[] = ['name' => $p['name'], 'price' => (float) $p['price'], 'quantity' => $item['quantity']];
            }

            if ($mode === 'cart') cart_clear();

            $pdo->commit();

            $_SESSION['last_order'] = [
                'id' => $orderId,
                'status' => 'PAID',
                'total' => $total,
                'contact_email' => $email,
                'items' => $orderItemsForConfirmation,
            ];
            redirect('/order_confirmation.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Не удалось оформить заказ. Попробуйте ещё раз.';
        }
    }
} else {
    if (isset($_GET['product_id'])) {
        $mode = 'buynow';
        $productId = (int) $_GET['product_id'];
        $quantity = max(1, (int) ($_GET['quantity'] ?? 1));
    } else {
        $mode = 'cart';
        $productId = 0;
        $quantity = 1;
    }
    $items = checkout_resolve_items($pdo, $mode, $productId, $quantity);
    $name = $user['name'] ?? '';
    $email = $user['email'] ?? '';
    $phone = $user['phone'] ?? '';
    $country = 'Россия';
    $city = $street = $zip = '';
}

if (empty($items) && empty($errors)) {
    $pageTitle = 'Оформление заказа — Aloha Threads';
    require __DIR__ . '/includes/header.php';
    echo '<p class="muted">Нечего оформлять — корзина пуста. <a href="/index.php" style="color:var(--lagoon-600);font-weight:600">Перейти в каталог →</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$total = 0.0;
foreach ($items as $item) $total += $item['quantity'] * (float) $item['product']['price'];

$pageTitle = 'Оформление заказа — Aloha Threads';
require __DIR__ . '/includes/header.php';
?>

<h1>Оформление заказа</h1>

<?php foreach ($errors as $err): ?>
  <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="grid-2">
  <form action="/checkout.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="mode" value="<?= e($mode) ?>">
    <?php if ($mode === 'buynow'): ?>
      <input type="hidden" name="product_id" value="<?= (int) $items[0]['product_id'] ?>">
      <input type="hidden" name="quantity" value="<?= (int) $items[0]['quantity'] ?>">
    <?php endif; ?>

    <fieldset>
      <legend>Контактные данные</legend>
      <input type="text" name="name" placeholder="Имя и фамилия" value="<?= e($name) ?>" required>
      <input type="email" name="email" placeholder="Email" value="<?= e($email) ?>" required>
      <input type="tel" name="phone" placeholder="Телефон" value="<?= e($phone) ?>" required>
    </fieldset>

    <fieldset>
      <legend>Адрес доставки</legend>
      <input type="text" name="country" placeholder="Страна" value="<?= e($country) ?>" required>
      <input type="text" name="city" placeholder="Город" value="<?= e($city) ?>" required>
      <input type="text" name="street" placeholder="Улица, дом, квартира" value="<?= e($street) ?>" required>
      <input type="text" name="zip" placeholder="Индекс" value="<?= e($zip) ?>" required>
    </fieldset>

    <fieldset>
      <legend>Оплата</legend>
      <label class="checkbox-row"><input type="radio" checked readonly> Банковская карта</label>
      <p class="muted" style="font-size:.8rem;margin-top:.5rem">
        Демо-режим: реальное списание средств не производится, оплата подтверждается автоматически.
      </p>
    </fieldset>

    <?php if (!$user): ?>
      <p class="muted" style="font-size:.9rem">
        Есть аккаунт? <a href="/login.php" style="color:var(--lagoon-700);font-weight:600">Войдите</a>,
        чтобы отслеживать заказ в профиле. Можно оформить и без регистрации.
      </p>
    <?php endif; ?>

    <button type="submit" class="btn btn-sunset" style="width:100%;margin-top:1rem">
      Оплатить <?= money($total) ?>
    </button>
  </form>

  <div class="card" style="height:fit-content">
    <h2 style="margin-top:0">Ваш заказ</h2>
    <?php foreach ($items as $item): ?>
      <div class="product-card__row" style="margin-bottom:.5rem;font-size:.9rem">
        <span><?= e($item['product']['name']) ?> × <?= $item['quantity'] ?></span>
        <span><?= money($item['quantity'] * (float) $item['product']['price']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="product-card__row" style="border-top:1px solid var(--lagoon-200);padding-top:.75rem;font-weight:600">
      <span>Итого</span>
      <span><?= money($total) ?></span>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
