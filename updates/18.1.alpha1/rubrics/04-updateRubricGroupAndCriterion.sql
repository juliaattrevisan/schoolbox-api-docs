-- Populate rubric_group table.
-- If and only if the criteria grouping has only deleted criteria:
-- the grouping is deleted at the time its most recently deleted
-- criterion was deleted.
-- It is not deleted if it has any non-deleted criteria.
INSERT INTO rubric_group
    (`name`, `parent_id`, `parent_type`, `deleted_at`)
SELECT
    `grouping`,
    `parent_id`,
    `parent_type`,
    IF (MAX(`deleted_at` IS NULL) = 0, MAX(`deleted_at`), NULL) AS `deleted_at`
FROM `rubric_criterion`
WHERE `rubric_group_id` IS NULL
GROUP BY `grouping`, `parent_id`, `parent_type`
ORDER BY `parent_type`, `parent_id`, `grouping`;

-- Associate criteria with their group
UPDATE rubric_criterion rc
INNER JOIN rubric_group rg ON rc.grouping = rg.name AND rc.parent_id = rg.parent_id AND rc.parent_type = rg.parent_type
SET rc.rubric_group_id = rg.id;
