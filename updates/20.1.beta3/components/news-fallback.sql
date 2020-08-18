-- Remove all Dashboard config options for itemImage, itemLayout

DELETE cci
FROM component_config_instance cci
         INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
WHERE ccd.`key` IN ('itemImage','itemLayout');

DELETE FROM component_config_definition
WHERE `key` IN ('itemImage', 'itemLayout');
