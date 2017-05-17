CREATE TABLE `webdav`
(
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `uri` VARCHAR(1024) NOT NULL,
  `path` VARCHAR(1024) NOT NULL,
  `username_prefix` VARCHAR(100) NOT NULL,
  `username_suffix` VARCHAR(100) NOT NULL,
  `campus_id` INT NULL,
  `role_id` INT NULL,
  `enabled` BOOL NOT NULL DEFAULT TRUE,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL
);

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required) VALUES
  ('webdav_fallback_enabled', '1', 'Legacy WebDAV Configuration Fallback',
   'For schools with legacy WebDAV configuration setups prior to v17.0 only. When enabled Schoolbox will fallback to using the legacy configuration if no matching rules were found for the current logged in user.', 3, '', 5, 'true', 'false', 'false');
