INSERT INTO `component_definition`
  (`name`, `class`, `description`, `module`, `component_category`, `visible`, `multiple`)
VALUES
  ('Image Slider', 'Component_Dashboard_ImageSliderController',
   'Display an animated image carousel, fading between all the images', 'dashboard', NULL, true, true);

INSERT INTO `component_config_definition`
  (`component_definition_id`, `key`, `default_value`, `label`)
VALUES
  ((SELECT `id` FROM `component_definition` WHERE class = 'Component_Dashboard_ImageSliderController'), 'componentname', 'Image Slider', 'Heading'),
  ((SELECT `id` FROM `component_definition` WHERE class = 'Component_Dashboard_ImageSliderController'), 'align',         'left',         'Align'),
  ((SELECT `id` FROM `component_definition` WHERE class = 'Component_Dashboard_ImageSliderController'), 'captionState',  'Active',       'Captions'),
  ((SELECT `id` FROM `component_definition` WHERE class = 'Component_Dashboard_ImageSliderController'), 'pixel',         '200',          'Max width/height'),
  ((SELECT `id` FROM `component_definition` WHERE class = 'Component_Dashboard_ImageSliderController'), 'src',           '200',          'Source'),
  ((SELECT `id` FROM `component_definition` WHERE class = 'Component_Dashboard_ImageSliderController'), 'timing',        '2',            'Timing'),
  ((SELECT `id` FROM `component_definition` WHERE class = 'Component_Dashboard_ImageSliderController'), 'compactMode',   '1',            'Hide header');
