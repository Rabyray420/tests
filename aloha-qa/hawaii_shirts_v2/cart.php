<?php
require_once __DIR__ . '/includes/bootstrap.php';

$items = cart_items();
$pageTitle = 'Корзина — Aloha Threads';
require __DIR__ . '/includes/header.php';
?>

<h1>Корзина</h1>

<?php if (empty($items)): ?>
  <p class="muted">Ваша корзина пуста. <a href="/index.php" style="color:var(--lagoon-600);font-weight:600">Перейти в каталог →</a></p>
<?php else: ?>

  <?php foreach ($items as $item): $p = $item['product']; ?>
    <div class="cart-row">
      <div class="cart-row__thumb">
        <?php if ($p['image_url']): ?>
          <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>">
        <?php else: ?><span class="placeholder">🌺</span><?php endif; ?>
      </div>
      <div class="cart-row__info">
        <a href="/product.php?id=<?= (int) $p['id'] ?>" style="font-weight:600"><?= e($p['name']) ?></a>
        <p class="muted"><?= money((float) $p['price']) ?></p>
      </div>
      <form action="/cart_update.php" method="post" class="qty-control">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
        <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= (int) $p['stock'] ?>" style="width:2.5rem" onchange="this.form.requestSubmit()">
        <button type="submit" class="btn btn-sm btn-outline">Обновить</button>
      </form>
      <p style="width:6rem; text-align:right; font-weight:600">
        <?= money($item['quantity'] * (float) $p['price']) ?>
      </p>
      <form action="/cart_remove.php" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
        <button type="submit" class="btn-danger-text" aria-label="Удалить">✕</button>
      </form>
    </div>
  <?php endforeach; ?>

  <div class="cart-summary mt-2">
    <span style="font-size:1.1rem;font-weight:600">Итого: <?= money(cart_total()) ?></span>
    <a href="/checkout.php" class="btn btn-sunset">Оформить заказ</a>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
