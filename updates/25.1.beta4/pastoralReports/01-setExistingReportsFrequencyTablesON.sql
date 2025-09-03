-- Update all existing pastoral reports to include all summary table options
-- This scripts will only update reports that do not have any summary tables set (NULL)
-- to avoid overwriting any custom configurations that may already exist

UPDATE `pastoral_report`
SET `summary_tables` = 'studentByType,typeByMonth,typeByWeek,studentByMonth,studentByWeek'
WHERE `summary_tables` IS NULL;
