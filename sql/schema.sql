-- ============================================================
-- Laboratory Inventory Management System - Database Schema
-- Version: 1.0 | Generated for XAMPP/WAMP MySQL 5.7+
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `lab_inventory` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lab_inventory`;

-- TABLE: users
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin','lab_manager','staff') NOT NULL DEFAULT 'staff',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE: categories
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `color_code` VARCHAR(7) DEFAULT '#4A90D9',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE: storage_locations
CREATE TABLE `storage_locations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `temperature_range` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE: suppliers
CREATE TABLE `suppliers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `contact_email` VARCHAR(100) DEFAULT NULL,
  `contact_phone` VARCHAR(30) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE: inventory_items
CREATE TABLE `inventory_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_code` VARCHAR(20) NOT NULL,
  `item_name` VARCHAR(200) NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `min_quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `unit` ENUM('mL','L','mg','g','kg','units','boxes','vials','plates','rolls') NOT NULL DEFAULT 'units',
  `storage_location_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED DEFAULT NULL,
  `lot_number` VARCHAR(100) DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `date_received` DATE DEFAULT NULL,
  `cost_per_unit` DECIMAL(10,2) DEFAULT NULL,
  `hazard_class` ENUM('None','Flammable','Corrosive','Toxic','Oxidizer','Biohazard','Radioactive','Explosive','Irritant') DEFAULT 'None',
  `msds_file` VARCHAR(255) DEFAULT NULL,
  `barcode` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('active','expired','low_stock','discontinued','under_maintenance') NOT NULL DEFAULT 'active',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `idx_category` (`category_id`),
  KEY `idx_storage` (`storage_location_id`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expiry` (`expiry_date`),
  KEY `idx_item_name` (`item_name`),
  CONSTRAINT `fk_item_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `fk_item_storage` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`),
  CONSTRAINT `fk_item_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_item_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_item_updated` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE: stock_movements
CREATE TABLE `stock_movements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` INT UNSIGNED NOT NULL,
  `movement_type` ENUM('in','out','adjustment','disposal') NOT NULL,
  `quantity_change` DECIMAL(10,2) NOT NULL,
  `quantity_before` DECIMAL(10,2) NOT NULL,
  `quantity_after` DECIMAL(10,2) NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `reference_number` VARCHAR(100) DEFAULT NULL,
  `performed_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_movement_date` (`created_at`),
  CONSTRAINT `fk_movement_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_movement_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE: audit_log
CREATE TABLE `audit_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `table_name` VARCHAR(50) DEFAULT NULL,
  `record_id` INT UNSIGNED DEFAULT NULL,
  `old_values` TEXT DEFAULT NULL,
  `new_values` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE: alerts
CREATE TABLE `alerts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` INT UNSIGNED NOT NULL,
  `alert_type` ENUM('low_stock','expiry','expired','maintenance') NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_is_read` (`is_read`),
  CONSTRAINT `fk_alert_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default password for all seeded users: password (bcrypt hashed)
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`) VALUES
('admin', 'admin@labsys.com', '$2y$10$VruE6ftfktb48rjRjXF7UeD/2RYGfluvHsulDt1wUoXCMvYxwvL.W', 'Dr. Admin User', 'admin'),
('labmanager', 'manager@labsys.com', '$2y$10$VruE6ftfktb48rjRjXF7UeD/2RYGfluvHsulDt1wUoXCMvYxwvL.W', 'Dr. Sarah Chen', 'lab_manager'),
('staff1', 'staff@labsys.com', '$2y$10$VruE6ftfktb48rjRjXF7UeD/2RYGfluvHsulDt1wUoXCMvYxwvL.W', 'John Smith', 'staff');

INSERT INTO `categories` (`name`, `description`, `color_code`) VALUES
('Chemical', 'Chemical compounds and solvents', '#E74C3C'),
('Reagent', 'Laboratory reagents and buffers', '#3498DB'),
('Equipment', 'Lab instruments and devices', '#2ECC71'),
('Consumable', 'Disposable lab supplies', '#F39C12'),
('Sample', 'Biological and environmental samples', '#9B59B6');

INSERT INTO `storage_locations` (`name`, `description`, `temperature_range`) VALUES
('Freezer A (-80C)', 'Ultra-low temperature freezer', '-80C to -70C'),
('Freezer B (-20C)', 'Standard freezer', '-20C to -18C'),
('Refrigerator (4C)', 'Cold storage', '2C to 8C'),
('Cold Room', 'Walk-in cold room', '4C to 8C'),
('Shelf A', 'Room temperature shelf A', '18C to 25C'),
('Shelf B', 'Room temperature shelf B', '18C to 25C'),
('Chemical Cabinet', 'Ventilated chemical storage', '18C to 25C'),
('Flammables Cabinet', 'Fire-resistant cabinet', '18C to 25C'),
('Equipment Room', 'Instrument storage area', '18C to 25C'),
('Biological Safety Cabinet', 'BSC for biohazard materials', '18C to 25C');

INSERT INTO `suppliers` (`name`, `contact_email`, `contact_phone`, `website`) VALUES
('Sigma-Aldrich', 'orders@sigmaaldrich.com', '+1-800-325-3010', 'https://www.sigmaaldrich.com'),
('Thermo Fisher Scientific', 'orders@thermofisher.com', '+1-800-766-7000', 'https://www.thermofisher.com'),
('VWR International', 'orders@vwr.com', '+1-800-932-5000', 'https://www.vwr.com'),
('BD Biosciences', 'orders@bd.com', '+1-800-877-6242', 'https://www.bd.com'),
('Qiagen', 'orders@qiagen.com', '+1-800-426-8157', 'https://www.qiagen.com'),
('BioRad', 'orders@bio-rad.com', '+1-800-424-6723', 'https://www.bio-rad.com');

INSERT INTO `inventory_items` (`item_code`,`item_name`,`category_id`,`quantity`,`min_quantity`,`unit`,`storage_location_id`,`supplier_id`,`lot_number`,`expiry_date`,`date_received`,`cost_per_unit`,`hazard_class`,`status`,`notes`,`created_by`) VALUES
('CHEM-001','Ethanol 96%',1,5000,500,'mL',8,1,'LOT-E2024-001','2026-12-31','2024-01-15',0.05,'Flammable','active','Molecular biology grade',1),
('CHEM-002','Hydrochloric Acid 37%',1,2,5,'L',7,1,'LOT-H2024-002','2025-06-30','2024-02-10',12.50,'Corrosive','low_stock','ACS reagent grade',1),
('CHEM-003','Sodium Chloride',1,500,100,'g',5,2,'LOT-N2024-003','2027-01-01','2024-01-20',0.15,'None','active','Molecular biology grade',1),
('CHEM-004','Methanol HPLC Grade',1,4000,500,'mL',8,1,'LOT-M2024-004','2026-08-15','2024-03-01',0.08,'Flammable','active','HPLC grade solvent',1),
('CHEM-005','Formaldehyde 37%',1,500,500,'mL',7,1,'LOT-F2023-005','2024-01-15','2023-01-15',0.09,'Toxic','expired','EXPIRED - dispose per protocol',1),
('REAG-001','PBS Buffer 10X',2,50,100,'mL',3,2,'LOT-P2024-001','2025-04-15','2024-01-10',8.90,'None','low_stock','Cell culture grade',1),
('REAG-002','TRIS-HCl Buffer pH 8.0',2,200,50,'mL',3,1,'LOT-T2024-002','2025-12-31','2024-02-05',12.30,'None','active','1M stock solution',1),
('REAG-003','EDTA Solution 0.5M',2,150,50,'mL',5,2,'LOT-E2024-003','2025-11-30','2024-02-20',6.75,'None','active','pH 8.0 sterile filtered',1),
('REAG-004','Agarose LE',2,3,10,'g',5,6,'LOT-A2024-004','2026-05-01','2024-01-25',45.00,'None','low_stock','Molecular biology grade',1),
('REAG-005','Bradford Protein Assay',2,100,30,'mL',3,6,'LOT-BPA2024','2025-08-31','2024-02-28',35.00,'None','active','Ready to use reagent',1),
('EQUIP-001','Micropipette P1000',3,5,2,'units',9,2,'SN-P1000-2023',NULL,'2023-11-01',285.00,'None','active','Gilson PIPETMAN - calibrated Jan 2024',1),
('EQUIP-002','Centrifuge Eppendorf 5424R',3,1,1,'units',9,2,'SN-5424R-001',NULL,'2023-06-15',8500.00,'None','under_maintenance','Scheduled maintenance Q1 2025',1),
('EQUIP-003','UV-Vis Spectrophotometer',3,1,1,'units',9,2,'SN-UV2022-001',NULL,'2022-08-10',12000.00,'None','active','NanoDrop 2000 - calibrated monthly',1),
('CONS-001','PCR Tubes 0.2mL',4,500,200,'units',6,3,'LOT-PCR2024','2028-01-01','2024-02-01',0.12,'None','active','Individual strip caps',1),
('CONS-002','Nitrile Gloves Medium',4,3,5,'boxes',6,3,'LOT-GLV2024','2027-06-30','2024-01-30',8.50,'None','low_stock','100 gloves per box',1),
('CONS-003','Eppendorf Tubes 1.5mL',4,1200,500,'units',6,1,'LOT-EP2024','2028-12-31','2024-02-15',0.08,'None','active','SafeLock tubes',1),
('CONS-004','Filter Tips 200uL',4,800,200,'units',6,2,'LOT-FT2024','2028-01-01','2024-03-01',0.15,'None','active','ART barrier tips',1),
('SAMP-001','Human Serum Panel A',5,10,3,'vials',1,NULL,'LOT-HS2024-A','2025-02-28','2024-01-05',250.00,'Biohazard','active','Processed and inactivated',1),
('SAMP-002','E. coli Strain K12',5,25,5,'vials',1,NULL,'LOT-EC2024-K12','2025-06-30','2024-02-01',0.00,'Biohazard','active','Glycerol stock -80C storage',1),
('SAMP-003','RNA Extraction Kit',5,8,3,'units',2,5,'LOT-RNA2024','2025-09-30','2024-01-18',89.00,'None','active','RNeasy Mini Kit 50 preps',1);

INSERT INTO `stock_movements` (`item_id`,`movement_type`,`quantity_change`,`quantity_before`,`quantity_after`,`reason`,`performed_by`) VALUES
(1,'in',5000,0,5000,'Initial stock entry',1),
(2,'in',10,0,10,'Initial stock entry',1),
(2,'out',8,10,2,'Used for pH adjustment experiment',2),
(6,'in',200,0,200,'Initial stock entry',1),
(6,'out',150,200,50,'Cell culture media preparation',2),
(14,'in',1000,0,1000,'Initial stock entry',1),
(14,'out',500,1000,500,'PCR runs batch 1',3),
(3,'in',500,0,500,'Initial stock entry',1),
(7,'in',200,0,200,'Initial stock entry',1),
(18,'in',10,0,10,'Received from biobank',2),
(4,'in',4000,0,4000,'Initial stock entry',1),
(4,'out',0,4000,4000,'No movement',1),
(16,'in',1500,0,1500,'Initial stock entry',1),
(16,'out',300,1500,1200,'General use Q1 2024',3);

INSERT INTO `alerts` (`item_id`,`alert_type`,`message`,`is_read`) VALUES
(2,'low_stock','HCl stock critically low: 2L remaining (min: 5L)',0),
(6,'low_stock','PBS Buffer below minimum threshold: 50mL remaining',0),
(9,'low_stock','Agarose LE stock low: 3g remaining (min: 10g)',0),
(15,'low_stock','Nitrile Gloves supply low: 3 boxes remaining',0),
(5,'expired','Formaldehyde 37% has expired - dispose per hazmat protocol',0),
(18,'expiry','Human Serum Panel A expires soon - check usage schedule',0),
(12,'maintenance','Centrifuge 5424R is under scheduled maintenance',0);

SET FOREIGN_KEY_CHECKS = 1;
