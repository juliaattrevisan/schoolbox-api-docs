-- exernal ups provider class selection
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`) VALUES ('external_ups_provider', '', 'External UPS Provider', 'UPS will use this as its data source, instead of your external database, if set to something other than "default"', '7', 'default;Default:batchxml;Batch XML', '7', 'true', 'false', 'false', 'settings');

-- external ups provider batchxml host and key
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`) VALUES ('external_ups_provider_batchxml_host', '', 'External UPS Provider Batchxml Host', 'Source of xml feed', '1', null, '7', 'true', 'false', 'false', 'settings');
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`) VALUES ('external_ups_provider_batchxml_key', '', 'External UPS Provider Batchxml Key', 'API key for the xml feed', '1', null, '7', 'true', 'false', 'false', 'settings');
