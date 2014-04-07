ALTER TABLE `groups` CHANGE `digest_schedule`
`digest_schedule` VARCHAR(24) DEFAULT 0  NOT NULL  COMMENT '0: Never, 1: Weekly, 2: Daily, [pattern]: Crontab';
