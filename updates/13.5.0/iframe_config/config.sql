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
  'www.youtube.com/embed/
player.vimeo.com/video/
www.slideshare.net/slideshow/embed_code/
(?:www|edu).glogster.com/glog(?:/|.php)
wikieducator.org/
www.google.com(?:.[a-z]{2})?/maps
maps.google.com(?:.[a-z]{2})?/
docs.google.com(?:.[a-z]{2})?/
www.tiki-toki.com/timeline/embed/
www.thinglink.com/card/
www.facebook.com/plugins/
padlet.com/embed/
linoit.com/users/
www.showme.com/sma/embed
www.sunsmart.com.au/uvalert/
skydrive.live.com/embed
(?:www\\\\.)?studyboost.com/
prezi.com/embed/
(?:www\\\\.)?spiderscribe.net/app/
(?:www\\\\.)?evt.org.nz
vhss-d.oddcast.com/vhss_editors/voki_player.swf',
  'Safe IFrame Sources',
  'Any URL that begins with a line in this list may be used as the source of an IFrame in text boxes. Regular expressions may be used, but do not include HTTP or HTTPS.',
  'textarea',
  '',
  'Security',
  'true',
  'false',
  'false'
);
