ALTER TABLE `luEventType`
  CHANGE COLUMN `eventType` `eventType` VARCHAR(100) CHARACTER SET 'utf8mb4' NOT NULL ,
  ADD INDEX `event_type_idx` (`eventType` ASC);

ALTER TABLE `calendar_connector`
  ADD INDEX `status_idx` (`status` ASC),
  ADD INDEX `connector_idx` (`connector_class` ASC),
  ADD INDEX `type_idx` (`event_type` ASC);

ALTER TABLE `groups`
  ADD INDEX `group_type_idx` (`group_type` ASC),
  ADD INDEX `deleted_at_idx` (`deleted_at` ASC);
