-- The tile source config is no longer used or required, and should be removed.

START TRANSACTION;

DELETE cci
FROM component_config_instance cci
INNER JOIN component_config_definition ccd on cci.component_config_definition_id = ccd.id
WHERE ccd.key = 'tileSource';

DELETE ccd FROM component_config_definition ccd WHERE ccd.key = 'tileSource';

COMMIT;
