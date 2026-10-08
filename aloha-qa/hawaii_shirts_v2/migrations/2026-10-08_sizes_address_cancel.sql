-- Миграция для УЖЕ работающей базы (создана по старому db.sql).
-- Выполните один раз в phpMyAdmin → вкладка «SQL». Данные не удаляются.
-- Добавляет: размеры товаров, структурированный адрес (дом/квартира),
-- запрос на отмену заказа.

SET NAMES utf8mb4;

CREATE TABLE product_sizes (
  product_id INT NOT NULL,
  size VARCHAR(10) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, size),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE cart_items ADD COLUMN size VARCHAR(10) NOT NULL DEFAULT '' AFTER product_id;
ALTER TABLE cart_items ADD UNIQUE KEY uniq_user_product_size (user_id, product_id, size);
ALTER TABLE cart_items DROP INDEX uniq_user_product;

ALTER TABLE order_items ADD COLUMN size VARCHAR(10) NOT NULL DEFAULT '' AFTER name;

ALTER TABLE orders
  ADD COLUMN shipping_house VARCHAR(20) NOT NULL DEFAULT '' AFTER shipping_street,
  ADD COLUMN shipping_apartment VARCHAR(20) NOT NULL DEFAULT '' AFTER shipping_house,
  ADD COLUMN cancel_reason TEXT NULL AFTER shipping_zip,
  ADD COLUMN cancel_requested_at DATETIME NULL AFTER cancel_reason;
