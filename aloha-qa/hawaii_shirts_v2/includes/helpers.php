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

function csrf_valid(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrf_check(): void {
    if (!csrf_valid()) {
        http_response_code(403);
        die('Сессия устарела, обновите страницу и попробуйте снова.');
    }
}

/** Допускает только путь внутри сайта («/cart.php»), иначе возвращает запасной. */
function safe_redirect_path(?string $path, string $fallback = '/index.php'): string {
    if (is_string($path) && preg_match('#^/(?![/\\\\])#', $path) && !preg_match('/[\r\n]/', $path)) {
        return $path;
    }
    return $fallback;
}

const STATUS_LABELS = [
    'PAID' => 'Оплачен',
    'PROCESSING' => 'В обработке',
    'SHIPPED' => 'Отправлен',
    'DELIVERED' => 'Доставлен',
    'CANCEL_REQUESTED' => 'Ожидает отмены',
    'CANCELLED' => 'Отменён',
];

const STATUS_ORDER = ['PAID', 'PROCESSING', 'SHIPPED', 'DELIVERED'];

// Покупатель может запросить отмену, пока заказ не отправлен.
const CANCELLABLE_STATUSES = ['PAID', 'PROCESSING'];

/** Единый блок «− число +» для страницы товара, карточек и корзины. */
function qty_stepper(int $value, int $max, string $name = 'quantity'): string {
    $max = max(1, $max);
    $value = max(1, min($value, $max));
    return '<div class="qty-control" data-qty>'
        . '<button type="button" data-qty-step="-1" aria-label="Уменьшить количество">−</button>'
        . '<input type="number" name="' . e($name) . '" value="' . $value . '" min="1" max="' . $max . '" inputmode="numeric" data-qty-input aria-label="Количество">'
        . '<button type="button" data-qty-step="1" aria-label="Увеличить количество">+</button>'
        . '</div>';
}

/** Адрес доставки одной строкой (поддерживает и старые заказы без дома/квартиры). */
function address_line(array $order): string {
    $parts = [$order['shipping_country'], $order['shipping_city'], $order['shipping_street']];
    if (($order['shipping_house'] ?? '') !== '') $parts[] = 'д. ' . $order['shipping_house'];
    $apt = $order['shipping_apartment'] ?? '';
    if ($apt !== '') $parts[] = preg_match('/^\d/', $apt) ? 'кв. ' . $apt : $apt;
    $parts[] = $order['shipping_zip'];
    return implode(', ', $parts);
}

/**
 * Поле формы с подписью и сообщением об ошибке под ним.
 * $attrs: html-атрибуты input; значение true выводит атрибут без значения.
 */
function form_field(string $label, string $name, string $value, array $errors = [], array $attrs = []): string {
    $attrs += ['type' => 'text', 'required' => true];
    $id = 'f-' . $name;
    $html = '<div class="field"><label for="' . e($id) . '">' . e($label) . '</label>'
        . '<input id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '"';
    foreach ($attrs as $attr => $val) {
        if ($val === true) $html .= ' ' . $attr;
        elseif ($val !== false && $val !== null) $html .= ' ' . $attr . '="' . e((string) $val) . '"';
    }
    if (isset($errors[$name])) $html .= ' aria-invalid="true"';
    return $html . '>' . field_error($errors, $name) . '</div>';
}

function phone_field_attrs(): array {
    return [
        'type' => 'tel',
        'data-phone' => true,
        'placeholder' => '+7 000-000-00-00',
        'title' => 'Формат: +7 000-000-00-00',
        'autocomplete' => 'tel',
        'inputmode' => 'tel',
        'maxlength' => 16,
    ];
}

function pending_cancel_count(): int {
    return (int) get_pdo()->query("SELECT COUNT(*) FROM orders WHERE status = 'CANCEL_REQUESTED'")->fetchColumn();
}

/** Верхняя навигация админки: Товары / Заказы (с числом запросов на отмену). */
function admin_nav(string $active): string {
    $pending = pending_cancel_count();
    $html = '<div class="pills">';
    $html .= '<a href="/admin/products.php" class="pill' . ($active === 'products' ? ' active' : '') . '">Товары</a>';
    $html .= '<a href="/admin/orders.php" class="pill' . ($active === 'orders' ? ' active' : '') . '">Заказы'
        . ($pending > 0 ? ' <span class="pill-count">' . $pending . '</span>' : '') . '</a>';
    return $html . '</div>';
}

/** Ссылка на файл из assets/ с отметкой времени — чтобы браузер не держал старый кеш. */
function asset(string $path): string {
    $file = __DIR__ . '/../' . ltrim($path, '/');
    return '/' . ltrim($path, '/') . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function flash_set(string $message, string $type = 'error'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_get(): ?array {
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
