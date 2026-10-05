<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;
$product = ['name' => '', 'description' => '', 'price' => '', 'stock' => '', 'category' => '', 'image_url' => null, 'active' => 1];
$error = '';

if ($isEdit) {
    $stmt = get_pdo()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) redirect('/admin/products.php');
    $product = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $product['name'] = trim($_POST['name'] ?? '');
    $product['description'] = trim($_POST['description'] ?? '');
    $product['price'] = $_POST['price'] ?? '';
    $product['stock'] = $_POST['stock'] ?? '';
    $product['category'] = trim($_POST['category'] ?? '');
    $product['active'] = isset($_POST['active']) ? 1 : 0;
    $removeImage = isset($_POST['remove_image']);

    if ($product['name'] === '' || $product['description'] === '' || $product['price'] === '' || $product['category'] === '') {
        $error = 'Заполните все обязательные поля товара';
    }

    if ($error === '') {
        try {
            $newImageUrl = handle_image_upload();
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }

    if ($error === '') {
        $pdo = get_pdo();
        $imageUrl = $product['image_url'];
        if ($newImageUrl !== null) {
            delete_uploaded_image($imageUrl);
            $imageUrl = $newImageUrl;
        } elseif ($removeImage) {
            delete_uploaded_image($imageUrl);
            $imageUrl = null;
        }

        if ($isEdit) {
            $stmt = $pdo->prepare(
                'UPDATE products SET name=?, description=?, price=?, stock=?, category=?, image_url=?, active=? WHERE id=?'
            );
            $stmt->execute([
                $product['name'], $product['description'], (float) $product['price'], (int) $product['stock'],
                $product['category'], $imageUrl, $product['active'], $id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO products (name, description, price, stock, category, image_url, active) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $product['name'], $product['description'], (float) $product['price'], (int) $product['stock'],
                $product['category'], $imageUrl, $product['active'],
            ]);
        }
        redirect('/admin/products.php');
    }
    $product['image_url'] = $removeImage ? null : $product['image_url'];
}

$pageTitle = ($isEdit ? 'Редактировать товар' : 'Новый товар') . ' — Админка';
require __DIR__ . '/../includes/header.php';
?>

<h1>Админ-панель</h1>
<div class="pills">
  <a href="/admin/products.php" class="pill active">Товары</a>
  <a href="/admin/orders.php" class="pill">Заказы</a>
</div>

<div class="grid-2">
  <form id="product-form-hidden" action="/admin/product_form.php" method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

    <h2 style="margin-top:0"><?= $isEdit ? 'Редактировать товар' : 'Новый товар' ?></h2>

    <input type="text" name="name" placeholder="Название" value="<?= e($product['name']) ?>" required>
    <textarea name="description" placeholder="Описание" rows="3" required><?= e($product['description']) ?></textarea>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <input type="number" name="price" placeholder="Цена, ₽" min="0" step="1" value="<?= e((string) $product['price']) ?>" required>
      <input type="number" name="stock" placeholder="Остаток на складе" min="0" step="1" value="<?= e((string) $product['stock']) ?>" required>
    </div>

    <input type="text" name="category" placeholder="Категория (например, Классические)" value="<?= e($product['category']) ?>" required>

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

<script src="/assets/app.js"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
