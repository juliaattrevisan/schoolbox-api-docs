-- Create user participation table
CREATE TABLE `tour`
(
  `uuid`        VARCHAR(36) PRIMARY KEY NOT NULL,
  `title`       VARCHAR(512) NOT NULL,
  `description` TEXT NOT NULL,
  `active`      INT(1) UNSIGNED NOT NULL,
  `created_at`  DATETIME NOT NULL,
  `updated_at`  DATETIME NOT NULL,
  `deleted_at`  DATETIME
);

