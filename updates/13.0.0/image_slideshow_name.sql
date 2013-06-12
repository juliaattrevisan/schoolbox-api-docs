-- Set image scroller's component name to "image slideshow"
UPDATE component_definition AS cd
  INNER JOIN component_config_definition AS cc ON cd.id = cc.component_definition_id
SET cc.default_value='Image Slideshow'
WHERE cd.class = 'Component_Homepage_ScrollerController' AND cc.key = 'componentname';
