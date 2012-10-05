UPDATE `config` SET `description`='Use this to control your automatic sync script, only use this if you have a sync script installed for your service' WHERE `configID`='57';


INSERT INTO `config` (`name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`, `required`) VALUES
('sync_patterns', '', 'Sync User Types', '', 'input', NULL, 'External DB', 'false', 'false', 'true');
