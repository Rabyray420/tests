<?php

// Возвращает корзину в едином виде независимо от того, гость это или
// авторизованный пользователь: [{ product_id, quantity, product }, ...]
function cart_items(): array {
    $user = current_user();
    $pdo = get_pdo();

    if ($user) {
        $stmt = $pdo->prepare(
            'SELECT ci.product_id AS cart_product_id, ci.quantity AS cart_quantity, p.*
             FROM cart_items ci JOIN products p ON p.id = ci.product_id
             WHERE ci.user_id = ?'
        );
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll();
    } else {
        $sessionCart = $_SESSION['cart'] ?? [];
        if (empty($sessionCart)) return [];
        $ids = array_map('intval', array_keys($sessionCart));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $rows = [];
        foreach ($stmt->fetchAll() as $p) {
            $p['cart_product_id'] = (int) $p['id'];
            $p['cart_quantity'] = (int) $sessionCart[(string) $p['id']];
            $rows[] = $p;
        }
    }

    $items = [];
    foreach ($rows as $row) {
        $productId = (int) $row['cart_product_id'];
        $quantity = (int) $row['cart_quantity'];
        unset($row['cart_product_id'], $row['cart_quantity']);
        $items[] = ['product_id' => $productId, 'quantity' => $quantity, 'product' => $row];
    }
    return $items;
}

function cart_count(): int {
    $count = 0;
    foreach (cart_items() as $item) $count += $item['quantity'];
    return $count;
}

function cart_total(): float {
    $total = 0.0;
    foreach (cart_items() as $item) $total += $item['quantity'] * (float) $item['product']['price'];
    return $total;
}

function cart_set_quantity(int $productId, int $quantity): void {
    $user = current_user();
    if ($quantity < 1) {
        cart_remove($productId);
        return;
    }
    if ($user) {
        $stmt = get_pdo()->prepare(
            'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
        );
        $stmt->execute([$user['id'], $productId, $quantity]);
    } else {
        $_SESSION['cart'][(string) $productId] = $quantity;
    }
}

function cart_add(int $productId, int $quantity): void {
    $current = 0;
    foreach (cart_items() as $item) {
        if ($item['product_id'] === $productId) { $current = $item['quantity']; break; }
    }
    cart_set_quantity($productId, $current + $quantity);
}

function cart_remove(int $productId): void {
    $user = current_user();
    if ($user) {
        $stmt = get_pdo()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$user['id'], $productId]);
    } else {
        unset($_SESSION['cart'][(string) $productId]);
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
    $sessionCart = $_SESSION['cart'] ?? [];
    if (empty($sessionCart)) return;
    $pdo = get_pdo();
    foreach ($sessionCart as $productId => $quantity) {
        $stmt = $pdo->prepare('SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$userId, $productId]);
        $existing = $stmt->fetch();
        $newQuantity = $quantity + ($existing ? (int) $existing['quantity'] : 0);
        $stmt = $pdo->prepare(
            'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
        );
        $stmt->execute([$userId, $productId, $newQuantity]);
    }
    $_SESSION['cart'] = [];
}
