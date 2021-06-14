/**
 * Somehow on some instances `component_config_definition.default_value` isn't
 * nullable: fix that here.
 */
ALTER TABLE component_config_definition
    MODIFY default_value VARCHAR(128) CHARSET utf8mb4 NULL;
