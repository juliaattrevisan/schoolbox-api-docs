-- News Article
-- Note the Character Set/Collation settings for that infernal storage table
CREATE TABLE `comms_news_article` (
  `id`                 int(11)       PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `title`              VARCHAR(255)  NOT NULL,
  `body`               text,
  `blurb`              text,
  `author_id`          int(11)       UNSIGNED NOT NULL,
  `feature_image_hash` VARCHAR(100)  CHARACTER SET ascii COLLATE ascii_general_ci,
  `submitted_at`       DATETIME,
  `accepted_by`        int(11)       UNSIGNED,
  `accepted_at`        DATETIME,
  `rejected_by`        INT(11)       UNSIGNED NULL,
  `rejected_at`        DATETIME,
  `rejected_reason`    text,
  `publish_at`         DATETIME,
  `expires_at`         DATETIME,
  `created_at`         DATETIME      NOT NULL,
  `updated_at`         DATETIME      NOT NULL,
  `deleted_at`         DATETIME,
  CONSTRAINT fk_comms_news_article_rejected_by FOREIGN KEY (`rejected_by`) REFERENCES user (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  INDEX idx_comms_news_article_submitted_at (submitted_at),
  INDEX idx_comms_news_article_accepted_at (accepted_at),
  INDEX idx_comms_news_article_rejected_at (rejected_at),
  INDEX idx_comms_news_article_publish_at (publish_at),
  INDEX idx_comms_news_article_expires_at (expires_at),
  INDEX idx_comms_news_article_created_at (created_at),
  INDEX idx_comms_news_article_updated_at (updated_at),
  INDEX idx_comms_news_article_deleted_at (deleted_at)
) CHARACTER SET utf8mb4;

ALTER TABLE `comms_news_article`
ADD CONSTRAINT `fk_comms_news_article_author_id`
FOREIGN KEY (`author_id`) REFERENCES `user` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `comms_news_article`
ADD CONSTRAINT `fk_comms_news_article_accepted_by`
FOREIGN KEY (`accepted_by`) REFERENCES `user` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `comms_news_article`
ADD CONSTRAINT `fk_comms_news_article_featured_image_hash`
FOREIGN KEY (`feature_image_hash`) REFERENCES `storage` (`hash`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

-- News Article audience segments
CREATE TABLE comms_news_audience(
  id                    INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  comms_news_article_id INT NULL,
  parent_id             INT NULL,
  entity_type           ENUM('composite', 'user', 'super', 'campus', 'role', 'role_type', 'school_house', 'res_house', 'year', 'service', 'pastoral_group', 'folder') NOT NULL,
  entity_value          VARCHAR(32) NULL,
  relationship          ENUM('self','parents_of','teachers_of') NOT NULL,
  CONSTRAINT fk_comms_news_audience_comms_news_article_id FOREIGN KEY (`comms_news_article_id`) REFERENCES comms_news_article (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_comms_news_audience_parent_id FOREIGN KEY (`parent_id`) REFERENCES comms_news_audience (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  INDEX idx_comms_news_audience_type (entity_type),
  INDEX idx_comms_news_audience_value (entity_value),
  INDEX idx_comms_news_audience_relationship (relationship),
  UNIQUE INDEX idx_comms_news_audience_parent_type_value_relationship(parent_id, entity_type, entity_value, relationship)
) CHARACTER SET utf8mb4;

-- News Article Tag relations
CREATE TABLE `comms_news_article_topic` (
  `article_id` int(11) NOT NULL,
  `topic_id`   int(11) UNSIGNED NOT NULL
) CHARACTER SET utf8mb4;

ALTER TABLE `comms_news_article_topic`
ADD CONSTRAINT `fk_comms_news_article_article_id`
FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `comms_news_article_topic`
ADD CONSTRAINT `fk_comms_news_article_topic_id`
FOREIGN KEY (`topic_id`) REFERENCES `newsTopic` (`newsTopicID`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

-- Save for later
CREATE TABLE `comms_news_saveforlater` (
  `id`                 int(11)       PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `user_id`            int(11)       UNSIGNED NOT NULL,
  `article_id`         int(11)       NOT NULL,
  `created_at`         DATETIME      NOT NULL,
  `updated_at`         DATETIME      NOT NULL,
  `deleted_at`         DATETIME
) CHARACTER SET utf8mb4;

ALTER TABLE `comms_news_saveforlater`
  ADD CONSTRAINT `fk_comms_news_saveforlater_user_id`
FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
  ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `comms_news_saveforlater`
  ADD CONSTRAINT `fk_comms_news_saveforlater_article_id`
FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`)
  ON DELETE NO ACTION ON UPDATE NO ACTION;

CREATE UNIQUE INDEX `idx_comms_news_saveforlater_user_id_article_id`
  ON `comms_news_saveforlater` (user_id, article_id) COMMENT '' ALGORITHM DEFAULT LOCK DEFAULT;

CREATE INDEX comms_news_saveforlater_deleted_at_index ON comms_news_saveforlater (deleted_at);


-- Error messages

INSERT INTO `error` (id, message)
VALUES (224, 'This topic could not be found.');

-- Migration data
ALTER TABLE subject_news ADD migration_id int NULL;
