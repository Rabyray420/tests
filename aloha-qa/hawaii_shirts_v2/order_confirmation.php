<?php
require_once __DIR__ . '/includes/bootstrap.php';

$order = $_SESSION['last_order'] ?? null;
unset($_SESSION['last_order']);

if (!$order) redirect('/index.php');

$user = current_user();
$pageTitle = 'Заказ оформлен — Aloha Threads';
require __DIR__ . '/includes/header.php';
?>

<div style="max-width:36rem;margin:0 auto;text-align:center;padding:2rem 0">
  <p style="font-size:3rem;margin:0">🌴</p>
  <h1>Заказ №<?= (int) $order['id'] ?> оформлен!</h1>
  <p class="muted">
    Статус: <strong><?= e(STATUS_LABELS[$order['status']]) ?></strong>.
    Мы отправили подтверждение на <?= e($order['contact_email']) ?>.
  </p>

  <div class="card" style="text-align:left;margin:1.5rem 0">
    <?php foreach ($order['items'] as $item): ?>
      <div class="product-card__row" style="margin-bottom:.5rem;font-size:.9rem">
        <span><?= e($item['name']) ?> × <?= (int) $item['quantity'] ?></span>
        <span><?= money($item['quantity'] * $item['price']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="product-card__row" style="border-top:1px solid var(--lagoon-200);padding-top:.75rem;font-weight:600">
      <span>Итого</span>
      <span><?= money($order['total']) ?></span>
    </div>
  </div>

  <div class="actions" style="justify-content:center">
    <a href="/index.php" style="color:var(--lagoon-700);font-weight:600">Вернуться в каталог</a>
    <?php if ($user): ?>
      <a href="/account.php" class="btn btn-lagoon">Мои заказы</a>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
