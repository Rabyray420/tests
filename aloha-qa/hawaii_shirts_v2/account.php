<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$passwordError = '';
$passwordSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    csrf_check();
    $result = change_password($user['id'], $_POST['current_password'] ?? '', $_POST['new_password'] ?? '');
    if (($_POST['new_password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
        $passwordError = 'Новые пароли не совпадают';
    } elseif ($result['ok']) {
        $passwordSuccess = true;
    } else {
        $passwordError = $result['error'];
    }
}

$stmt = get_pdo()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$pageTitle = 'Мой профиль — Aloha Threads';
require __DIR__ . '/includes/header.php';
?>

<h1 style="margin-bottom:.25rem">Мой профиль</h1>
<p class="muted"><?= e($user['name']) ?> · <?= e($user['email']) ?></p>

<h2>История заказов</h2>
<?php if (empty($orders)): ?>
  <p class="muted">Заказов пока нет.</p>
<?php endif; ?>

<?php foreach ($orders as $order): ?>
  <a href="/order.php?id=<?= (int) $order['id'] ?>" class="cart-row" style="text-decoration:none">
    <div style="flex:1">
      <p style="font-weight:600;margin:0">Заказ №<?= (int) $order['id'] ?></p>
      <p class="muted" style="margin:.2rem 0 0;font-size:.85rem"><?= date('d.m.Y', strtotime($order['created_at'])) ?></p>
    </div>
    <div class="text-right">
      <p style="font-weight:600;margin:0"><?= money((float) $order['total']) ?></p>
      <span class="status-badge status-<?= e($order['status']) ?>"><?= e(STATUS_LABELS[$order['status']]) ?></span>
    </div>
  </a>
<?php endforeach; ?>

<div class="card mt-2" style="max-width:24rem">
  <h2 style="margin-top:0">Сменить пароль</h2>
  <form action="/account.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <input type="password" name="current_password" placeholder="Текущий пароль" required>
    <input type="password" name="new_password" placeholder="Новый пароль (от 6 символов)" required>
    <input type="password" name="confirm_password" placeholder="Повторите новый пароль" required>
    <?php if ($passwordError): ?><p class="error-text"><?= e($passwordError) ?></p><?php endif; ?>
    <?php if ($passwordSuccess): ?><p style="color:var(--green-700);font-size:.85rem;margin:-.4rem 0 .75rem">Пароль изменён.</p><?php endif; ?>
    <button type="submit" class="btn btn-lagoon">Сохранить пароль</button>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
