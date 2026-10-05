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
  <?php foreach ($products as $p): ?>
    <a href="/product.php?id=<?= (int) $p['id'] ?>" class="product-card">
      <div class="product-card__image">
        <?php if ($p['image_url']): ?>
          <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>">
        <?php else: ?>
          <span class="placeholder">🌺</span>
        <?php endif; ?>
      </div>
      <div class="product-card__body">
        <p class="product-card__category"><?= e($p['category']) ?></p>
        <p class="product-card__name"><?= e($p['name']) ?></p>
        <div class="product-card__row">
          <span class="price"><?= money((float) $p['price']) ?></span>
          <?php if ((int) $p['stock'] === 0): ?>
            <span class="stock-out">Нет в наличии</span>
          <?php elseif ((int) $p['stock'] <= 5): ?>
            <span class="stock-low">Осталось <?= (int) $p['stock'] ?></span>
          <?php endif; ?>
        </div>
      </div>
    </a>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
