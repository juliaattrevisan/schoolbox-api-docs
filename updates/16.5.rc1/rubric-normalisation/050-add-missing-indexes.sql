-- We're gonna need these ...
ALTER TABLE `rubric_descriptor`
  ADD INDEX `level_id` (`level_id`);

ALTER TABLE `rubric_mark`
  ADD INDEX `descriptor_id` (`descriptor_id`);