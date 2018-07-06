/**
 * Set rubric_criterion.rubric_group_id to non-nullable.
 *
 * If this raises an error, then there are some rubric_criterion rows without a rubric_group.
 * This is an error, and must be reported.
 */
ALTER TABLE rubric_criterion CHANGE COLUMN rubric_group_id rubric_group_id INT(11) NOT NULL;
