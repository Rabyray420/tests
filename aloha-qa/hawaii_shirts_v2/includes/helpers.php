<?php

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string {
    return number_format($amount, 0, ',', ' ') . ' ₽';
}

function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Сессия устарела, обновите страницу и попробуйте снова.');
    }
}

const STATUS_LABELS = [
    'PAID' => 'Оплачен',
    'PROCESSING' => 'В обработке',
    'SHIPPED' => 'Отправлен',
    'DELIVERED' => 'Доставлен',
    'CANCELLED' => 'Отменён',
];

const STATUS_ORDER = ['PAID', 'PROCESSING', 'SHIPPED', 'DELIVERED'];

function flash_set(string $message, string $type = 'error'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_get(): ?array {
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
