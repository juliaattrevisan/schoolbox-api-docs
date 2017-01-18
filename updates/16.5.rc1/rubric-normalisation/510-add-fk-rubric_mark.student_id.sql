ALTER TABLE `rubric_mark`
  ADD CONSTRAINT `fk_rubric_mark_student_id`
    FOREIGN KEY (`student_id`) REFERENCES `user` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;
