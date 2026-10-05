<?php

function current_user(): ?array {
    // Кешируем по id из сессии, а не один раз на весь запрос: сразу после
    // login_user()/logout_user() $_SESSION['user_id'] меняется в том же
    // запросе, и результат должен обновиться, а не остаться от старого вызова.
    static $cachedId = null;
    static $cachedUser = null;

    $sessionId = $_SESSION['user_id'] ?? null;
    if ($sessionId !== $cachedId) {
        $cachedId = $sessionId;
        $cachedUser = null;
        if ($sessionId) {
            $stmt = get_pdo()->prepare('SELECT id, email, name, phone, role FROM users WHERE id = ?');
            $stmt->execute([$sessionId]);
            $found = $stmt->fetch();
            $cachedUser = $found ?: null;
        }
    }
    return $cachedUser;
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        redirect('/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    return $user;
}

function require_admin(): array {
    $user = require_login();
    if ($user['role'] !== 'ADMIN') {
        redirect('/index.php');
    }
    return $user;
}

function register_user(string $email, string $password, string $name, ?string $phone): array {
    if ($email === '' || $password === '' || $name === '') {
        return ['ok' => false, 'error' => 'Заполните имя, email и пароль'];
    }
    if (strlen($password) < 6) {
        return ['ok' => false, 'error' => 'Пароль должен быть не короче 6 символов'];
    }
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'error' => 'Пользователь с таким email уже существует'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name, phone) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $hash, $name, $phone ?: null]);
    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    return ['ok' => true];
}

function login_user(string $email, string $password): array {
    $stmt = get_pdo()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Неверный email или пароль'];
    }
    $_SESSION['user_id'] = (int) $user['id'];
    return ['ok' => true];
}

function logout_user(): void {
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}

function change_password(int $userId, string $currentPassword, string $newPassword): array {
    if (strlen($newPassword) < 6) {
        return ['ok' => false, 'error' => 'Новый пароль должен быть не короче 6 символов'];
    }
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        return ['ok' => false, 'error' => 'Текущий пароль неверен'];
    }
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$hash, $userId]);
    return ['ok' => true];
}
