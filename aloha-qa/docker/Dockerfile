# Готовый официальный образ: PHP 8.3 и веб-сервер Apache
FROM php:8.3-apache

# Драйвер PDO для MySQL: без него сайт не подключится к базе
RUN docker-php-ext-install pdo_mysql

# Разрешаем .htaccess, иначе на стенде не будет работать защита
# config.php и db.sql, и стенд будет вести себя не так, как хостинг
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf