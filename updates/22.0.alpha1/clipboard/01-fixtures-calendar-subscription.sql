--$config = (new \Calendar_Api_Config_Connector_Fixtures())
--    ->setLabel('Fixtures')
--    ->setEventType('Event')
--    ->setLuEventType(1);
--
--$record = \Calendar_Api_Config_Mapper::getInstance()
--    ->createModel()
--    ->setEventType('Fixtures')
--    ->setConnectorClass('Schoolbox')
--    ->deactivate()
--    ->encodeConfig($config);
--
--\Calendar_Api_Config_Mapper::getInstance()
--    ->save($record);
--
-- This produces encoded config string which we ram in
-- TzozODoiQ2FsZW5kYXJfQXBpX0NvbmZpZ19Db25uZWN0b3JfRml4dHVyZXMiOjY6e3M6MTc6IgAqAGNvbm5lY3RvckNsYXNzIjtzOjg6IkZpeHR1cmVzIjtzOjU6IgAqAGlkIjtpOi0xO3M6ODoiACoAbGFiZWwiO3M6ODoiRml4dHVyZXMiO3M6MTI6IgAqAGV2ZW50VHlwZSI7czo1OiJFdmVudCI7czoxNDoiACoAbHVFdmVudFR5cGUiO3M6MToiMSI7czoxNzoiACoAaGFzUGVybWlzc2lvbnMiO2I6MDt9

INSERT INTO calendar_connector
(`event_type`, `connector_class`, `config`, `status`)
VALUES
(
    'Fixtures',
    'Schoolbox',
    'TzozODoiQ2FsZW5kYXJfQXBpX0NvbmZpZ19Db25uZWN0b3JfRml4dHVyZXMiOjY6e3M6MTc6IgAqAGNvbm5lY3RvckNsYXNzIjtzOjg6IkZpeHR1cmVzIjtzOjU6IgAqAGlkIjtpOi0xO3M6ODoiACoAbGFiZWwiO3M6ODoiRml4dHVyZXMiO3M6MTI6IgAqAGV2ZW50VHlwZSI7czo1OiJFdmVudCI7czoxNDoiACoAbHVFdmVudFR5cGUiO3M6MToiMSI7czoxNzoiACoAaGFzUGVybWlzc2lvbnMiO2I6MDt9',
    0
);