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

INSERT INTO schoolbox.config (name, value, title, description, field_id, options, type_id, editable, hidden, required) VALUES
  ('webdav_fallback_enabled', '1', 'Legacy WebDAV Configuration Fallback',
   'For schools with legacy WebDAV configuration setups prior to v17.0 only. When enabled Schoolbox will fallback to using the legacy configuration if no matching rules were found for the current logged in user.', 3, '', 5, 'true', 'false', 'false');