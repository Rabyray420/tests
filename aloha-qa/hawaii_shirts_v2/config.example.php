<?php
// Пример настроек подключения к базе данных.
// Скопируйте этот файл в config.php и укажите свои значения
// (на хостинге — из панели, раздел "Базы данных MySQL").
// Файл config.php не попадает в репозиторий (см. .gitignore).
//
// Значения читаются из переменных окружения; если переменной нет — берётся значение справа.
// В docker-compose переменные DB_* уже заданы, поэтому там достаточно скопировать файл как есть.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'your_database_name');
define('DB_USER', getenv('DB_USER') ?: 'your_database_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_database_password');

// Абсолютный путь к папке для загруженных фото товаров (должна быть доступна на запись).
define('UPLOAD_DIR', __DIR__ . '/uploads');
// Публичный URL-путь к той же папке (используется в <img src="...">).
define('UPLOAD_URL', '/uploads');
