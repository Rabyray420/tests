<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = get_pdo();
$user = current_user();
$errors = [];     // ошибки по полям
$formErrors = []; // общие ошибки (склад, пустая корзина)

/**
 * Позиции заказа: одна позиция («Купить сейчас») или вся корзина.
 * Формат как у cart_items(): product_id, size, quantity, product, available.
 */
function checkout_resolve_items(PDO $pdo, string $mode, int $productId = 0, string $size = '', int $quantity = 1): array {
    if ($mode === 'buynow') {
        $info = resolve_stock($pdo, $productId, $size);
        if (!$info || $info['available'] < 1) return [];
        return [[
            'product_id' => $productId,
            'size' => $size,
            'quantity' => max(1, min($quantity, $info['available'])),
            'product' => $info['product'],
            'available' => $info['available'],
        ]];
    }
    return cart_items();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $mode = ($_POST['mode'] ?? '') === 'buynow' ? 'buynow' : 'cart';
    $items = checkout_resolve_items(
        $pdo, $mode,
        (int) ($_POST['product_id'] ?? 0),
        trim((string) ($_POST['size'] ?? '')),
        (int) ($_POST['quantity'] ?? 1)
    );

    [$v, $errors] = validate_checkout($_POST);

    if (empty($items)) $formErrors[] = 'Корзина пуста или товар недоступен.';
    foreach ($items as $item) {
        if ($item['quantity'] > $item['available']) {
            $formErrors[] = 'Недостаточно товара на складе: ' . item_label($item['product']['name'], $item['size']);
        }
    }

    if (empty($errors) && empty($formErrors)) {
        $total = 0.0;
        foreach ($items as $item) $total += $item['quantity'] * (float) $item['product']['price'];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO orders (user_id, status, total, contact_name, contact_email, contact_phone,
                    shipping_country, shipping_city, shipping_street, shipping_house, shipping_apartment, shipping_zip)
                 VALUES (?, \'PAID\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['id'] ?? null, $total, $v['name'], $v['email'], $v['phone'],
                $v['country'], $v['city'], $v['street'], $v['house'], $v['apartment'], $v['zip'],
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $orderItemsForConfirmation = [];
            foreach ($items as $item) {
                $p = $item['product'];
                $label = item_label($p['name'], $item['size']);

                // Списываем остаток; условие stock >= ? защищает от одновременной покупки последней штуки.
                if ($item['size'] === '') {
                    $upd = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
                    $upd->execute([$item['quantity'], $p['id'], $item['quantity']]);
                    if ($upd->rowCount() === 0) throw new RuntimeException('Недостаточно товара на складе: ' . $label);
                } else {
                    $upd = $pdo->prepare('UPDATE product_sizes SET stock = stock - ? WHERE product_id = ? AND size = ? AND stock >= ?');
                    $upd->execute([$item['quantity'], $p['id'], $item['size'], $item['quantity']]);
                    if ($upd->rowCount() === 0) throw new RuntimeException('Недостаточно товара на складе: ' . $label);
                    $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?')->execute([$item['quantity'], $p['id']]);
                }

                $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, size, price, quantity) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$orderId, $p['id'], $p['name'], $item['size'], $p['price'], $item['quantity']]);

                $orderItemsForConfirmation[] = ['name' => $label, 'price' => (float) $p['price'], 'quantity' => $item['quantity']];
            }

            if ($mode === 'cart') cart_clear();

            $pdo->commit();

            $_SESSION['last_order'] = [
                'id' => $orderId,
                'status' => 'PAID',
                'total' => $total,
                'contact_email' => $v['email'],
                'items' => $orderItemsForConfirmation,
            ];
            redirect('/order_confirmation.php');
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            $formErrors[] = $e->getMessage();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $formErrors[] = 'Не удалось оформить заказ. Попробуйте ещё раз.';
        }
    }
} else {
    if (isset($_GET['product_id'])) {
        $mode = 'buynow';
        $items = checkout_resolve_items(
            $pdo, $mode,
            (int) $_GET['product_id'],
            trim((string) ($_GET['size'] ?? '')),
            (int) ($_GET['quantity'] ?? 1)
        );
    } else {
        $mode = 'cart';
        $items = checkout_resolve_items($pdo, $mode);
    }
    $v = [
        'name' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'phone' => normalize_phone($user['phone'] ?? null) ?? '',
        'country' => 'Россия',
        'city' => '', 'street' => '', 'house' => '', 'apartment' => '', 'zip' => '',
    ];
}

if (empty($items) && empty($formErrors)) {
    $pageTitle = 'Оформление заказа — Aloha Threads';
    require __DIR__ . '/includes/header.php';
    echo '<p class="muted">Нечего оформлять — корзина пуста или товар недоступен. <a href="/index.php" style="color:var(--lagoon-600);font-weight:600">Перейти в каталог →</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$total = 0.0;
foreach ($items as $item) $total += $item['quantity'] * (float) $item['product']['price'];

$pageTitle = 'Оформление заказа — Aloha Threads';
require __DIR__ . '/includes/header.php';
?>

<h1>Оформление заказа</h1>

<?php foreach ($formErrors as $err): ?>
  <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>
<?php if (!empty($errors)): ?>
  <div class="flash flash-error">Проверьте выделенные поля и попробуйте снова.</div>
<?php endif; ?>

<div class="grid-2">
  <form action="/checkout.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="mode" value="<?= e($mode) ?>">
    <?php if ($mode === 'buynow' && !empty($items)): ?>
      <input type="hidden" name="product_id" value="<?= (int) $items[0]['product_id'] ?>">
      <input type="hidden" name="size" value="<?= e($items[0]['size']) ?>">
      <input type="hidden" name="quantity" value="<?= (int) $items[0]['quantity'] ?>">
    <?php endif; ?>

    <fieldset>
      <legend>Контактные данные</legend>
      <?= form_field('Имя и фамилия', 'name', $v['name'], $errors, ['autocomplete' => 'name']) ?>
      <?= form_field('Email', 'email', $v['email'], $errors, ['type' => 'email', 'autocomplete' => 'email', 'placeholder' => 'name@example.ru']) ?>
      <?= form_field('Телефон', 'phone', $v['phone'] === '' ? PHONE_PLACEHOLDER : $v['phone'], $errors, phone_field_attrs()) ?>
    </fieldset>

    <fieldset>
      <legend>Адрес доставки</legend>
      <?= form_field('Страна', 'country', $v['country'], $errors, ['autocomplete' => 'country-name']) ?>
      <?= form_field('Город', 'city', $v['city'], $errors, ['autocomplete' => 'address-level2']) ?>
      <?= form_field('Улица', 'street', $v['street'], $errors, ['autocomplete' => 'address-line1', 'placeholder' => 'Например, Тверская улица']) ?>
      <div class="field-row">
        <?= form_field('Дом', 'house', $v['house'], $errors, ['maxlength' => 20, 'placeholder' => 'Например, 12к1']) ?>
        <?= form_field('Квартира / офис', 'apartment', $v['apartment'], $errors, ['maxlength' => 20, 'autocomplete' => 'address-line2', 'placeholder' => 'Например, 45']) ?>
      </div>
      <?= form_field('Индекс', 'zip', $v['zip'], $errors, ['autocomplete' => 'postal-code', 'inputmode' => 'numeric', 'maxlength' => 20]) ?>
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
      <div class="product-card__row" style="margin-bottom:.5rem;font-size:.9rem;gap:.75rem">
        <span><?= e(item_label($item['product']['name'], $item['size'])) ?> × <?= $item['quantity'] ?></span>
        <span style="white-space:nowrap"><?= money($item['quantity'] * (float) $item['product']['price']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="product-card__row" style="border-top:1px solid var(--lagoon-200);padding-top:.75rem;font-weight:600">
      <span>Итого</span>
      <span><?= money($total) ?></span>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
