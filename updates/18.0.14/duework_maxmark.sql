UPDATE `submission_box` SET `mark_type`=1 WHERE `mark_type`=3 AND (`max_mark` IS NULL OR `max_mark` <= 0);
