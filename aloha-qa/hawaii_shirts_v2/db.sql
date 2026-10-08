-- Схема базы данных Aloha Threads для MySQL.
-- Импортируйте этот файл через phpMyAdmin (или `mysql -u USER -p DBNAME < db.sql`)
-- в пустую базу данных, созданную в панели хостинга.
-- Если база уже создана и работает — НЕ импортируйте этот файл повторно,
-- а примените миграцию из папки migrations/.

SET NAMES utf8mb4;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(190) NOT NULL,
  phone VARCHAR(50) NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'CUSTOMER',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  category VARCHAR(100) NOT NULL,
  image_url VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Остатки по размерам. Если у товара нет строк в этой таблице — он продаётся
-- без выбора размера, а остаток берётся из products.stock.
-- Если строки есть — products.stock хранит сумму остатков по всем размерам.
CREATE TABLE product_sizes (
  product_id INT NOT NULL,
  size VARCHAR(10) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, size),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cart_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  size VARCHAR(10) NOT NULL DEFAULT '',
  quantity INT NOT NULL DEFAULT 1,
  UNIQUE KEY uniq_user_product_size (user_id, product_id, size),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'PAID',
  total DECIMAL(10,2) NOT NULL,
  contact_name VARCHAR(190) NOT NULL,
  contact_email VARCHAR(190) NOT NULL,
  contact_phone VARCHAR(50) NOT NULL,
  shipping_country VARCHAR(100) NOT NULL,
  shipping_city VARCHAR(100) NOT NULL,
  shipping_street VARCHAR(190) NOT NULL,
  shipping_house VARCHAR(20) NOT NULL DEFAULT '',
  shipping_apartment VARCHAR(20) NOT NULL DEFAULT '',
  shipping_zip VARCHAR(20) NOT NULL,
  cancel_reason TEXT NULL,
  cancel_requested_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  name VARCHAR(190) NOT NULL,
  size VARCHAR(10) NOT NULL DEFAULT '',
  price DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
