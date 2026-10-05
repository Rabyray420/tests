<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) redirect('/account.php');

$error = '';
$name = $email = $phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = register_user($email, $password, $name, $phone ?: null);
    if ($result['ok']) {
        cart_merge_into_user($_SESSION['user_id']);
        redirect('/account.php');
    }
    $error = $result['error'];
}

$pageTitle = 'Регистрация — Aloha Threads';
require __DIR__ . '/includes/header.php';
?>

<div style="max-width:24rem;margin:2rem auto">
  <h1 style="text-align:center">Регистрация</h1>
  <form action="/register.php" method="post" class="card">
    <?= csrf_field() ?>
    <input type="text" name="name" placeholder="Имя" value="<?= e($name) ?>" required>
    <input type="email" name="email" placeholder="Email" value="<?= e($email) ?>" required>
    <input type="tel" name="phone" placeholder="Телефон (необязательно)" value="<?= e($phone) ?>">
    <input type="password" name="password" placeholder="Пароль (от 6 символов)" required>
    <?php if ($error): ?><p class="error-text"><?= e($error) ?></p><?php endif; ?>
    <button type="submit" class="btn btn-lagoon" style="width:100%">Зарегистрироваться</button>
  </form>
  <p class="muted" style="text-align:center;margin-top:1rem">
    Уже есть аккаунт? <a href="/login.php" style="color:var(--lagoon-700);font-weight:600">Войти</a>
  </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
