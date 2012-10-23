UPDATE `config` SET `description`='Use this to control your automatic sync script, only use this if you have a sync script installed for your service' WHERE `name`='external_sync_data';


INSERT INTO `config` (`name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`, `required`) VALUES
('sync_patterns', '', 'Sync User Types', 'User strategies for synchronisation, must be set by Alaress', 'input', NULL, 'External DB', 'false', 'false', 'true');
