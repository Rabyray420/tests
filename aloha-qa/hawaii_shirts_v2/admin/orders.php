<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$pdo = get_pdo();
$tab = ($_GET['tab'] ?? '') === 'cancel' ? 'cancel' : 'all';
$statusFilter = $_GET['status'] ?? '';
// Статус «Ожидает отмены» вынесен в отдельную вкладку, в фильтре его нет.
$filterStatuses = array_values(array_diff(array_keys(STATUS_LABELS), ['CANCEL_REQUESTED']));
$allStatuses = array_keys(STATUS_LABELS);
$pendingCount = pending_cancel_count();

if ($tab === 'cancel') {
    $orders = $pdo->query("SELECT * FROM orders WHERE status = 'CANCEL_REQUESTED' ORDER BY cancel_requested_at ASC, id ASC")->fetchAll();
} elseif (in_array($statusFilter, $filterStatuses, true)) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE status = ? ORDER BY created_at DESC');
    $stmt->execute([$statusFilter]);
    $orders = $stmt->fetchAll();
} else {
    $statusFilter = '';
    $orders = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll();
}

$itemsByOrder = [];
if (!empty($orders)) {
    $ids = array_map('intval', array_column($orders, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders)");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $item) $itemsByOrder[(int) $item['order_id']][] = $item;
}

$pageTitle = 'Заказы — Админка';
require __DIR__ . '/../includes/header.php';
?>

<h1>Админ-панель</h1>
<?= admin_nav('orders') ?>

<div class="pills">
  <a href="/admin/orders.php" class="pill <?= $tab === 'all' && $statusFilter === '' ? 'active' : '' ?>">Все заказы</a>
  <a href="/admin/orders.php?tab=cancel" class="pill pill-warn <?= $tab === 'cancel' ? 'active' : '' ?>">
    Ожидают отмены<?php if ($pendingCount > 0): ?> <span class="pill-count"><?= $pendingCount ?></span><?php endif; ?>
  </a>
  <?php foreach ($filterStatuses as $s): ?>
    <a href="/admin/orders.php?status=<?= $s ?>" class="pill <?= $tab === 'all' && $statusFilter === $s ? 'active' : '' ?>"><?= e(STATUS_LABELS[$s]) ?></a>
  <?php endforeach; ?>
</div>

<?php foreach ($orders as $order): $isPending = $order['status'] === 'CANCEL_REQUESTED'; ?>
  <div class="card mt-2 <?= $isPending ? 'card-warn' : '' ?>" style="margin-bottom:1rem">
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
          <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI']) ?>">
          <select name="status" class="status-badge status-<?= e($order['status']) ?>" style="border:none;cursor:pointer" onchange="this.form.requestSubmit()" aria-label="Статус заказа №<?= (int) $order['id'] ?>">
            <?php foreach ($allStatuses as $s): ?>
              <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= e(STATUS_LABELS[$s]) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <?php if ($isPending): ?>
      <div class="notice notice-warn" style="margin-top:.75rem">
        <strong>Покупатель просит отменить заказ</strong>
        <?php if ($order['cancel_requested_at']): ?>
          <span class="muted">— <?= date('d.m.Y H:i', strtotime($order['cancel_requested_at'])) ?></span>
        <?php endif; ?>
        <p style="margin:.4rem 0 0">
          <span class="muted">Причина:</span>
          <?= $order['cancel_reason'] ? nl2br(e($order['cancel_reason'])) : '<em class="muted">не указана</em>' ?>
        </p>
        <p style="margin:.4rem 0 0">
          <span class="muted">Связаться:</span>
          <a href="tel:+<?= e(preg_replace('/\D+/', '', $order['contact_phone'])) ?>" style="font-weight:600"><?= e($order['contact_phone']) ?></a>
          · <a href="mailto:<?= e($order['contact_email']) ?>" style="font-weight:600"><?= e($order['contact_email']) ?></a>
        </p>
        <p class="muted" style="margin:.4rem 0 0;font-size:.85rem">Согласовав отмену, выберите статус «Отменён» в списке справа вверху.</p>
      </div>
    <?php endif; ?>

    <div style="margin-top:.75rem;border-top:1px solid var(--lagoon-100);padding-top:.75rem;font-size:.9rem">
      <?php foreach ($itemsByOrder[(int) $order['id']] ?? [] as $item): ?>
        <div class="product-card__row" style="gap:.75rem">
          <span><?= e(item_label($item['name'], $item['size'])) ?> × <?= (int) $item['quantity'] ?></span>
          <span style="white-space:nowrap"><?= money($item['quantity'] * (float) $item['price']) ?></span>
        </div>
      <?php endforeach; ?>
      <p class="muted" style="margin:.5rem 0 0">
        <?= e(address_line($order)) ?> · <?= e($order['contact_phone']) ?>
      </p>
    </div>
  </div>
<?php endforeach; ?>

<?php if (empty($orders)): ?>
  <p class="muted"><?= $tab === 'cancel' ? 'Запросов на отмену нет.' : 'Заказов не найдено.' ?></p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
