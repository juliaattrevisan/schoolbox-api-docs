/**
 * Submission boxes were being created with late date set as the same as start,
 * when imported via the course importer.
 *
 * This ought to fix that.
 */

UPDATE `submission_box` SET `late_date` = `date` WHERE `late_date` < `date`;
