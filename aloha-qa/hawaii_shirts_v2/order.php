<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);

$stmt = get_pdo()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();

$isOwner = $order && $order['user_id'] !== null && (int) $order['user_id'] === $user['id'];
$isAdmin = $user['role'] === 'ADMIN';

if (!$order || (!$isOwner && !$isAdmin)) {
    http_response_code(404);
    $pageTitle = 'Заказ не найден';
    require __DIR__ . '/includes/header.php';
    echo '<p class="muted">Заказ не найден.</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = get_pdo()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$isCancelled = $order['status'] === 'CANCELLED';
$currentIndex = array_search($order['status'], STATUS_ORDER, true);

$pageTitle = 'Заказ №' . $id . ' — Aloha Threads';
require __DIR__ . '/includes/header.php';
?>

<a href="/account.php" class="muted">← Мои заказы</a>
<h1 style="margin-bottom:.25rem">Заказ №<?= (int) $order['id'] ?></h1>
<p class="muted"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>

<?php if (!$isCancelled): ?>
  <div class="tracker">
    <?php foreach (STATUS_ORDER as $idx => $status): ?>
      <div class="tracker__step">
        <div class="tracker__circle <?= $idx <= $currentIndex ? 'done' : '' ?>"><?= $idx + 1 ?></div>
        <div class="tracker__label"><?= e(STATUS_LABELS[$status]) ?></div>
      </div>
      <?php if ($idx < count(STATUS_ORDER) - 1): ?>
        <div class="tracker__line <?= $idx < $currentIndex ? 'done' : '' ?>"></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <span class="status-badge status-CANCELLED mt-2" style="display:inline-block">Заказ отменён</span>
<?php endif; ?>

<div class="card mt-2">
  <?php foreach ($items as $item): ?>
    <div class="product-card__row" style="margin-bottom:.5rem;font-size:.9rem">
      <span><?= e($item['name']) ?> × <?= (int) $item['quantity'] ?></span>
      <span><?= money($item['quantity'] * (float) $item['price']) ?></span>
    </div>
  <?php endforeach; ?>
  <div class="product-card__row" style="border-top:1px solid var(--lagoon-200);padding-top:.75rem;font-weight:600">
    <span>Итого</span>
    <span><?= money((float) $order['total']) ?></span>
  </div>
</div>

<div class="card mt-2" style="background:var(--lagoon-50);border:none">
  <p style="font-weight:600;margin:0 0 .5rem">Доставка</p>
  <p style="margin:0"><?= e($order['contact_name']) ?> · <?= e($order['contact_phone']) ?> · <?= e($order['contact_email']) ?></p>
  <p style="margin:.25rem 0 0"><?= e($order['shipping_country']) ?>, <?= e($order['shipping_city']) ?>, <?= e($order['shipping_street']) ?>, <?= e($order['shipping_zip']) ?></p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
