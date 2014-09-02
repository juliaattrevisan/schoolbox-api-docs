/* Homepage News expiry
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES ('homepage_news_expiry', '+1 week', 'Default Homepage News expiry date', "The relative date that Homepage News will expire on by default. Can be any string like 'tomorrow', '+1 week', 'next Thursday'", 1, '', 5, 'true', 'false', 'true');

/* Clarify School News expiry
 */
UPDATE `config` SET `title` = 'Default School News expiry date' WHERE `name` = 'news_default_expiry';
UPDATE `config` SET `description` = 'The relative date that School News will expire on by default. Can be any string like \'tomorrow\', \'+1 week\', \'next Thursday\'' WHERE `name` = 'news_default_expiry';