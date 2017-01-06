ALTER TABLE `rubric_usage`
  ADD CONSTRAINT `fk_rubric_usage_box_id`
    FOREIGN KEY (`submission_box_id`) REFERENCES `submission_box` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;

