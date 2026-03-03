-- Миграция: переход от категорий к тегам
-- Выполните этот SQL файл для обновления структуры БД

-- 1. Создаем таблицу tags
CREATE TABLE IF NOT EXISTS `tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#0dcaf0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tag_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Создаем связующую таблицу product_tags
CREATE TABLE IF NOT EXISTS `product_tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `tag_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_tag` (`product_id`, `tag_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_tag_id` (`tag_id`),
  CONSTRAINT `fk_product_tags_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_tags_tags` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Переносим данные из categories в tags
INSERT INTO `tags` (`id`, `name`, `color`, `is_active`, `created_at`)
SELECT 
  id,
  name,
  ELT(FLOOR(1 + (RAND() * 6)), '#0dcaf0', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14') as color,
  is_active,
  created_at
FROM categories
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 4. Привязываем теги к товарам на основе category_id
INSERT INTO `product_tags` (`product_id`, `tag_id`)
SELECT id, category_id
FROM products
WHERE category_id IS NOT NULL
ON DUPLICATE KEY UPDATE product_id = VALUES(product_id);

-- 5. Удаляем внешний ключ category_id из products (но оставляем колонку для совместимости)
ALTER TABLE `products` DROP FOREIGN KEY `fk_products_categories`;

-- 6. Вставляем дефолтные теги для новых товаров
INSERT INTO `tags` (`name`, `color`, `is_active`) VALUES
('Новинка', '#fd7e14', 1),
('Хит продаж', '#dc3545', 1),
('Рекомендуем', '#198754', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);
