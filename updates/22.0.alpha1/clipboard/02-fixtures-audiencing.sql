ALTER TABLE `comms_news_audience`
CHANGE COLUMN `entity_type` `entity_type` ENUM('composite', 'user', 'super', 'campus', 'role', 'role_type', 'school_house', 'res_house', 'year', 'service', 'pastoral_group', 'folder', 'fixturesteam') CHARACTER SET 'ascii' NOT NULL ;

ALTER TABLE `comms_news_audience_complex`
CHANGE COLUMN `entity_type_1` `entity_type_1` ENUM('user', 'super', 'campus', 'role', 'role_type', 'school_house', 'res_house', 'year', 'service', 'pastoral_group', 'folder', 'fixturesteam') CHARACTER SET 'ascii' NOT NULL ,
CHANGE COLUMN `entity_type_2` `entity_type_2` ENUM('user', 'super', 'campus', 'role', 'role_type', 'school_house', 'res_house', 'year', 'service', 'pastoral_group', 'folder', 'fixturesteam') CHARACTER SET 'ascii' NOT NULL ;
