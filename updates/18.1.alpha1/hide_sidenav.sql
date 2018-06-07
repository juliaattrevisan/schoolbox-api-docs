ALTER TABLE `user`
  ADD COLUMN `hide_sidenav` TINYINT(1) NULL DEFAULT 0 AFTER `hide_timetable`;
