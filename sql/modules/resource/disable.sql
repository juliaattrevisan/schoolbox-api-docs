UPDATE `config` SET `value` = '0' WHERE `name` = 'resource_booking_enabled';
UPDATE `component_definition` SET `visible` = 'false' WHERE `name` = 'Resource Management' AND `module` = 'dashboard';
