<?php

// Размеры, которые можно выбрать в админке для товара.
const SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

// Размерная сетка (мужские рубашки, см). Цифры ориентировочные — сверьте
// с реальными замерами ваших изделий и при необходимости поправьте здесь.
const SIZE_CHART = [
    'XS'  => ['ru' => '44', 'chest' => '84–88',   'waist' => '70–74'],
    'S'   => ['ru' => '46', 'chest' => '88–92',   'waist' => '74–78'],
    'M'   => ['ru' => '48', 'chest' => '92–96',   'waist' => '78–82'],
    'L'   => ['ru' => '50', 'chest' => '96–100',  'waist' => '82–86'],
    'XL'  => ['ru' => '52', 'chest' => '100–104', 'waist' => '86–90'],
    'XXL' => ['ru' => '54', 'chest' => '104–108', 'waist' => '90–94'],
];

/** Остатки по размерам одного товара: ['M' => 5, 'L' => 0], в порядке SIZES. */
function product_sizes(PDO $pdo, int $productId): array {
    return sizes_for_products($pdo, [$productId])[$productId] ?? [];
}

/** Остатки по размерам для набора товаров: [productId => ['M' => 5, ...]]. */
function sizes_for_products(PDO $pdo, array $productIds): array {
    $result = [];
    if (empty($productIds)) return $result;
    $ids = array_values(array_map('intval', $productIds));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT product_id, size, stock FROM product_sizes WHERE product_id IN ($placeholders)");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $result[(int) $row['product_id']][$row['size']] = (int) $row['stock'];
    }
    foreach ($result as $id => $sizes) {
        $ordered = [];
        foreach (SIZES as $s) {
            if (isset($sizes[$s])) $ordered[$s] = $sizes[$s];
        }
        $result[$id] = $ordered;
    }
    return $result;
}

/**
 * Проверяет сочетание «товар + размер» и возвращает активный товар с доступным
 * остатком, либо null, если такого сочетания купить нельзя (товар скрыт/удалён,
 * у товара есть размеры, а размер не выбран или не существует, и т.п.).
 */
function resolve_stock(PDO $pdo, int $productId, string $size): ?array {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND active = 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) return null;

    $sizes = product_sizes($pdo, $productId);
    if (empty($sizes)) {
        if ($size !== '') return null;
        return ['product' => $product, 'available' => max(0, (int) $product['stock'])];
    }
    if ($size === '' || !isset($sizes[$size])) return null;
    return ['product' => $product, 'available' => max(0, $sizes[$size])];
}

/** Понятное сообщение, почему сочетание «товар + размер» купить нельзя. */
function stock_error_message(PDO $pdo, int $productId, string $size): string {
    $stmt = $pdo->prepare('SELECT id FROM products WHERE id = ? AND active = 1');
    $stmt->execute([$productId]);
    if (!$stmt->fetch()) return 'Товар недоступен.';
    if ($size === '' && !empty(product_sizes($pdo, $productId))) return 'Выберите размер.';
    return 'Выбранный размер недоступен.';
}

function item_label(string $name, string $size): string {
    return $size === '' ? $name : $name . ' (' . $size . ')';
}
