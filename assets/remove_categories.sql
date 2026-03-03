-- Миграция: удаление категорий и переход на теги
-- Выполните этот SQL файл для обновления структуры БД

-- 1. Создаем таблицу tags, если ещё не создана
CREATE TABLE IF NOT EXISTS `tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#0dcaf0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tag_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Создаем связующую таблицу product_tags, если ещё не создана
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

-- 3. Переносим данные из categories в tags (если ещё не перенесены)
INSERT IGNORE INTO `tags` (`id`, `name`, `color`, `is_active`, `created_at`)
SELECT 
  id,
  name,
  ELT(FLOOR(1 + (RAND() * 6)), '#0dcaf0', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14') as color,
  is_active,
  created_at
FROM categories;

-- 4. Привязываем теги к товарам на основе category_id (если ещё не привязаны)
INSERT IGNORE INTO `product_tags` (`product_id`, `tag_id`)
SELECT id, category_id
FROM products
WHERE category_id IS NOT NULL;

-- 5. Удаляем внешний ключ category_id из products, если существует
SET @dbname = DATABASE();
SET @foreign_key_check = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                          WHERE CONSTRAINT_SCHEMA = @dbname 
                          AND TABLE_NAME = 'products' 
                          AND CONSTRAINT_NAME = 'fk_products_categories'
                          AND CONSTRAINT_TYPE = 'FOREIGN KEY');

SET @sql = IF(@foreign_key_check > 0, 
    'ALTER TABLE `products` DROP FOREIGN KEY `fk_products_categories`',
    'SELECT "Foreign key fk_products_categories does not exist"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Удаляем колонку category_id из products
ALTER TABLE `products` DROP COLUMN `category_id`;

-- 7. Удаляем таблицу categories
DROP TABLE IF EXISTS `categories`;

-- 8. Вставляем дефолтные теги для новых товаров (если не существуют)
INSERT IGNORE INTO `tags` (`name`, `color`, `is_active`) VALUES
('Новинка', '#fd7e14', 1),
('Хит продаж', '#dc3545', 1),
('Рекомендуем', '#198754', 1);
