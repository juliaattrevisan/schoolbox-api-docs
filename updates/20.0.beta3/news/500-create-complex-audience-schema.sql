-- Create new complex table
CREATE TABLE comms_news_audience_complex(
    comms_news_article_id INT NOT NULL,
    entity_type_1         ENUM('user', 'super', 'campus', 'role', 'role_type', 'school_house', 'res_house', 'year', 'service', 'pastoral_group', 'folder') NOT NULL,
    entity_value_1        VARCHAR(32) NULL,
    entity_type_2         ENUM('user', 'super', 'campus', 'role', 'role_type', 'school_house', 'res_house', 'year', 'service', 'pastoral_group', 'folder') NOT NULL,
    entity_value_2        VARCHAR(32) NULL,
    CONSTRAINT fk_comms_news_audience_child_comms_news_audience_id FOREIGN KEY (`comms_news_article_id`) REFERENCES comms_news_article (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_comms_news_audience_complex_type_1 (entity_type_1),
    INDEX idx_comms_news_audience_complex_value_1 (entity_value_1),
    INDEX idx_comms_news_audience_complex_type_2 (entity_type_2),
    INDEX idx_comms_news_audience_complex_value_2 (entity_value_2),
    UNIQUE INDEX idx_comms_news_audience_complex_t1_v1_t2_v2(comms_news_article_id, entity_type_1, entity_value_1, entity_type_2, entity_value_2)
) CHARACTER SET utf8mb4;

-- Migrate records
INSERT INTO comms_news_audience_complex(comms_news_article_id, entity_type_1, entity_value_1, entity_type_2, entity_value_2)
    SELECT DISTINCT cna.comms_news_article_id, cna1.entity_type, cna1.entity_value, cna2.entity_type, cna2.entity_value
    FROM comms_news_audience cna
    JOIN comms_news_audience cna1 ON cna1.parent_id = cna.id
    JOIN comms_news_audience cna2 ON cna2.parent_id = cna.id AND cna1.id < cna2.id;

-- Delete complex audience children, then composite parent
DELETE FROM comms_news_audience WHERE parent_id IS NOT NULL OR entity_type = 'composite' ORDER BY entity_type = 'composite';

-- Remove old index and FK, and add new PK constraint (unique index + enables row ID)
ALTER TABLE comms_news_audience DROP FOREIGN KEY fk_comms_news_audience_comms_news_article_id;
ALTER TABLE comms_news_audience DROP FOREIGN KEY fk_comms_news_audience_parent_id;
ALTER TABLE comms_news_audience DROP INDEX idx_comms_news_audience_parent_type_value_relationship;
ALTER TABLE comms_news_audience DROP COLUMN id;
ALTER TABLE comms_news_audience DROP COLUMN parent_id;

-- We should be able to just do an 'ALTER IGNORE' to remove duplicates, but there is a bug in some version of 10.2.
-- This affects cloud which is running 10.2.21. It appears this is fixed in later versions, eg 10.2.31.
-- https://jira.mariadb.org/browse/MDEV-12968
ALTER IGNORE TABLE comms_news_audience ADD UNIQUE INDEX idx_comms_news_audience_article_type_value_relationship (comms_news_article_id, entity_type, entity_value, relationship), ALGORITHM=COPY;

