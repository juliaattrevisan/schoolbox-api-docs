
/* Category hierarchy for Assess Question Bank Categories
 */
ALTER TABLE `assess_question_category`
  ADD COLUMN `parent_id` BIGINT(20) NULL AFTER `question_category_id`;

UPDATE assess_question_category SET parent_id = 0;

/* To allow soft-delete of Questionbank Categories (otherwise you might lose questions)
 */
ALTER TABLE `assess_question_category`
  ADD COLUMN `created_at` DATETIME NULL AFTER `availability`,
  ADD COLUMN `updated_at` DATETIME NULL AFTER `created_at`,
  ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`;

UPDATE assess_question_category SET created_at = NOW(), updated_at = NOW();
