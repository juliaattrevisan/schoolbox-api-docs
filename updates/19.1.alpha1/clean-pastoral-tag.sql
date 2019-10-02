-- recreate pastoral_tags table with active pastoral records & tags
CREATE TABLE tmp_pastoral_tags
    SELECT pastoral_id, tag_id FROM pastoral_tags
    WHERE deleted_at IS NULL
    GROUP BY pastoral_id, tag_id;
DROP TABLE pastoral_tags;
ALTER TABLE tmp_pastoral_tags RENAME pastoral_tags;
ALTER TABLE pastoral_tags
    ADD UNIQUE INDEX unique_pastoral_tag_idx (pastoral_id ASC, tag_id ASC);
ALTER TABLE pastoral_tags
    ADD CONSTRAINT fk_pastoral_tags_pastoral
        FOREIGN KEY (pastoral_id) REFERENCES pastoral (id)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;
ALTER TABLE pastoral_tags
    ADD CONSTRAINT fk_pastoral_tags_tag
        FOREIGN KEY (tag_id) REFERENCES lu_pastoral_tag (id)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;

-- delete tags that are not in use
UPDATE lu_pastoral_tag
  SET    `deleted_at` = NOW()
  WHERE  lu_pastoral_tag.`id` NOT IN (SELECT `tag_id` FROM `pastoral_tags`)
    AND  lu_pastoral_tag.deleted_at IS NULL;
