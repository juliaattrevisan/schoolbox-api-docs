ALTER TABLE `rubric_mark`
  ADD INDEX `descriptor_id` (`descriptor_id`);

ALTER TABLE `rubric_mark`
  ADD CONSTRAINT `fk_rubric_mark_descriptor_id`
    FOREIGN KEY (`descriptor_id`) REFERENCES `rubric_descriptor` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;

