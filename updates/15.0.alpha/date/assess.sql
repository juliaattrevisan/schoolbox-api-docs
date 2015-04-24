ALTER TABLE `assess_attempt` CHANGE `finish` `finish` DATETIME NULL;
UPDATE assess_attempt SET finish = NULL WHERE finish = '0000-00-00 00:00:00'