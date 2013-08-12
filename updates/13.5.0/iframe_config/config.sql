-- Configure your own valid iframe sources, I'm sick of receiving tickets about
-- this sort of thing, and I'm sure you're sick of sending them.

-- First add new "html" input type, and change existing "textarea" to "html"
ALTER TABLE `config` CHANGE COLUMN `field` `field` ENUM('input','textarea','html','boolean','select','checkbox','bytes','selectKey') NULL DEFAULT 'input';
UPDATE `config` SET `field` = 'html' WHERE `field` = 'textarea';

-- and then add the field.
INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field`,
  `options`,
  `type`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'safe_iframe_sources',
  '',
  'Safe IFrame Sources',
  'Any URL that begins with a line in this list may be used as the source of an IFrame. Regular expressions may be used, but do not include HTTP or HTTPS.',
  'textarea',
  '',
  'Security',
  'true',
  'false',
  'false'
);
