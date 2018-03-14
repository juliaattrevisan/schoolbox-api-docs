/* Create a config option for forgotten password page title  */
INSERT INTO `config` (
  `name`,
  `title`,
  `value`,
  `description`,
  `field_id`,
  `type_id`,
  `editable`,
  `required`
) VALUES (
  'image_slideshow_max',
  'Max Images in Slideshows',
  '10',
  'This is the maximum number of images displayed in the slideshow component. Note that setting this limit too high (30+) has the potential to cause performance issues and make some homepages unloadable. Set this value to zero to apply no limit.',
  1,
  (SELECT `id` FROM lu_config_type WHERE `type` = 'Resources'),
  true,
  true
)
ON DUPLICATE KEY UPDATE `id` = `id`;
