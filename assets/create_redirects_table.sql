-- Таблица для управления редиректами
CREATE TABLE IF NOT EXISTS `redirects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `from_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `to_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `redirect_type` enum('301','302') COLLATE utf8mb4_general_ci DEFAULT '301',
  `is_active` tinyint(1) DEFAULT '1',
  `hits` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_from_url` (`from_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Примеры редиректов
INSERT INTO `redirects` (`from_url`, `to_url`, `redirect_type`, `is_active`) VALUES
('/old-category/1', '/products.php?tags[]=1', '301', 1),
('/old-product', '/products.php', '301', 1),
('/temp-sale', '/products.php', '302', 1);
