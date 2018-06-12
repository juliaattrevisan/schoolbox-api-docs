-- Comms Officer
INSERT INTO lu_service (id, name) VALUES (11, 'Comms Officer');

-- News Policy
CREATE TABLE `comms_news_policy` (
  `id`         int(11)      PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(255) NOT NULL,
  `value`      VARCHAR(255) NULL
);

-- News Policy: Global Moderation
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('global_moderation', 0);

-- News Article
CREATE TABLE `comms_news_article` (
  `id`         int(11)      PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(255) NOT NULL,
  `body`       text,
  `blurb`      text,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME
);

