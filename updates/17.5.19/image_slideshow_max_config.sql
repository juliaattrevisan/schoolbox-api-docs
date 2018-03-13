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
  'This is the maximum number of images displayed in the slideshow component. Be aware that high numbers (30+) have been known to crash servers and make pages unloadable.',
  1,
  (SELECT `id` FROM lu_config_type WHERE `type` = 'Resources'),
  true,
  true
);
