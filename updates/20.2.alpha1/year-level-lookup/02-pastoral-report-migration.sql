/* Make a backup of pastoral_report.years into pastoral_report._years
 */
ALTER TABLE `pastoral_report`
    ADD COLUMN `_years` TEXT NULL DEFAULT NULL AFTER `years`;

UPDATE pastoral_report
SET _years = years;
