<?php

// Начальное значение поля телефона: префикс страны, который нельзя стереть.
const PHONE_PLACEHOLDER = '+7 ';

/**
 * Приводит телефон к виду «+7 000-000-00-00».
 * Принимает любые разделители; 11 цифр с первой 7 или 8 — это префикс страны.
 * Возвращает null, если после этого не получилось ровно 10 цифр номера.
 */
function normalize_phone(?string $raw): ?string {
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) !== 10) return null;
    return sprintf('+7 %s-%s-%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 2), substr($digits, 8, 2));
}

/** true, если в поле телефона по сути ничего не введено (пусто или только «+7»). */
function phone_is_blank(?string $raw): bool {
    return preg_replace('/\D+/', '', (string) $raw) === '' || preg_replace('/\D+/', '', (string) $raw) === '7';
}

function is_valid_email(string $email): bool {
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Убирает лишнюю приставку, если покупатель сам написал «д. 5» / «кв. 12». */
function clean_house(string $value): string {
    return trim(preg_replace('/^(дом|д)\.?\s*/ui', '', trim($value)));
}

function clean_apartment(string $value): string {
    return trim(preg_replace('/^(квартира|кв)\.?\s*/ui', '', trim($value)));
}

/**
 * Проверка данных оформления заказа. Все поля обязательны.
 * Индекс только проверяется на заполненность — формат не проверяется.
 * Возвращает [очищенные значения, ошибки по полям].
 */
function validate_checkout(array $in): array {
    $v = [
        'name'      => trim((string) ($in['name'] ?? '')),
        'email'     => trim((string) ($in['email'] ?? '')),
        'phone'     => trim((string) ($in['phone'] ?? '')),
        'country'   => trim((string) ($in['country'] ?? '')),
        'city'      => trim((string) ($in['city'] ?? '')),
        'street'    => trim((string) ($in['street'] ?? '')),
        'house'     => clean_house((string) ($in['house'] ?? '')),
        'apartment' => clean_apartment((string) ($in['apartment'] ?? '')),
        'zip'       => trim((string) ($in['zip'] ?? '')),
    ];
    $errors = [];

    if ($v['name'] === '') $errors['name'] = 'Укажите имя и фамилию';

    if ($v['email'] === '') $errors['email'] = 'Укажите email';
    elseif (!is_valid_email($v['email'])) $errors['email'] = 'Введите корректный email, например name@example.ru';

    if (phone_is_blank($v['phone'])) {
        $errors['phone'] = 'Укажите телефон';
    } else {
        $phone = normalize_phone($v['phone']);
        if ($phone === null) $errors['phone'] = 'Введите телефон в формате +7 000-000-00-00';
        else $v['phone'] = $phone;
    }

    $required = ['country' => 'страну', 'city' => 'город', 'street' => 'улицу', 'house' => 'дом', 'apartment' => 'квартиру или офис', 'zip' => 'индекс'];
    foreach ($required as $field => $what) {
        if ($v[$field] === '') $errors[$field] = 'Укажите ' . $what;
    }

    return [$v, $errors];
}

function field_error(array $errors, string $field): string {
    return isset($errors[$field]) ? '<p class="error-text">' . e($errors[$field]) . '</p>' : '';
}
