<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$pdo = get_pdo();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;
$product = ['name' => '', 'description' => '', 'price' => '', 'stock' => '', 'category' => '', 'image_url' => null, 'active' => 1];
$formSizes = []; // размер => остаток (строкой, как введено в форме)
$error = '';

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) redirect('/admin/products.php');
    $product = $found;
    foreach (product_sizes($pdo, $id) as $size => $stock) $formSizes[$size] = (string) $stock;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $product['name'] = trim($_POST['name'] ?? '');
    $product['description'] = trim($_POST['description'] ?? '');
    $product['price'] = trim((string) ($_POST['price'] ?? ''));
    $product['stock'] = trim((string) ($_POST['stock'] ?? ''));
    $product['category'] = trim($_POST['category'] ?? '');
    $product['active'] = isset($_POST['active']) ? 1 : 0;
    $removeImage = isset($_POST['remove_image']);

    // Остатки по размерам: пустое поле = такой размер не продаётся.
    $formSizes = [];
    $sizesInput = is_array($_POST['sizes'] ?? null) ? $_POST['sizes'] : [];
    $sizeStocks = [];
    foreach (SIZES as $s) {
        $raw = trim((string) ($sizesInput[$s] ?? ''));
        if ($raw === '') continue;
        $formSizes[$s] = $raw;
        if (!ctype_digit($raw)) {
            $error = 'Остаток по размеру ' . $s . ' должен быть целым числом, не меньше 0';
        } else {
            $sizeStocks[$s] = (int) $raw;
        }
    }

    if ($error === '' && ($product['name'] === '' || $product['description'] === '' || $product['price'] === '' || $product['category'] === '')) {
        $error = 'Заполните все обязательные поля товара';
    }
    if ($error === '' && (!is_numeric($product['price']) || (float) $product['price'] < 0)) {
        $error = 'Цена должна быть числом, не меньше 0';
    }
    if ($error === '') {
        if (!empty($sizeStocks)) {
            $product['stock'] = (string) array_sum($sizeStocks); // общий остаток — сумма по размерам
        } elseif ($product['stock'] === '' || !ctype_digit($product['stock'])) {
            $error = 'Укажите остаток на складе — либо по размерам, либо общий';
        }
    }

    if ($error === '') {
        try {
            $newImageUrl = handle_image_upload();
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }

    if ($error === '') {
        $imageUrl = $product['image_url'];
        if ($newImageUrl !== null) {
            delete_uploaded_image($imageUrl);
            $imageUrl = $newImageUrl;
        } elseif ($removeImage) {
            delete_uploaded_image($imageUrl);
            $imageUrl = null;
        }

        $pdo->beginTransaction();
        try {
            if ($isEdit) {
                $pdo->prepare('UPDATE products SET name=?, description=?, price=?, stock=?, category=?, image_url=?, active=? WHERE id=?')
                    ->execute([$product['name'], $product['description'], (float) $product['price'], (int) $product['stock'],
                        $product['category'], $imageUrl, $product['active'], $id]);
            } else {
                $pdo->prepare('INSERT INTO products (name, description, price, stock, category, image_url, active) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$product['name'], $product['description'], (float) $product['price'], (int) $product['stock'],
                        $product['category'], $imageUrl, $product['active']]);
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM product_sizes WHERE product_id = ?')->execute([$id]);
            $insert = $pdo->prepare('INSERT INTO product_sizes (product_id, size, stock) VALUES (?, ?, ?)');
            foreach ($sizeStocks as $s => $stock) $insert->execute([$id, $s, $stock]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        redirect('/admin/products.php');
    }
    $product['image_url'] = $removeImage ? null : $product['image_url'];
}

$pageTitle = ($isEdit ? 'Редактировать товар' : 'Новый товар') . ' — Админка';
require __DIR__ . '/../includes/header.php';
?>

<h1>Админ-панель</h1>
<?= admin_nav('products') ?>

<div class="grid-2">
  <form id="product-form-hidden" action="/admin/product_form.php" method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

    <h2 style="margin-top:0"><?= $isEdit ? 'Редактировать товар' : 'Новый товар' ?></h2>

    <input type="text" name="name" placeholder="Название" value="<?= e($product['name']) ?>" required>
    <textarea name="description" placeholder="Описание" rows="3" required><?= e($product['description']) ?></textarea>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <input type="number" name="price" placeholder="Цена, ₽" min="0" step="1" value="<?= e((string) (is_numeric($product['price']) ? (float) $product['price'] : $product['price'])) ?>" required>
      <input type="number" name="stock" id="stock-input" placeholder="Общий остаток" min="0" step="1" value="<?= e((string) $product['stock']) ?>" <?= empty($formSizes) ? '' : 'readonly' ?>>
    </div>

    <input type="text" name="category" placeholder="Категория (например, Классические)" value="<?= e($product['category']) ?>" required>

    <fieldset class="size-admin">
      <legend>Размеры и остатки</legend>
      <p class="muted" style="font-size:.85rem;margin:0 0 .5rem">
        Укажите остаток для каждого размера, который продаётся. Пустое поле — размера нет.
        Если ни один размер не указан, товар продаётся без выбора размера, а остаток берётся из поля выше.
      </p>
      <div class="size-admin__grid">
        <?php foreach (SIZES as $s): ?>
          <label class="size-admin__cell">
            <span><?= e($s) ?></span>
            <input type="number" name="sizes[<?= e($s) ?>]" min="0" step="1" placeholder="—" value="<?= e($formSizes[$s] ?? '') ?>" data-size-stock-input>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <label class="checkbox-row">
      <input type="checkbox" name="active" <?= $product['active'] ? 'checked' : '' ?>>
      Показывать в каталоге
    </label>

    <?php if ($error): ?><p class="error-text"><?= e($error) ?></p><?php endif; ?>

    <div class="actions mt-2">
      <button type="submit" class="btn btn-lagoon">Сохранить</button>
      <a href="/admin/products.php" style="color:var(--lagoon-600)">Отмена</a>
    </div>
  </form>

  <div>
    <label class="muted" style="font-size:.8rem;display:block;margin-bottom:.25rem">Фото товара</label>

    <div id="dropzone" class="dropzone">
      <?php if ($product['image_url']): ?>
        <img id="preview" src="<?= e($product['image_url']) ?>" alt="Фото товара">
      <?php else: ?>
        <img id="preview" alt="Фото товара" hidden>
      <?php endif; ?>
      <div id="dropzone-hint" class="dropzone__hint" <?= $product['image_url'] ? 'hidden' : '' ?>>
        📷<br>Перетащите фото сюда или нажмите, чтобы выбрать файл
      </div>
    </div>
    <input type="file" name="image" id="image-input" accept="image/*" form="product-form-hidden" style="display:none">

    <?php if ($product['image_url']): ?>
      <label class="checkbox-row mt-2">
        <input type="checkbox" name="remove_image" id="remove-image" form="product-form-hidden">
        Удалить текущее фото
      </label>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
