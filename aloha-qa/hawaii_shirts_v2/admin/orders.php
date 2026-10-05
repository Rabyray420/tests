<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$statusFilter = $_GET['status'] ?? '';
$validStatuses = array_keys(STATUS_LABELS);

$pdo = get_pdo();
if (in_array($statusFilter, $validStatuses, true)) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE status = ? ORDER BY created_at DESC');
    $stmt->execute([$statusFilter]);
} else {
    $statusFilter = '';
    $stmt = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC');
}
$orders = $stmt->fetchAll();

$pageTitle = 'Заказы — Админка';
require __DIR__ . '/../includes/header.php';
?>

<h1>Админ-панель</h1>
<div class="pills">
  <a href="/admin/products.php" class="pill">Товары</a>
  <a href="/admin/orders.php" class="pill active">Заказы</a>
</div>

<div class="pills">
  <a href="/admin/orders.php" class="pill <?= $statusFilter === '' ? 'active' : '' ?>">Все</a>
  <?php foreach ($validStatuses as $s): ?>
    <a href="/admin/orders.php?status=<?= $s ?>" class="pill <?= $statusFilter === $s ? 'active' : '' ?>"><?= e(STATUS_LABELS[$s]) ?></a>
  <?php endforeach; ?>
</div>

<?php foreach ($orders as $order): ?>
  <div class="card mt-2" style="margin-bottom:1rem">
    <div class="product-card__row" style="flex-wrap:wrap;gap:.75rem">
      <div>
        <strong>Заказ №<?= (int) $order['id'] ?></strong> — <?= e($order['contact_name']) ?> (<?= e($order['contact_email']) ?>)
        <p class="muted" style="font-size:.8rem;margin:.2rem 0 0"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
      </div>
      <div class="actions">
        <span style="font-weight:600"><?= money((float) $order['total']) ?></span>
        <form action="/admin/order_status.php" method="post" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
          <select name="status" class="status-badge status-<?= e($order['status']) ?>" style="border:none;cursor:pointer" onchange="this.form.requestSubmit()">
            <?php foreach ($validStatuses as $s): ?>
              <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= e(STATUS_LABELS[$s]) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <?php
      $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
      $itemsStmt->execute([$order['id']]);
      $orderItems = $itemsStmt->fetchAll();
    ?>
    <div style="margin-top:.75rem;border-top:1px solid var(--lagoon-100);padding-top:.75rem;font-size:.9rem">
      <?php foreach ($orderItems as $item): ?>
        <div class="product-card__row"><span><?= e($item['name']) ?> × <?= (int) $item['quantity'] ?></span><span><?= money($item['quantity'] * (float) $item['price']) ?></span></div>
      <?php endforeach; ?>
      <p class="muted" style="margin:.5rem 0 0">
        <?= e($order['shipping_country']) ?>, <?= e($order['shipping_city']) ?>, <?= e($order['shipping_street']) ?>, <?= e($order['shipping_zip']) ?>
        · <?= e($order['contact_phone']) ?>
      </p>
    </div>
  </div>
<?php endforeach; ?>

<?php if (empty($orders)): ?>
  <p class="muted">Заказов не найдено.</p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
