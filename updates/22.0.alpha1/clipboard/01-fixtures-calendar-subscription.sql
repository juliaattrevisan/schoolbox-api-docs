--$config = (new \Calendar_Api_Config_Connector_Fixtures())
--    ->setLabel('Fixtures')
--    ->setEventType('Fixtures') // This is actually the classname corresponding to Calendar/Api/Config/Connector/Fixtures.php
--    ->setLuEventType(1);
--
--$record = \Calendar_Api_Config_Mapper::getInstance()
--    ->createModel()
--    ->setEventType('Fixtures') // This is actually the classname corresponding to Calendar/Api/Config/Connector/Fixtures.php
--    ->setConnectorClass('Schoolbox')
--    ->deactivate()
--    ->encodeConfig($config);
--
--\Calendar_Api_Config_Mapper::getInstance()
--    ->save($record);
--
-- This produces encoded config string which we ram in
-- TzozODoiQ2FsZW5kYXJfQXBpX0NvbmZpZ19Db25uZWN0b3JfRml4dHVyZXMiOjY6e3M6MTc6IgAqAGNvbm5lY3RvckNsYXNzIjtzOjk6IlNjaG9vbGJveCI7czo1OiIAKgBpZCI7aTotMTtzOjg6IgAqAGxhYmVsIjtzOjg6IkZpeHR1cmVzIjtzOjEyOiIAKgBldmVudFR5cGUiO3M6ODoiRml4dHVyZXMiO3M6MTQ6IgAqAGx1RXZlbnRUeXBlIjtzOjE6IjEiO3M6MTc6IgAqAGhhc1Blcm1pc3Npb25zIjtiOjA7fQ==

INSERT INTO calendar_connector
(`event_type`, `connector_class`, `config`, `status`)
VALUES
(
    'Fixtures',
    'Schoolbox',
    'TzozODoiQ2FsZW5kYXJfQXBpX0NvbmZpZ19Db25uZWN0b3JfRml4dHVyZXMiOjY6e3M6MTc6IgAqAGNvbm5lY3RvckNsYXNzIjtzOjk6IlNjaG9vbGJveCI7czo1OiIAKgBpZCI7aTotMTtzOjg6IgAqAGxhYmVsIjtzOjg6IkZpeHR1cmVzIjtzOjEyOiIAKgBldmVudFR5cGUiO3M6ODoiRml4dHVyZXMiO3M6MTQ6IgAqAGx1RXZlbnRUeXBlIjtzOjE6IjEiO3M6MTc6IgAqAGhhc1Blcm1pc3Npb25zIjtiOjA7fQ==',
    0
);