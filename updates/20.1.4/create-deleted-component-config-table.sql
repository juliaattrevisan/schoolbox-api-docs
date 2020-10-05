-- This is for DB which has already run updates/20.1.alpha1/components/01-add-component-config.sql
-- and thus does not yet have _deleted_component_config_definition and _deleted_component_config_instance tables
CREATE TABLE IF NOT EXISTS _deleted_component_config_instance
SELECT *
FROM component_config_instance LIMIT 0;
CREATE TABLE IF NOT EXISTS _deleted_component_config_definition
SELECT *
FROM component_config_definition LIMIT 0;
