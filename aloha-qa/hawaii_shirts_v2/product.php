<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
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

$sizes = product_sizes($pdo, $id);
$hasSizes = !empty($sizes);
$stock = (int) $product['stock'];
$outOfStock = $stock === 0 || !(int) $product['active'];
// Без размеров максимум — остаток товара; с размерами его выставит JS по выбранному размеру.
$qtyMax = $hasSizes ? max(1, max($sizes)) : max(1, $stock);

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

    <p class="<?= $outOfStock ? 'stock-out' : 'muted' ?>" style="font-weight:600" data-stock-note>
      <?= $outOfStock ? 'Нет в наличии' : 'В наличии: ' . $stock . ' шт.' ?>
    </p>

    <?php if (!$outOfStock): ?>
      <form action="/cart_add.php" method="post" class="mt-2">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
        <input type="hidden" name="redirect" value="/product.php?id=<?= (int) $product['id'] ?>">

        <?php if ($hasSizes): ?>
          <div class="size-head">
            <span class="size-title">Размер</span>
            <button type="button" class="link-button size-chart-link" data-open-dialog="size-chart">Размерная сетка</button>
          </div>
          <div class="size-pills" data-size-group>
            <?php foreach ($sizes as $size => $sizeStock): ?>
              <label class="size-pill <?= $sizeStock < 1 ? 'is-disabled' : '' ?>">
                <input type="radio" name="size" value="<?= e($size) ?>" data-stock="<?= (int) $sizeStock ?>"
                       data-size-required required <?= $sizeStock < 1 ? 'disabled' : '' ?>>
                <span><?= e($size) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="actions" style="margin-bottom:1rem">
          <span class="muted">Количество</span>
          <?= qty_stepper(1, $qtyMax) ?>
        </div>

        <div class="actions">
          <button type="submit" class="btn btn-outline" style="flex:1">В корзину</button>
          <button type="submit" formaction="/buynow.php" class="btn btn-sunset" style="flex:1">Купить сейчас</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($hasSizes): ?>
  <dialog id="size-chart" class="modal">
    <h2 style="margin-top:0">Размерная сетка</h2>
    <div style="overflow-x:auto">
      <table class="size-table">
        <thead><tr><th>Размер</th><th>Российский</th><th>Обхват груди, см</th><th>Обхват талии, см</th></tr></thead>
        <tbody>
          <?php foreach (SIZE_CHART as $size => $row): ?>
            <tr>
              <td><strong><?= e($size) ?></strong></td>
              <td><?= e($row['ru']) ?></td>
              <td><?= e($row['chest']) ?></td>
              <td><?= e($row['waist']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="muted" style="font-size:.85rem">Измерьте обхват груди по самой широкой части. Между двумя размерами выбирайте больший — рубашка будет свободнее.</p>
    <div class="actions" style="justify-content:flex-end">
      <button type="button" class="btn btn-lagoon btn-sm" data-close-dialog>Закрыть</button>
    </div>
  </dialog>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
