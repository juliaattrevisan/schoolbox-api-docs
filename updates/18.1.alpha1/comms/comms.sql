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
  `id`            int(11)      PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `title`         VARCHAR(255) NOT NULL,
  `body`          text,
  `blurb`         text,
  `author_id`     int(11)      UNSIGNED,
  `moderated_by`  int(11)      UNSIGNED,
  `moderated_at`  DATETIME,
  `publish_at`    DATETIME     NOT NULL,
  `expires_at`    DATETIME     NOT NULL,
  `created_at`    DATETIME     NOT NULL,
  `updated_at`    DATETIME     NOT NULL,
  `deleted_at`    DATETIME
);

ALTER TABLE `comms_news_article`
ADD CONSTRAINT `fk_comms_news_article_1`
FOREIGN KEY (`author_id`) REFERENCES `user` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `comms_news_article`
ADD CONSTRAINT `fk_comms_news_article_2`
FOREIGN KEY (`moderated_by`) REFERENCES `user` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;