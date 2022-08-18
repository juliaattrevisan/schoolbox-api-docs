ALTER TABLE `sis_event_user_overrides`
CHANGE COLUMN `action` `action` ENUM('clear', 'clear_staff', 'add', 'subtract') NULL DEFAULT 'add' ;
