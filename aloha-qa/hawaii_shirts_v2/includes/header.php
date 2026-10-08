<?php
/** @var string|null $pageTitle */
$pageTitle = $pageTitle ?? 'Aloha Threads';
$user = current_user();
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="stylesheet" href="<?= asset('assets/style.css') ?>">
</head>
<body>
<header class="site-header">
  <div class="site-header__inner">
    <a href="/index.php" class="brand">🌺 Aloha Threads</a>
    <nav class="main-nav">
      <a href="/index.php">Каталог</a>
      <a href="/cart.php" class="cart-link">
        Корзина
        <?php $count = cart_count(); if ($count > 0): ?>
          <span class="badge"><?= $count ?></span>
        <?php endif; ?>
      </a>
      <?php if ($user): ?>
        <a href="/account.php">Профиль</a>
        <?php if ($user['role'] === 'ADMIN'): ?>
          <a href="/admin/products.php">Админка</a>
        <?php endif; ?>
        <form action="/logout.php" method="post" class="inline-form">
          <?= csrf_field() ?>
          <button type="submit" class="link-button">Выйти</button>
        </form>
      <?php else: ?>
        <a href="/login.php">Войти</a>
        <a href="/register.php" class="btn btn-pill btn-sunset">Регистрация</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container">
<?php $flash = flash_get(); if ($flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
