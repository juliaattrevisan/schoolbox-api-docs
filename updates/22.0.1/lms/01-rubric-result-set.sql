-- Updates rubric_result to have 1024 chars,
-- bringing it on parity with rubric_descriptor.description

ALTER TABLE `rubric_result`
CHANGE COLUMN `label` `label` VARCHAR(1024) NULL DEFAULT NULL ;
