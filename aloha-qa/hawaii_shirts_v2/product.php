<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = get_pdo()->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Товар не найден';
    require __DIR__ . '/includes/header.php';
    echo '<p class="muted">Товар не найден.</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$outOfStock = (int) $product['stock'] === 0;
$pageTitle = $product['name'] . ' — Aloha Threads';
require __DIR__ . '/includes/header.php';
?>

<a href="/index.php" class="muted">← Назад в каталог</a>

<div class="product-detail mt-2">
  <div class="product-detail__image">
    <?php if ($product['image_url']): ?>
      <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?>">
    <?php else: ?>
      <span class="placeholder" style="font-size:3rem">🌺</span>
    <?php endif; ?>
  </div>
  <div>
    <p class="product-card__category"><?= e($product['category']) ?></p>
    <h1><?= e($product['name']) ?></h1>
    <p class="price" style="font-size:1.5rem"><?= money((float) $product['price']) ?></p>
    <p><?= nl2br(e($product['description'])) ?></p>

    <p class="<?= $outOfStock ? 'stock-out' : 'muted' ?>" style="font-weight:600">
      <?= $outOfStock ? 'Нет в наличии' : 'В наличии: ' . (int) $product['stock'] . ' шт.' ?>
    </p>

    <?php if (!$outOfStock): ?>
      <form action="/cart_add.php" method="post" class="mt-2">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
        <input type="hidden" name="redirect" value="/product.php?id=<?= (int) $product['id'] ?>">

        <div class="actions" style="margin-bottom:1rem">
          <label class="muted">Количество</label>
          <div class="qty-control">
            <button type="button" onclick="this.nextElementSibling.stepDown()">−</button>
            <input type="number" name="quantity" value="1" min="1" max="<?= (int) $product['stock'] ?>">
            <button type="button" onclick="this.previousElementSibling.stepUp()">+</button>
          </div>
        </div>

        <div class="actions">
          <button type="submit" class="btn btn-outline" style="flex:1">В корзину</button>
          <button type="submit" formaction="/checkout.php" formmethod="get" class="btn btn-sunset" style="flex:1">
            Купить сейчас
          </button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
