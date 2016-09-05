UPDATE `config` SET `options` = 'synergetic:tass:denbigh:edumate:schoolbox:schoolpro:maze:pcschools:humanedge:doublefirst'
WHERE `name` = 'external_type';

ALTER TABLE `luCampus` MODIFY `ext_timetable_code` VARCHAR(255);