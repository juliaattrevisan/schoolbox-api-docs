UPDATE `config`
SET
  `field_id`=15,
  `options`=NULL,
  `value`='06:00'
WHERE `name`='calendar_start_time';

INSERT INTO `lu_config_field` (`field`) VALUES ('timepicker');