<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) redirect('/account.php');

$error = '';
$email = '';
$redirectTo = $_GET['redirect'] ?? $_POST['redirect'] ?? '/account.php';
if (!str_starts_with($redirectTo, '/')) $redirectTo = '/account.php'; // защита от открытого редиректа

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = login_user($email, $password);
    if ($result['ok']) {
        cart_merge_into_user($_SESSION['user_id']);
        $user = current_user();
        redirect($user['role'] === 'ADMIN' ? '/admin/products.php' : $redirectTo);
    }
    $error = $result['error'];
}

$pageTitle = 'Вход — Aloha Threads';
require __DIR__ . '/includes/header.php';
?>

<div style="max-width:24rem;margin:2rem auto">
  <h1 style="text-align:center">Вход</h1>
  <form action="/login.php" method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
    <input type="email" name="email" placeholder="Email" value="<?= e($email) ?>" required>
    <input type="password" name="password" placeholder="Пароль" required>
    <?php if ($error): ?><p class="error-text"><?= e($error) ?></p><?php endif; ?>
    <button type="submit" class="btn btn-lagoon" style="width:100%">Войти</button>
  </form>
  <p class="muted" style="text-align:center;margin-top:1rem">
    Нет аккаунта? <a href="/register.php" style="color:var(--lagoon-700);font-weight:600">Зарегистрироваться</a>
  </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
