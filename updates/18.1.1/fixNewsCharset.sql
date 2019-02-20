ALTER TABLE `comms_news_article` CHARACTER SET = utf8mb4 ;
ALTER TABLE `comms_news_article_statistics` CHARACTER SET = utf8mb4 ;
ALTER TABLE `comms_news_article_statistics_cumulative` CHARACTER SET = utf8mb4 ;
ALTER TABLE `comms_news_article_topic` CHARACTER SET = utf8mb4 ;
ALTER TABLE `comms_news_audience` CHARACTER SET = utf8mb4 ;
ALTER TABLE `comms_news_saveforlater` CHARACTER SET = utf8mb4 ;

ALTER TABLE `comms_news_article`
  CHANGE COLUMN `title` `title` VARCHAR(255) NOT NULL ,
  CHANGE COLUMN `body` `body` MEDIUMTEXT NULL DEFAULT NULL ,
  CHANGE COLUMN `blurb` `blurb` MEDIUMTEXT NULL DEFAULT NULL,
  CHANGE COLUMN `rejected_reason` `rejected_reason` TEXT NULL DEFAULT NULL ;

ALTER TABLE `comms_news_audience`
  CHANGE COLUMN `entity_type` `entity_type` ENUM('composite', 'user', 'super', 'campus', 'role', 'role_type', 'school_house', 'res_house', 'year', 'service', 'pastoral_group', 'folder') CHARACTER SET 'ascii' NOT NULL ,
  CHANGE COLUMN `relationship` `relationship` ENUM('self', 'parents_of', 'teachers_of') CHARACTER SET 'ascii' NOT NULL ;

ALTER TABLE `comms_news_article_statistics`
  CHANGE COLUMN `type` `type` ENUM('impression', 'conversion', 'open') CHARACTER SET 'ascii' NULL DEFAULT NULL ,
  CHANGE COLUMN `referrer` `referrer` ENUM('dashboard', 'homepage', 'index', 'archive', 'email', 'saved', 'search') CHARACTER SET 'ascii' NULL DEFAULT NULL ,
  CHANGE COLUMN `role_type` `role_type` ENUM('staff', 'parent', 'student', 'guest') CHARACTER SET 'ascii' NULL DEFAULT NULL ;

ALTER TABLE `comms_news_article_statistics_cumulative`
  CHANGE COLUMN `type` `type` ENUM('impression', 'conversion', 'open') CHARACTER SET 'ascii' NULL DEFAULT NULL ,
  CHANGE COLUMN `referrer` `referrer` ENUM('dashboard', 'homepage', 'index', 'archive', 'email', 'saved', 'search') CHARACTER SET 'ascii' NULL DEFAULT NULL ,
  CHANGE COLUMN `role_type` `role_type` ENUM('staff', 'parent', 'student', 'guest') CHARACTER SET 'ascii' NULL DEFAULT NULL ;
