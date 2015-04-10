-- datetime fixes for EOTC events
ALTER TABLE `eotc_event`
    ADD COLUMN `start_datetime` DATETIME NULL AFTER `startTime`,
    ADD COLUMN `end_datetime` DATETIME NULL AFTER `endTime`
;

ALTER TABLE `eotc_event`
    CHANGE `startDate` `startDate` DATE NULL COMMENT 'deprecated',
    CHANGE `startTime` `startTime` TIME NULL COMMENT 'deprecated',
    CHANGE `endDate` `endDate` DATE NULL COMMENT 'deprecated',
    CHANGE `endTime` `endTime` TIME NULL COMMENT 'deprecated'
;

UPDATE `eotc_event` SET
    start_datetime = CONCAT(startDate,' ',startTime),
    end_datetime = CONCAT(endDate,' ',endTime)
;

alter table `eotc_event`
    add index (`start_datetime`),
    add index (`end_datetime`)
;
