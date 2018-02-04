ALTER TABLE `submission_box`
ADD `self_reviewability` ENUM('none', 'reflect-only', 'full') NOT NULL;
