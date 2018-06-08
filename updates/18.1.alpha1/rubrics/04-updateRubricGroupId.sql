-- Step 1: insert rubric groups
INSERT INTO `rubric_group`
    (`name`, `parent_id`, `parent_type`, `deleted_at`)
SELECT DISTINCT
    `grouping`, `parent_id`, `parent_type`, MAX(`deleted_at`)
FROM `rubric_criterion`
WHERE `deleted_at` IS NOT NULL
GROUP BY `grouping`, `parent_id`, `parent_type`
ORDER BY `grouping`, `parent_id`, `parent_type`;

-- Step 2: Sync foreign key values
UPDATE `rubric_criterion` rc
INNER JOIN `rubric_group` rg ON rc.grouping = rg.name AND rc.parent_id = rg.parent_id AND rc.parent_type = rg.parent_type
SET rc.rubric_group_id = rg.id;

-- Step 3: Undelete groups of any non-deleted criteria
UPDATE `rubric_criterion` rc
INNER JOIN `rubric_group` rg ON rc.rubric_group_id = rg.id
SET rg.deleted_at = NULL
WHERE rc.deleted_at IS NULL;
