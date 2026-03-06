-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         12.0.2-MariaDB - mariadb.org binary distribution
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.13.0.7147
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para uproject_db
CREATE DATABASE IF NOT EXISTS `uproject_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci */;
USE `uproject_db`;

-- Volcando estructura para tabla uproject_db.contact_submissions
CREATE TABLE IF NOT EXISTS `contact_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','answered') NOT NULL DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.customers
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL COMMENT 'Whatsapp',
  `address` text DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL COMMENT 'DNI',
  `birth_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.employees
CREATE TABLE IF NOT EXISTS `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.exchange_rates
CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `currency_code` varchar(5) NOT NULL,
  `buy_price` decimal(10,2) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`currency_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.inventories
CREATE TABLE IF NOT EXISTS `inventories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `min_stock` tinyint(1) NOT NULL DEFAULT 0,
  `sale_price` tinyint(1) NOT NULL DEFAULT 0,
  `receipt_price` tinyint(1) NOT NULL DEFAULT 0,
  `hard_gain` tinyint(1) NOT NULL DEFAULT 0,
  `percentage_gain` tinyint(1) NOT NULL DEFAULT 0,
  `auto_price` tinyint(1) NOT NULL DEFAULT 0,
  `auto_price_type` varchar(10) DEFAULT NULL,
  `column_mappings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`column_mappings`)),
  `preferences` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `inventories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.inventory_preferences
CREATE TABLE IF NOT EXISTS `inventory_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` int(11) NOT NULL,
  `mapping_json` text DEFAULT NULL,
  `features_json` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_inventory` (`inventory_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.inventory_users
CREATE TABLE IF NOT EXISTS `inventory_users` (
  `inventory_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3,
  PRIMARY KEY (`inventory_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `inventory_users_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_users_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_users_ibfk_3` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `inventory_id` (`inventory_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1387 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.payment_methods
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'Other',
  `currency` varchar(10) NOT NULL DEFAULT 'ARS',
  `surcharge` decimal(5,2) DEFAULT 0.00 COMMENT 'Porcentaje de recargo (ej: 10 para 10%)',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.providers
CREATE TABLE IF NOT EXISTS `providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL COMMENT 'Whatsapp',
  `address` text DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL COMMENT 'CUIT/RUT',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.purchase_details
CREATE TABLE IF NOT EXISTS `purchase_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `product_id` varchar(255) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_purchase_detail` (`purchase_id`),
  CONSTRAINT `fk_purchase_detail` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.purchases
CREATE TABLE IF NOT EXISTS `purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `category` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_purchases_inventory` (`inventory_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.receipt_items
CREATE TABLE IF NOT EXISTS `receipt_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) NOT NULL,
  `receipt_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_sku` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `receipt_id` (`receipt_id`),
  KEY `FK_receipt_items_inventories` (`inventory_id`),
  CONSTRAINT `FK_receipt_items_inventories` FOREIGN KEY (`inventory_id`) REFERENCES `inventories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `receipt_items_ibfk_1` FOREIGN KEY (`receipt_id`) REFERENCES `receipts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.receipts
CREATE TABLE IF NOT EXISTS `receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `receipt_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `provider_id` (`provider_id`),
  CONSTRAINT `receipts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `receipts_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Volcando estructura para tabla uproject_db.roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

UPDATE roles SET name = 'Owner' WHERE id = 1;
UPDATE roles SET name = 'Admin' WHERE id = 2;
UPDATE roles SET name = 'Employee' WHERE id = 3;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.sale_details
CREATE TABLE IF NOT EXISTS `sale_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` decimal(20,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(20,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(20,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_details_sale` (`sale_id`),
  KEY `idx_details_product` (`product_id`),
  CONSTRAINT `FK_sale_details_sales` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.sale_items
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_sku` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `sale_items_inventory` (`inventory_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `sale_items_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.sale_payments
CREATE TABLE IF NOT EXISTS `sale_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `currency_id` varchar(10) NOT NULL DEFAULT 'ARS',
  `amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `original_amount` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `exchange_rate` decimal(20,2) NOT NULL DEFAULT 1.00,
  `surcharge` decimal(20,2) DEFAULT 0.00,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_payments_sale` (`sale_id`),
  CONSTRAINT `FK_sale_payments_sales` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.sales
CREATE TABLE IF NOT EXISTS `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `seller_id` int(11) DEFAULT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `sale_date` datetime NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `exchange_rate_snapshot` decimal(20,2) NOT NULL DEFAULT 1.00,
  `amount_tendered` decimal(20,2) DEFAULT 0.00,
  `change_returned` decimal(20,2) DEFAULT 0.00,
  `commission_amount` decimal(20,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_sales_user` (`user_id`),
  KEY `idx_sales_inventory` (`inventory_id`),
  KEY `idx_sales_customer` (`customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.user_6_pruebadb
CREATE TABLE IF NOT EXISTS `user_6_pruebadb` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `min_stock` int(11) DEFAULT 0,
  `hard_gain` decimal(10,2) DEFAULT 0.00,
  `percentage_gain` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `nombre` varchar(255) DEFAULT NULL,
  `categoria` text DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `proveedor` text DEFAULT NULL,
  `Fecha de Ingreso` text DEFAULT NULL,
  `Fecha de Vencimiento` text DEFAULT NULL,
  `ubicacion` text DEFAULT NULL,
  `Codigo de Barras` text DEFAULT NULL,
  `clientes` text DEFAULT NULL,
  `Precio de Compra` text DEFAULT NULL,
  `Precio de Venta` text DEFAULT NULL,
  `_meta_currency_buy` varchar(10) DEFAULT 'ARS',
  `_meta_currency_sale` varchar(10) DEFAULT 'ARS',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=501 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.user_6_pruebadb2
CREATE TABLE IF NOT EXISTS `user_6_pruebadb2` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `min_stock` int(11) DEFAULT 0,
  `sale_price` decimal(10,2) DEFAULT 0.00,
  `receipt_price` decimal(10,2) DEFAULT 0.00,
  `hard_gain` decimal(10,2) DEFAULT 0.00,
  `percentage_gain` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `stock` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.user_6_pruebadb3
CREATE TABLE IF NOT EXISTS `user_6_pruebadb3` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `min_stock` int(11) DEFAULT 0,
  `sale_price` decimal(10,2) DEFAULT 0.00,
  `receipt_price` decimal(10,2) DEFAULT 0.00,
  `hard_gain` decimal(10,2) DEFAULT 0.00,
  `percentage_gain` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `stock` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.user_tables
CREATE TABLE IF NOT EXISTS `user_tables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` int(11) NOT NULL,
  `table_name` varchar(128) NOT NULL,
  `columns_json` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `table_name` (`table_name`),
  KEY `inventory_id` (`inventory_id`),
  CONSTRAINT `user_tables_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla uproject_db.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_hash` varchar(255) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `otp_attempts` int(11) NOT NULL DEFAULT 0,
  `otp_last_sent_at` datetime DEFAULT NULL,
  `otp_action_type` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `cell` varchar(20) DEFAULT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `dni` (`dni`),
  KEY `idx_google_id` (`google_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para disparador uproject_db.inventories_delete
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER inventories_delete
BEFORE DELETE ON inventories
FOR EACH ROW
BEGIN
    INSERT INTO reg_inventories (action, inventory_id, name, user_id, created_at)
    VALUES ('Borrado', OLD.id, OLD.name, OLD.user_id, DATE_FORMAT(OLD.created_at, '%Y-%m-%d %H:%i:%s'));
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador uproject_db.inventories_insert
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER inventories_insert
AFTER INSERT ON inventories
FOR EACH ROW
BEGIN
    INSERT INTO reg_inventories (action, inventory_id, name, user_id, created_at)
    VALUES ('Agregado', NEW.id, NEW.name, NEW.user_id, DATE_FORMAT(NEW.created_at, '%Y-%m-%d %H:%i:%s'));
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador uproject_db.inventories_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER inventories_update
AFTER UPDATE ON inventories
FOR EACH ROW
BEGIN
    DECLARE changes_made BOOLEAN DEFAULT FALSE;
    DECLARE v_inventory_id VARCHAR(200) DEFAULT OLD.id;
    DECLARE v_name VARCHAR(400) DEFAULT '';
    DECLARE v_user_id VARCHAR(200) DEFAULT '';
    DECLARE v_created_at VARCHAR(200) DEFAULT '';

    IF NOT (NEW.id <=> OLD.id) THEN
        SET v_inventory_id = CONCAT(OLD.id, ' -> ', NEW.id);
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.name <=> OLD.name) THEN
        SET v_name = CONCAT(IFNULL(OLD.name,'N/A'), ' -> ', IFNULL(NEW.name,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.user_id <=> OLD.user_id) THEN
        SET v_user_id = CONCAT(OLD.user_id, ' -> ', NEW.user_id);
        SET changes_made = TRUE;
    END IF;
        IF NOT(NEW.created_at <=> OLD.created_at) THEN
    	  SET v_created_at = CONCAT(DATE_FORMAT(OLD.created_at, '%Y-%m-%d %H:%i:%s'), ' -> ', DATE_FORMAT(NEW.created_at, '%Y-%m-%d %H:%i:%s'));
		  SET changes_made = TRUE;
	 END IF;

    IF changes_made THEN
        INSERT INTO reg_inventories (action, inventory_id, name, user_id, created_at)
        VALUES ('Modificado', v_inventory_id, v_name, v_user_id, v_created_at);
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador uproject_db.sal_items_delete
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER sal_items_delete
BEFORE DELETE ON sale_items
FOR EACH ROW
BEGIN
    INSERT INTO reg_salitm (action, sale_item_id, item_id, inventory_id, sale_id, product_name, product_sku, quantity, unit_price, total_price)
    VALUES ('Borrado', OLD.id, OLD.item_id, OLD.inventory_id, OLD.sale_id, OLD.product_name, OLD.product_sku, CAST(OLD.quantity AS CHAR), CAST(OLD.unit_price AS CHAR), CAST(OLD.total_price AS CHAR));
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador uproject_db.sal_items_insert
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER sal_items_insert
AFTER INSERT ON sale_items
FOR EACH ROW
BEGIN
    INSERT INTO reg_salitm (action, sale_item_id, item_id, inventory_id, sale_id, product_name, product_sku, quantity, unit_price, total_price)
    VALUES ('Agregado', NEW.id, NEW.item_id, NEW.inventory_id, NEW.sale_id, NEW.product_name, NEW.product_sku, CAST(NEW.quantity AS CHAR), CAST(NEW.unit_price AS CHAR), CAST(NEW.total_price AS CHAR));
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador uproject_db.sal_items_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER sal_items_update
AFTER UPDATE ON sale_items
FOR EACH ROW
BEGIN
    DECLARE changes_made BOOLEAN DEFAULT FALSE;
    DECLARE v_sale_item_id VARCHAR(200) DEFAULT OLD.id;
    DECLARE v_item_id VARCHAR(200) DEFAULT '';
    DECLARE v_inventory_id VARCHAR(200) DEFAULT '';
    DECLARE v_sale_id VARCHAR(200) DEFAULT '';
    DECLARE v_product_name VARCHAR(400) DEFAULT '';
    DECLARE v_product_sku VARCHAR(200) DEFAULT '';
    DECLARE v_quantity VARCHAR(200) DEFAULT '';
    DECLARE v_unit_price VARCHAR(200) DEFAULT '';
    DECLARE v_total_price VARCHAR(200) DEFAULT '';

    IF NOT (NEW.id <=> OLD.id) THEN
        SET v_sale_item_id = CONCAT(OLD.id, ' -> ', NEW.id);
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.item_id <=> OLD.item_id) THEN
        SET v_item_id = CONCAT(IFNULL(OLD.item_id,'N/A'), ' -> ', IFNULL(NEW.item_id,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.inventory_id <=> OLD.inventory_id) THEN
        SET v_inventory_id = CONCAT(IFNULL(OLD.inventory_id,'N/A'), ' -> ', IFNULL(NEW.inventory_id,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.sale_id <=> OLD.sale_id) THEN
        SET v_sale_id = CONCAT(IFNULL(OLD.sale_id,'N/A'), ' -> ', IFNULL(NEW.sale_id,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.product_name <=> OLD.product_name) THEN
        SET v_product_name = CONCAT(IFNULL(OLD.product_name,'N/A'), ' -> ', IFNULL(NEW.product_name,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.product_sku <=> OLD.product_sku) THEN
        SET v_product_sku = CONCAT(IFNULL(OLD.product_sku,'N/A'), ' -> ', IFNULL(NEW.product_sku,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.quantity <=> OLD.quantity) THEN
        SET v_quantity = CONCAT(IFNULL(OLD.quantity,'N/A'), ' -> ', IFNULL(NEW.quantity,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.unit_price <=> OLD.unit_price) THEN
        SET v_unit_price = CONCAT(IFNULL(OLD.unit_price,'N/A'), ' -> ', IFNULL(NEW.unit_price,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.total_price <=> OLD.total_price) THEN
        SET v_total_price = CONCAT(IFNULL(OLD.total_price,'N/A'), ' -> ', IFNULL(NEW.total_price,'N/A'));
        SET changes_made = TRUE;
    END IF;

    IF changes_made THEN
        INSERT INTO reg_salitm (action, sale_item_id, item_id, inventory_id, sale_id, product_name, product_sku, quantity, unit_price, total_price)
        VALUES ('Modificado', v_sale_item_id, v_item_id, v_inventory_id, v_sale_id, v_product_name, v_product_sku, v_quantity, v_unit_price, v_total_price);
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador uproject_db.users_delete
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER users_delete
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
    INSERT INTO reg_users (action, user_id, username, email, full_name, cell, dni, is_admin, created_at)
    VALUES ('Borrado', OLD.id, OLD.username, OLD.email, OLD.full_name, OLD.cell, OLD.dni, CAST(OLD.is_admin AS CHAR), DATE_FORMAT(OLD.created_at, '%Y-%m-%d %H:%i:%s'));
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador uproject_db.users_insert
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER users_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO reg_users (action, user_id, username, email, full_name, cell, dni, is_admin, created_at)
    VALUES ('Agregado', NEW.id, NEW.username, NEW.email, NEW.full_name, NEW.cell, NEW.dni, CAST(NEW.is_admin AS CHAR), DATE_FORMAT(NEW.created_at, '%Y-%m-%d %H:%i:%s'));
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Volcando estructura para disparador uproject_db.users_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER users_update
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    DECLARE changes_made BOOLEAN DEFAULT FALSE;
    DECLARE v_user_id VARCHAR(200) DEFAULT OLD.id;
    DECLARE v_username VARCHAR(300) DEFAULT '';
    DECLARE v_email VARCHAR(300) DEFAULT '';
    DECLARE v_full_name VARCHAR(400) DEFAULT '';
    DECLARE v_cell VARCHAR(200) DEFAULT '';
    DECLARE v_dni VARCHAR(200) DEFAULT '';
    DECLARE v_is_admin VARCHAR(50) DEFAULT '';
    DECLARE v_created_at VARCHAR(200) DEFAULT '';

    IF NOT (NEW.id <=> OLD.id) THEN
        SET v_user_id = CONCAT(OLD.id, ' -> ', NEW.id);
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.username <=> OLD.username) THEN
        SET v_username = CONCAT(IFNULL(OLD.username,'N/A'), ' -> ', IFNULL(NEW.username,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.email <=> OLD.email) THEN
        SET v_email = CONCAT(IFNULL(OLD.email,'N/A'), ' -> ', IFNULL(NEW.email,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.full_name <=> OLD.full_name) THEN
        SET v_full_name = CONCAT(IFNULL(OLD.full_name,'N/A'), ' -> ', IFNULL(NEW.full_name,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.cell <=> OLD.cell) THEN
        SET v_cell = CONCAT(IFNULL(OLD.cell,'N/A'), ' -> ', IFNULL(NEW.cell,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.dni <=> OLD.dni) THEN
        SET v_dni = CONCAT(IFNULL(OLD.dni,'N/A'), ' -> ', IFNULL(NEW.dni,'N/A'));
        SET changes_made = TRUE;
    END IF;
    IF NOT (NEW.is_admin <=> OLD.is_admin) THEN
        SET v_is_admin = CONCAT(OLD.is_admin, ' -> ', NEW.is_admin);
        SET changes_made = TRUE;
    END IF;
    IF NOT(NEW.created_at <=> OLD.created_at) THEN
    	  SET v_created_at = CONCAT(DATE_FORMAT(OLD.created_at, '%Y-%m-%d %H:%i:%s'), ' -> ', DATE_FORMAT(NEW.created_at, '%Y-%m-%d %H:%i:%s'));
		  SET changes_made = TRUE;
	 END IF;

    IF changes_made THEN
        INSERT INTO reg_users (action, user_id, username, email, full_name, cell, dni, is_admin, created_at)
        VALUES ('Modificado', v_user_id, v_username, v_email, v_full_name, v_cell, v_dni, v_is_admin, v_created_at);
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
