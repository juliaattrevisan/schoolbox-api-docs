-- Replace 'size' with 'sizeNoAll'
UPDATE component_config_definition ccd
  JOIN component_definition cd ON cd.id = ccd.component_definition_id
SET
  ccd.`key` = 'sizeNoAll',
  ccd.default_value = '25',
  ccd.description = 'Size without the \'All\' option'
WHERE cd.class = 'Component_Homepage_ImageGalleryController'
      AND ccd.`key` = 'size';

-- Hard delete config instances that point to the now-invalid value.
-- This will cause the default value to be used instead at run-time
DELETE FROM component_config_instance WHERE id IN (SELECT id FROM (
  SELECT cci.id FROM component_config_instance cci
    JOIN component_config_definition ccd ON ccd.id = cci.component_config_definition_id
    JOIN component_definition cd ON cd.id = ccd.component_definition_id
  WHERE cci.value = 0
        AND cd.class = 'Component_Homepage_ImageGalleryController'
        AND ccd.`key` = 'sizeNoAll'
) t1 );
