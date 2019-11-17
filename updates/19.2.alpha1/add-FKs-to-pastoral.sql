-- Set pastoral.parent_id to NULL if it contains a value that is not in pastoral.id
UPDATE pastoral pc
    LEFT JOIN pastoral pp ON pp.id = pc.parent_id
SET pc.parent_id = NULL
WHERE pc.parent_id IS NOT NULL
  AND pp.id IS NULL;

-- Set pastoral_revision.parent_id to NULL if it contains a value that is not in pastoral.id
UPDATE `pastoral_revision`
SET `parent_id` = null
WHERE `parent_id` NOT IN (SELECT `id` FROM `pastoral`);


ALTER TABLE `pastoral`
    ADD CONSTRAINT `fk_pastoral_parent_id`
        FOREIGN KEY (`parent_id`)
            REFERENCES `pastoral` (`id`);

ALTER TABLE `pastoral_revision`
    ADD CONSTRAINT `fk_pastoral_revision_parent_id`
        FOREIGN KEY (`parent_id`)
            REFERENCES `pastoral` (`id`);
