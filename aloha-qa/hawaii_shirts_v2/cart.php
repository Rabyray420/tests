<?php
require_once __DIR__ . '/includes/bootstrap.php';

$items = cart_items();

// Остаток мог уменьшиться с момента добавления в корзину — подгоняем количество.
$adjusted = false;
foreach ($items as $item) {
    if ($item['available'] >= 1 && $item['quantity'] > $item['available']) {
        cart_set_quantity($item['product_id'], $item['size'], $item['available']);
        $adjusted = true;
    }
}
if ($adjusted) {
    flash_set('Количество некоторых товаров уменьшено до доступного остатка.', 'error');
    redirect('/cart.php');
}

$hasProblems = false;
foreach ($items as $item) {
    if ($item['available'] < 1) $hasProblems = true;
}

$pageTitle = 'Корзина — Aloha Threads';
require __DIR__ . '/includes/header.php';
?>

<h1>Корзина</h1>

<?php if (empty($items)): ?>
  <p class="muted">Ваша корзина пуста. <a href="/index.php" style="color:var(--lagoon-600);font-weight:600">Перейти в каталог →</a></p>
<?php else: ?>

  <?php foreach ($items as $item):
    $p = $item['product'];
    $unavailable = $item['available'] < 1;
  ?>
    <div class="cart-row">
      <div class="cart-row__thumb">
        <?php if ($p['image_url']): ?>
          <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>">
        <?php else: ?><span class="placeholder">🌺</span><?php endif; ?>
      </div>
      <div class="cart-row__info">
        <a href="/product.php?id=<?= (int) $p['id'] ?>" style="font-weight:600"><?= e($p['name']) ?></a>
        <p class="muted" style="margin:.15rem 0 0">
          <?= $item['size'] !== '' ? 'Размер ' . e($item['size']) . ' · ' : '' ?><?= money((float) $p['price']) ?>
        </p>
        <?php if ($unavailable): ?>
          <p class="error-text" style="margin:.3rem 0 0">Нет в наличии — удалите позицию из корзины.</p>
        <?php endif; ?>
      </div>

      <?php if (!$unavailable): ?>
        <form action="/cart_update.php" method="post" data-autosubmit-qty>
          <?= csrf_field() ?>
          <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
          <input type="hidden" name="size" value="<?= e($item['size']) ?>">
          <?= qty_stepper($item['quantity'], $item['available']) ?>
          <noscript><button type="submit" class="btn btn-sm btn-outline">Обновить</button></noscript>
        </form>
      <?php endif; ?>

      <p style="min-width:5.5rem; text-align:right; font-weight:600; margin:0">
        <?= money($item['quantity'] * (float) $p['price']) ?>
      </p>
      <form action="/cart_remove.php" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
        <input type="hidden" name="size" value="<?= e($item['size']) ?>">
        <button type="submit" class="btn-danger-text" aria-label="Удалить из корзины">✕</button>
      </form>
    </div>
  <?php endforeach; ?>

  <div class="cart-summary mt-2">
    <span style="font-size:1.1rem;font-weight:600">Итого: <?= money(cart_total()) ?></span>
    <?php if ($hasProblems): ?>
      <span class="btn btn-sunset" style="opacity:.4;cursor:not-allowed" aria-disabled="true">Оформить заказ</span>
    <?php else: ?>
      <a href="/checkout.php" class="btn btn-sunset">Оформить заказ</a>
    <?php endif; ?>
  </div>
  <?php if ($hasProblems): ?>
    <p class="error-text" style="margin-top:.75rem">Исправьте отмеченные позиции, чтобы оформить заказ.</p>
  <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
