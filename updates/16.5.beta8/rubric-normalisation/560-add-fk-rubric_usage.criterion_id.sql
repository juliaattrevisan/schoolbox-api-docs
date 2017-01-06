# ALTER TABLE `rubric_usage`
#   ADD CONSTRAINT `fk_rubric_usage_criterion_id`
#   FOREIGN KEY (`criterion_id`) REFERENCES `rubric_criterion` (`id`)
#     ON DELETE NO ACTION
#     ON UPDATE NO ACTION;

