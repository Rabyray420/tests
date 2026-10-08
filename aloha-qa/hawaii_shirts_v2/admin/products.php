<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$products = get_pdo()->query('SELECT * FROM products ORDER BY created_at DESC')->fetchAll();
$sizeMap = sizes_for_products(get_pdo(), array_column($products, 'id'));

$pageTitle = 'Товары — Админка';
require __DIR__ . '/../includes/header.php';
?>

<h1>Админ-панель</h1>
<?= admin_nav('products') ?>

<div class="text-right mt-2" style="margin-bottom:1rem">
  <a href="/admin/product_form.php" class="btn btn-sunset btn-pill">+ Добавить товар</a>
</div>

<div class="card" style="padding:0;overflow-x:auto">
  <table>
    <thead>
      <tr>
        <th>Товар</th><th>Категория</th><th>Цена</th><th>Склад</th><th>Активен</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td>
            <span class="table-thumb">
              <?php if ($p['image_url']): ?>
                <img src="<?= e($p['image_url']) ?>" alt="">
              <?php else: ?>🌺<?php endif; ?>
            </span>
            <?= e($p['name']) ?>
          </td>
          <td class="muted"><?= e($p['category']) ?></td>
          <td><?= money((float) $p['price']) ?></td>
          <td>
            <?= (int) $p['stock'] ?>
            <?php if (!empty($sizeMap[(int) $p['id']])): ?>
              <br><span class="muted" style="font-size:.75rem">
                <?= e(implode(' · ', array_map(fn($s, $n) => "$s:$n", array_keys($sizeMap[(int) $p['id']]), $sizeMap[(int) $p['id']]))) ?>
              </span>
            <?php endif; ?>
          </td>
          <td>
            <form action="/admin/product_toggle.php" method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="status-badge" style="border:none;cursor:pointer;<?= $p['active'] ? 'background:var(--green-100);color:var(--green-700)' : 'background:var(--lagoon-100);color:#6b8a86' ?>">
                <?= $p['active'] ? 'Да' : 'Скрыт' ?>
              </button>
            </form>
          </td>
          <td class="text-right">
            <a href="/admin/product_form.php?id=<?= (int) $p['id'] ?>" style="color:var(--lagoon-600);font-weight:600">Изменить</a>
            &nbsp;
            <form action="/admin/product_delete.php" method="post" class="inline-form" onsubmit="return confirm('Удалить товар «<?= e($p['name']) ?>»?')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="btn-danger-text">Удалить</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (empty($products)): ?>
    <p class="muted" style="padding:1rem">Товаров пока нет — добавьте первый.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
