<?php
// Запрос покупателя на отмену заказа. Сам заказ не отменяется: статус становится
// «Ожидает отмены», дальше администратор связывается с покупателем и отменяет вручную.
require_once __DIR__ . '/includes/bootstrap.php';

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function cancel_respond(bool $wantsJson, bool $ok, string $message, int $orderId): never {
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    flash_set($message, $ok ? 'success' : 'error');
    redirect('/order.php?id=' . $orderId);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/account.php');

$user = current_user();
$orderId = (int) ($_POST['order_id'] ?? 0);

if (!$user) {
    if ($wantsJson) { http_response_code(401); cancel_respond(true, false, 'Войдите в аккаунт, чтобы отменить заказ.', $orderId); }
    redirect('/login.php');
}
if (!csrf_valid()) {
    if ($wantsJson) http_response_code(403);
    cancel_respond($wantsJson, false, 'Сессия устарела, обновите страницу и попробуйте снова.', $orderId);
}

$reason = trim(mb_scrub((string) ($_POST['reason'] ?? '')));
$reason = mb_substr($reason, 0, 1000);

$statuses = "'" . implode("','", CANCELLABLE_STATUSES) . "'";
$stmt = get_pdo()->prepare(
    "UPDATE orders SET status = 'CANCEL_REQUESTED', cancel_reason = ?, cancel_requested_at = NOW()
     WHERE id = ? AND user_id = ? AND status IN ($statuses)"
);
$stmt->execute([$reason !== '' ? $reason : null, $orderId, $user['id']]);

if ($stmt->rowCount() === 0) {
    cancel_respond($wantsJson, false, 'Отменить этот заказ уже нельзя: он не найден, уже отправлен или запрос уже отправлен ранее.', $orderId);
}

cancel_respond($wantsJson, true, 'Запрос на отмену отправлен. Администратор свяжется с вами.', $orderId);
