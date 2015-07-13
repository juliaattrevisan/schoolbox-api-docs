-- Not used anymore, so remove it
DELETE `cci` FROM `component_config_instance` `cci`
    INNER JOIN `component_config_definition` `ccd` ON `cci`.`component_config_definition_id` = `ccd`.`id`
    INNER JOIN `component_definition` `cd` ON `ccd`.`component_definition_id` = `cd`.`id`
WHERE `cd`.`class` = 'Component_Dashboard_OrganisationController';

DELETE `ci` FROM component_instance `ci`
    INNER JOIN `component_definition` `cd` ON `ci`.`component_definition_id` = `cd`.`id`
WHERE `cd`.`class` = 'Component_Dashboard_OrganisationController';

DELETE `ccd` FROM component_config_definition `ccd`
    INNER JOIN `component_definition` `cd` ON `ccd`.`component_definition_id` = `cd`.`id`
WHERE `cd`.`class` = 'Component_Dashboard_OrganisationController';

DELETE `cd` FROM `component_definition` `cd`
WHERE `cd`.`class` = 'Component_Dashboard_OrganisationController';
