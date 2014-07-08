INSERT INTO `component_definition`
  (`name`, `class`, `description`, `module`, `component_category`, `visible`, `multiple`)
VALUES
  ('Image Slider', 'Component_Dashboard_ImageSliderController',
   'An interactive slider for displaying ads that can be clicked', 'dashboard', NULL, false, true);

INSERT INTO `component_config_definition`
  (`component_definition_id`, `key`, `default_value`, `label`)
VALUES
  ((SELECT `id` FROM `component_definition` WHERE class = 'Component_Dashboard_ImageSliderController'), 'componentname', 'Image Slider', 'Heading'),
  ((SELECT `id` FROM `component_definition` WHERE class = 'Component_Dashboard_ImageSliderController'), 'pixel',         '200',          'Max width/height'),
  ((SELECT `id` FROM `component_definition` WHERE class = 'Component_Dashboard_ImageSliderController'), 'src',           '',          'Source'),
  ((SELECT `id` FROM `component_definition` WHERE class = 'Component_Dashboard_ImageSliderController'), 'timing',        '5',            'Timing'),
  ((SELECT `id` FROM `component_definition` WHERE class = 'Component_Dashboard_ImageSliderController'), 'compactMode',   '1',            'Hide header');
