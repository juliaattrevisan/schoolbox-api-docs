ALTER TABLE `rubric_descriptor`
  ADD INDEX `level_id` (`level_id`);

ALTER TABLE `rubric_descriptor`
  ADD CONSTRAINT `fk_rubric_descriptor_level_id`
    FOREIGN KEY (`level_id`) REFERENCES `rubric_level` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;
