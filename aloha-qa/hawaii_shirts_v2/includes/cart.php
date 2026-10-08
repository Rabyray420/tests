<?php

// Строка корзины однозначно определяется парой «товар + размер».
// У товаров без размеров size = ''.

function cart_session_lines(): array {
    $lines = [];
    foreach (($_SESSION['cart'] ?? []) as $key => $quantity) {
        // Ключ вида "12:M"; старый формат без размера ("12") тоже поддерживаем.
        $parts = explode(':', (string) $key, 2);
        $lines[] = ['product_id' => (int) $parts[0], 'size' => $parts[1] ?? '', 'quantity' => (int) $quantity];
    }
    return $lines;
}

/**
 * Возвращает корзину в едином виде независимо от того, гость это или
 * авторизованный пользователь:
 * [{ product_id, size, quantity, product, available }, ...]
 * available — сколько единиц этого товара/размера реально можно купить сейчас.
 */
function cart_items(): array {
    $user = current_user();
    $pdo = get_pdo();

    if ($user) {
        $stmt = $pdo->prepare('SELECT product_id, size, quantity FROM cart_items WHERE user_id = ? ORDER BY id');
        $stmt->execute([$user['id']]);
        $lines = $stmt->fetchAll();
    } else {
        $lines = cart_session_lines();
    }
    if (empty($lines)) return [];

    $ids = array_values(array_unique(array_map(fn($l) => (int) $l['product_id'], $lines)));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $products = [];
    foreach ($stmt->fetchAll() as $p) $products[(int) $p['id']] = $p;
    $sizeMap = sizes_for_products($pdo, $ids);

    $items = [];
    foreach ($lines as $line) {
        $pid = (int) $line['product_id'];
        $size = (string) $line['size'];
        if (!isset($products[$pid])) continue;
        $product = $products[$pid];
        $sizes = $sizeMap[$pid] ?? [];

        if (empty($sizes)) {
            $available = $size === '' ? (int) $product['stock'] : 0;
        } else {
            $available = ($size !== '' && isset($sizes[$size])) ? $sizes[$size] : 0;
        }
        if (!(int) $product['active']) $available = 0;

        $items[] = [
            'product_id' => $pid,
            'size' => $size,
            'quantity' => (int) $line['quantity'],
            'product' => $product,
            'available' => max(0, $available),
        ];
    }
    return $items;
}

function cart_count(): int {
    $user = current_user();
    if ($user) {
        $stmt = get_pdo()->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        return (int) $stmt->fetchColumn();
    }
    return (int) array_sum($_SESSION['cart'] ?? []);
}

function cart_total(): float {
    $total = 0.0;
    foreach (cart_items() as $item) $total += $item['quantity'] * (float) $item['product']['price'];
    return $total;
}

function cart_quantity_of(int $productId, string $size): int {
    foreach (cart_items() as $item) {
        if ($item['product_id'] === $productId && $item['size'] === $size) return $item['quantity'];
    }
    return 0;
}

function cart_store(int $productId, string $size, int $quantity): void {
    $user = current_user();
    if ($user) {
        $stmt = get_pdo()->prepare(
            'INSERT INTO cart_items (user_id, product_id, size, quantity) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
        );
        $stmt->execute([$user['id'], $productId, $size, $quantity]);
    } else {
        $_SESSION['cart'][$productId . ':' . $size] = $quantity;
        unset($_SESSION['cart'][(string) $productId]); // старый формат ключа
    }
}

/**
 * Задаёт количество строки корзины. Количество не может быть меньше 1 и больше
 * доступного остатка; убрать строку можно только через cart_remove().
 * Возвращает итоговое количество (0 — если купить этот вариант сейчас нельзя).
 */
function cart_set_quantity(int $productId, string $size, int $quantity): int {
    $info = resolve_stock(get_pdo(), $productId, $size);
    if (!$info || $info['available'] < 1) {
        cart_remove($productId, $size);
        return 0;
    }
    $quantity = max(1, min($quantity, $info['available']));
    cart_store($productId, $size, $quantity);
    return $quantity;
}

/** Добавляет товар в корзину. Возвращает ['ok' => bool, 'error' => ?, 'clamped' => bool]. */
function cart_add(int $productId, string $size, int $quantity): array {
    $pdo = get_pdo();
    $info = resolve_stock($pdo, $productId, $size);
    if (!$info) return ['ok' => false, 'error' => stock_error_message($pdo, $productId, $size)];
    if ($info['available'] < 1) return ['ok' => false, 'error' => 'Этого товара нет в наличии.'];

    $wanted = cart_quantity_of($productId, $size) + max(1, $quantity);
    $final = min($wanted, $info['available']);
    cart_store($productId, $size, $final);
    return ['ok' => true, 'clamped' => $wanted > $final, 'quantity' => $final];
}

function cart_remove(int $productId, string $size): void {
    $user = current_user();
    if ($user) {
        $stmt = get_pdo()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ? AND size = ?');
        $stmt->execute([$user['id'], $productId, $size]);
    } else {
        unset($_SESSION['cart'][$productId . ':' . $size], $_SESSION['cart'][(string) $productId]);
    }
}

function cart_clear(): void {
    $user = current_user();
    if ($user) {
        $stmt = get_pdo()->prepare('DELETE FROM cart_items WHERE user_id = ?');
        $stmt->execute([$user['id']]);
    }
    $_SESSION['cart'] = [];
}

// Вызывается сразу после входа/регистрации: переносит гостевую корзину из
// сессии в корзину пользователя на сервере.
function cart_merge_into_user(int $userId): void {
    $lines = cart_session_lines();
    if (empty($lines)) return;
    $pdo = get_pdo();
    foreach ($lines as $line) {
        $stmt = $pdo->prepare('SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ? AND size = ?');
        $stmt->execute([$userId, $line['product_id'], $line['size']]);
        $existing = $stmt->fetchColumn();
        $info = resolve_stock($pdo, $line['product_id'], $line['size']);
        if (!$info || $info['available'] < 1) continue;
        $quantity = min($line['quantity'] + (int) $existing, $info['available']);
        $stmt = $pdo->prepare(
            'INSERT INTO cart_items (user_id, product_id, size, quantity) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
        );
        $stmt->execute([$userId, $line['product_id'], $line['size'], $quantity]);
    }
    $_SESSION['cart'] = [];
}
