ALTER TABLE `user`
CHANGE COLUMN `calendar_view` `calendar_view` ENUM('month', 'agendaWeek', 'agendaDay', 'list') COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'month' ;
