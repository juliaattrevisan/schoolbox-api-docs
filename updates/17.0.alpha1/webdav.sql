CREATE TABLE `webdav`
(
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `uri` VARCHAR(1024),
  `path` VARCHAR(1024),
  `username_prefix` VARCHAR(100),
  `username_suffix` VARCHAR(100),
  `campus_id` INT,
  `role_id` INT,
  `enabled` TINYINT DEFAULT 1,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  `deleted_at` DATETIME
);