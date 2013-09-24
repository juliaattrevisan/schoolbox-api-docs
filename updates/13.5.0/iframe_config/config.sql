-- Configure your own valid iframe sources, I'm sick of receiving tickets about
-- this sort of thing, and I'm sure you're sick of sending them.

-- First add new "html" input type, and change existing "textarea" to "html"
-- ALTER TABLE `config` CHANGE COLUMN `field` `field` ENUM('input','textarea','html','boolean','select','checkbox','bytes','selectKey') NULL DEFAULT 'input';

INSERT IGNORE INTO `lu_config_field` (`field`) VALUES ('html');
UPDATE `config`
  SET `field_id` = (SELECT `id` FROM `lu_config_field` WHERE `field` = 'html' LIMIT 1)
  WHERE `field_id` =  (SELECT `id` FROM `lu_config_field` WHERE `field` = 'textarea' LIMIT 1);

-- and then add the field.
INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field_id`,
  `options`,
  `type_id`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'safe_iframe_source_list',
  '',
  'Safe IFrame Sources',
  'Any URL that begins with a line in this list may be used as the source of an IFrame in text boxes. Do not include the scheme (HTTP or HTTPS), only the location and path (example: www.schoolbox.com.au/embed/).',
  (SELECT `id` FROM `lu_config_field` WHERE `field` = 'textarea' LIMIT 1),
  '',
  (SELECT `id` FROM `lu_config_type` WHERE `type` = 'Security' LIMIT 1),
  'true',
  'false',
  'false'
);
