ALTER TABLE `submission_box`
ADD `self_reviewable` ENUM('none', 'reflect-only', 'full') NOT NULL;
