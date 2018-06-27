-- Comms Officer
INSERT INTO lu_service (id, name) VALUES (11, 'Comms Officer');

-- News Policy
CREATE TABLE `comms_news_policy` (
  `id`         int(11)      PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(255) NOT NULL,
  `value`      VARCHAR(255) NULL
);

-- News Policy: Global Moderation
-- When enabled, every news article requires moderation before it can reach the masses
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('global_moderation', 1);

-- News Policy: Publish Date Offset
-- When enabled, every article's publish date is offset by this amount
-- When empty, publish date is set to the current datetime for immediate effect
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('publish_date_offset', '');

-- News Policy: Expiry Date Offset
-- When enabled, every article's expiry date is offset by this amount
-- When empty, expiry date is set to the current datetime plus 1 week
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('expiry_date_offset', '+1 week');

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
  `moderated_by`       int(11)       UNSIGNED,
  `moderated_at`       DATETIME,
  `rejected_by`        int(11)       UNSIGNED,
  `rejected_at`        DATETIME,
  `rejected_reason`    text,
  `publish_at`         DATETIME      NOT NULL,
  `expires_at`         DATETIME      NOT NULL,
  `created_at`         DATETIME      NOT NULL,
  `updated_at`         DATETIME      NOT NULL,
  `deleted_at`         DATETIME
);

ALTER TABLE `comms_news_article`
ADD CONSTRAINT `fk_comms_news_article_author_id`
FOREIGN KEY (`author_id`) REFERENCES `user` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `comms_news_article`
ADD CONSTRAINT `fk_comms_news_article_moderated_by`
FOREIGN KEY (`moderated_by`) REFERENCES `user` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `comms_news_article`
ADD CONSTRAINT `fk_comms_news_article_featured_image_hash`
FOREIGN KEY (`feature_image_hash`) REFERENCES `storage` (`hash`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

-- News Article Tag relations
CREATE TABLE `comms_news_article_topic` (
  `article_id` int(11) NOT NULL,
  `topic_id`   int(11) UNSIGNED NOT NULL
);

ALTER TABLE `comms_news_article_topic`
ADD CONSTRAINT `fk_comms_news_article_article_id`
FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `comms_news_article_topic`
ADD CONSTRAINT `fk_comms_news_article_topic_id`
FOREIGN KEY (`topic_id`) REFERENCES `newsTopic` (`newsTopicID`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

-- Error messages
INSERT INTO `error` (id, message)
VALUES (215, 'You cannot edit articles that are already moderated.');

INSERT INTO `error` (id, message)
VALUES (216, 'You cannot delete this article.');