/* Field added to remember the last view the user...viewed */
ALTER TABLE `user`
  ADD COLUMN `calendar_view` ENUM('month','agendaWeek','agendaDay') DEFAULT 'month' NOT NULL COMMENT 'Remembers the last view of the calendar the user used. To be refactored into preferences system.';
