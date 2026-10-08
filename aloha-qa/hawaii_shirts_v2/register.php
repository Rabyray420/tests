<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) redirect('/account.php');

$errors = [];
$name = $email = $phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '') $errors['name'] = 'Укажите имя';

    if ($email === '') $errors['email'] = 'Укажите email';
    elseif (!is_valid_email($email)) $errors['email'] = 'Введите корректный email, например name@example.ru';

    // Телефон при регистрации необязателен, но если введён — должен быть полным.
    $normalizedPhone = null;
    if (!phone_is_blank($phone)) {
        $normalizedPhone = normalize_phone($phone);
        if ($normalizedPhone === null) $errors['phone'] = 'Введите телефон в формате +7 000-000-00-00';
        else $phone = $normalizedPhone;
    } else {
        $phone = '';
    }

    if ($password === '') $errors['password'] = 'Укажите пароль';
    elseif (strlen($password) < 6) $errors['password'] = 'Пароль должен быть не короче 6 символов';

    if (empty($errors)) {
        $result = register_user($email, $password, $name, $normalizedPhone);
        if ($result['ok']) {
            cart_merge_into_user($_SESSION['user_id']);
            redirect('/account.php');
        }
        $errors['email'] = $result['error'];
    }
}

$pageTitle = 'Регистрация — Aloha Threads';
require __DIR__ . '/includes/header.php';
?>

<div style="max-width:24rem;margin:2rem auto">
  <h1 style="text-align:center">Регистрация</h1>
  <form action="/register.php" method="post" class="card">
    <?= csrf_field() ?>
    <?= form_field('Имя', 'name', $name, $errors, ['autocomplete' => 'name']) ?>
    <?= form_field('Email', 'email', $email, $errors, ['type' => 'email', 'autocomplete' => 'email', 'placeholder' => 'name@example.ru']) ?>
    <?= form_field('Телефон (необязательно)', 'phone', $phone === '' ? PHONE_PLACEHOLDER : $phone, $errors, ['required' => false] + phone_field_attrs()) ?>
    <?= form_field('Пароль (от 6 символов)', 'password', '', $errors, ['type' => 'password', 'autocomplete' => 'new-password']) ?>
    <button type="submit" class="btn btn-lagoon" style="width:100%">Зарегистрироваться</button>
  </form>
  <p class="muted" style="text-align:center;margin-top:1rem">
    Уже есть аккаунт? <a href="/login.php" style="color:var(--lagoon-700);font-weight:600">Войти</a>
  </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
