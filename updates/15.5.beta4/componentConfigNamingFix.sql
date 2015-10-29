-- Rename "Size" to "Limit" for social stream and collabdocs
UPDATE `component_config_definition` ccd
INNER JOIN `component_definition` cd ON ccd.`component_definition_id` = cd.`id`
SET `ccd`.`label`='Limit'
WHERE `ccd`.`key`='size'
AND `cd`.`class` IN ('Component_Homepage_SocialStreamController', 'Component_Homepage_EtherpadController');
