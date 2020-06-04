-- All the relevant parts joined up
-- You can use this to check whether anything remained
--
-- This particular query assumes you saved the component's config at least once tho,
-- otherwise leftmost table won't have any rows

-- SELECT *
-- FROM component_config_instance cci
--          LEFT JOIN component_config_definition ccd ON ccd.id = cci.component_config_definition_id
--          LEFT JOIN component_instance ci ON cci.component_instance_id = ci.id
--          LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
-- WHERE cd.class = 'Component_Homepage_CalendarListController';


-- Delete config instances associated with Campus Calendar limits
-- There's only one config: limits

DELETE cci
FROM component_config_instance cci
LEFT JOIN component_config_definition ccd ON ccd.id = cci.component_config_definition_id
LEFT JOIN component_instance ci ON cci.component_instance_id = ci.id
LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
WHERE cd.class = 'Component_Homepage_CalendarListController';

-- Delete config definitions associated with Campus Calendar
-- There's only one config: limits

DELETE ccd
FROM component_config_definition ccd
LEFT JOIN component_definition cd ON ccd.component_definition_id = cd.id
WHERE cd.class = 'Component_Homepage_CalendarListController';

-- Delete component instances associated with Campus Calendar

DELETE ci
FROM component_instance ci
LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
WHERE cd.class = 'Component_Homepage_CalendarListController';

-- Delete component definition associated with Campus Calendar

DELETE
FROM component_definition
WHERE component_definition.class = 'Component_Homepage_CalendarListController';
