ALTER TABLE `rubric_level`
  ADD CONSTRAINT `fk_rubric_level_box_id`
    FOREIGN KEY (`box_id`) REFERENCES `submission_box` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;
