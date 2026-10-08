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

$status = $order['status'];
$showTracker = in_array($status, STATUS_ORDER, true);
$currentIndex = array_search($status, STATUS_ORDER, true);
$canRequestCancel = $isOwner && in_array($status, CANCELLABLE_STATUSES, true);

$pageTitle = 'Заказ №' . $id . ' — Aloha Threads';
require __DIR__ . '/includes/header.php';
?>

<a href="/account.php" class="muted">← Мои заказы</a>
<h1 style="margin-bottom:.25rem">Заказ №<?= (int) $order['id'] ?></h1>
<p class="muted"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>

<?php if ($showTracker): ?>
  <div class="tracker">
    <?php foreach (STATUS_ORDER as $idx => $s): ?>
      <div class="tracker__step">
        <div class="tracker__circle <?= $idx <= $currentIndex ? 'done' : '' ?>"><?= $idx + 1 ?></div>
        <div class="tracker__label"><?= e(STATUS_LABELS[$s]) ?></div>
      </div>
      <?php if ($idx < count(STATUS_ORDER) - 1): ?>
        <div class="tracker__line <?= $idx < $currentIndex ? 'done' : '' ?>"></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php elseif ($status === 'CANCEL_REQUESTED'): ?>
  <div class="notice notice-warn mt-2">
    <strong>Запрос на отмену отправлен.</strong> Администратор свяжется с вами, чтобы подтвердить отмену.
    <?php if ($order['cancel_requested_at']): ?>
      <span class="muted"> Запрос от <?= date('d.m.Y H:i', strtotime($order['cancel_requested_at'])) ?>.</span>
    <?php endif; ?>
    <?php if ($order['cancel_reason']): ?>
      <p style="margin:.5rem 0 0"><span class="muted">Причина:</span> <?= nl2br(e($order['cancel_reason'])) ?></p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <span class="status-badge status-CANCELLED mt-2" style="display:inline-block">Заказ отменён</span>
<?php endif; ?>

<div class="card mt-2">
  <?php foreach ($items as $item): ?>
    <div class="product-card__row" style="margin-bottom:.5rem;font-size:.9rem;gap:.75rem">
      <span><?= e(item_label($item['name'], $item['size'])) ?> × <?= (int) $item['quantity'] ?></span>
      <span style="white-space:nowrap"><?= money($item['quantity'] * (float) $item['price']) ?></span>
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
  <p style="margin:.25rem 0 0"><?= e(address_line($order)) ?></p>
</div>

<?php if ($canRequestCancel): ?>
  <div class="mt-2">
    <button type="button" class="btn btn-outline btn-danger-outline" data-open-dialog="cancel-dialog">Отменить заказ</button>
  </div>

  <dialog id="cancel-dialog" class="modal" data-reload-on-close>
    <form action="/order_cancel.php" method="post" id="cancel-form" data-cancel-form>
      <?= csrf_field() ?>
      <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">

      <div data-cancel-step="form">
        <h2 style="margin-top:0">Отменить заказ №<?= (int) $order['id'] ?>?</h2>
        <p class="muted">Администратор свяжется с вами, подтвердит и отменит заказ.</p>
        <label for="cancel-reason">Причина отмены <span class="muted">(необязательно)</span></label>
        <textarea id="cancel-reason" name="reason" rows="3" maxlength="1000" placeholder="Например: передумал(а), ошибся размером…"></textarea>
        <p class="error-text" data-cancel-error hidden></p>
        <div class="actions" style="justify-content:flex-end">
          <button type="button" class="btn btn-outline btn-sm" data-close-dialog>Назад</button>
          <button type="submit" class="btn btn-danger btn-sm">Отменить заказ</button>
        </div>
      </div>

      <div data-cancel-step="done" hidden>
        <h2 style="margin-top:0">Запрос отправлен</h2>
        <p>Мы получили запрос на отмену заказа №<?= (int) $order['id'] ?>. Статус заказа — «Ожидает отмены». Администратор свяжется с вами в ближайшее время.</p>
        <div class="actions" style="justify-content:flex-end">
          <button type="button" class="btn btn-lagoon btn-sm" data-close-dialog>Закрыть</button>
        </div>
      </div>
    </form>
  </dialog>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
