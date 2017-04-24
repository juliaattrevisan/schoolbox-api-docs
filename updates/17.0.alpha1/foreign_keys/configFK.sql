ALTER TABLE `schoolbox`.`config`
  ADD CONSTRAINT `fk_config_field_id`
FOREIGN KEY (`field_id`)
REFERENCES `schoolbox`.`lu_config_field` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_config_type_id`
FOREIGN KEY (`type_id`)
REFERENCES `schoolbox`.`lu_config_type` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
