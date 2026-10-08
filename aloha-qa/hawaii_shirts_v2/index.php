<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = get_pdo();
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE active = 1 ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$activeCategory = $_GET['category'] ?? '';
if ($activeCategory !== '' && in_array($activeCategory, $categories, true)) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE active = 1 AND category = ? ORDER BY created_at DESC');
    $stmt->execute([$activeCategory]);
} else {
    $activeCategory = '';
    $stmt = $pdo->query('SELECT * FROM products WHERE active = 1 ORDER BY created_at DESC');
}
$products = $stmt->fetchAll();
$sizeMap = sizes_for_products($pdo, array_column($products, 'id'));

$pageTitle = 'Aloha Threads — гавайские рубашки';
require __DIR__ . '/includes/header.php';
?>

<div class="hero">
  <h1>Гавайские рубашки Aloha Threads</h1>
  <p>Яркие принты, лёгкие ткани и настроение отпуска круглый год. Выбирайте любимый узор и заказывайте с доставкой.</p>
</div>

<div class="pills">
  <a href="/index.php" class="pill <?= $activeCategory === '' ? 'active' : '' ?>">Все</a>
  <?php foreach ($categories as $cat): ?>
    <a href="/index.php?category=<?= urlencode($cat) ?>" class="pill <?= $activeCategory === $cat ? 'active' : '' ?>"><?= e($cat) ?></a>
  <?php endforeach; ?>
</div>

<?php if (empty($products)): ?>
  <p class="muted">Товары не найдены.</p>
<?php endif; ?>

<div class="product-grid">
  <?php foreach ($products as $p):
    $sizes = $sizeMap[(int) $p['id']] ?? [];
    $inStock = (int) $p['stock'] > 0;
  ?>
    <div class="product-card">
      <a href="/product.php?id=<?= (int) $p['id'] ?>" class="product-card__link">
        <div class="product-card__image">
          <?php if ($p['image_url']): ?>
            <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
          <?php else: ?>
            <span class="placeholder">🌺</span>
          <?php endif; ?>
        </div>
        <div class="product-card__body">
          <p class="product-card__category"><?= e($p['category']) ?></p>
          <p class="product-card__name"><?= e($p['name']) ?></p>
          <div class="product-card__row">
            <span class="price"><?= money((float) $p['price']) ?></span>
            <?php if (!$inStock): ?>
              <span class="stock-out">Нет в наличии</span>
            <?php elseif ((int) $p['stock'] <= 5): ?>
              <span class="stock-low">Осталось <?= (int) $p['stock'] ?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>

      <?php if ($inStock): ?>
        <form action="/cart_add.php" method="post" class="card-buy">
          <?= csrf_field() ?>
          <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
          <input type="hidden" name="quantity" value="1">
          <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
          <?php if (!empty($sizes)): ?>
            <select name="size" required data-size-required aria-label="Размер">
              <option value="">Размер</option>
              <?php foreach ($sizes as $size => $stock): ?>
                <option value="<?= e($size) ?>" <?= $stock < 1 ? 'disabled' : '' ?>><?= e($size) ?><?= $stock < 1 ? ' — нет' : '' ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <button type="submit" class="btn btn-outline btn-sm">В корзину</button>
          <button type="submit" formaction="/buynow.php" class="btn btn-sunset btn-sm">Купить сейчас</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
