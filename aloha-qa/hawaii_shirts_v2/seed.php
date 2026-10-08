<?php
// Одноразовый скрипт: создаёт администратора и демо-товары, если их ещё нет.
// Запустите один раз после импорта db.sql — либо командой `php seed.php` по SSH,
// либо открыв этот файл в браузере. После успешного запуска удалите его с сервера
// (или он безопасно ничего не сделает при повторном запуске).
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

$pdo = get_pdo();

$adminEmail = 'admin@hawaii-shirts.test';
// Пароль админа берётся из переменной окружения ADMIN_PASSWORD.
// Если её нет — генерируется случайный и показывается один раз в выводе скрипта.
$adminPassword = getenv('ADMIN_PASSWORD') ?: bin2hex(random_bytes(8));
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$adminEmail]);
if (!$stmt->fetch()) {
    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (email, password_hash, name, role) VALUES (?, ?, ?, ?)')
        ->execute([$adminEmail, $hash, 'Admin', 'ADMIN']);
    echo "Создан администратор: $adminEmail / $adminPassword\n";
} else {
    echo "Администратор уже существует, пропускаю.\n";
}

$count = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
if ($count === 0) {
    $products = [
        ['Sunset Aloha', 'Рубашка с закатным градиентом и силуэтами пальм. Свободный крой, лёгкая вискоза.', 3490, 25, 'Классические'],
        ['Palm Breeze', 'Тропический принт с пальмовыми листьями на насыщенном зелёном фоне.', 3290, 30, 'Классические'],
        ['Hibiscus Bloom', 'Яркие гавайские цветы хибискуса на глубоком коралловом фоне.', 3590, 18, 'Яркие'],
        ['Ocean Wave', 'Волнообразный принт в бирюзовых тонах — привет с побережья Вайкики.', 3390, 22, 'Классические'],
        ['Pineapple Punch', 'Весёлый принт с ананасами на солнечно-жёлтом фоне.', 3190, 15, 'Яркие'],
        ['Tropical Night', 'Тёмная база с мелким тропическим узором — универсальный вариант на вечер.', 3690, 12, 'Премиум'],
        ['Coral Reef', 'Коралловый принт с рыбками и кораллами, лёгкая дышащая ткань.', 3450, 20, 'Яркие'],
        ['Golden Aloha', 'Премиальная рубашка с золотистым принтом пальм на чёрном фоне.', 4290, 8, 'Премиум'],
    ];
    $stmt = $pdo->prepare('INSERT INTO products (name, description, price, stock, category) VALUES (?, ?, ?, ?, ?)');
    $sizeStmt = $pdo->prepare('INSERT INTO product_sizes (product_id, size, stock) VALUES (?, ?, ?)');
    foreach ($products as $p) {
        $stmt->execute($p);
        $productId = (int) $pdo->lastInsertId();
        // Раскладываем общий остаток по размерам S–XL.
        $total = (int) $p[3];
        $s = (int) round($total * 0.2);
        $m = (int) round($total * 0.35);
        $l = (int) round($total * 0.3);
        $xl = $total - $s - $m - $l;
        foreach (['S' => $s, 'M' => $m, 'L' => $l, 'XL' => $xl] as $size => $qty) {
            $sizeStmt->execute([$productId, $size, $qty]);
        }
    }
    echo 'Добавлено товаров: ' . count($products) . "\n";
} else {
    echo "Товары уже есть в базе, пропускаю.\n";
}

echo "\nГотово. Теперь удалите seed.php с сервера — он больше не нужен.\n";
