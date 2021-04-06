/** Add parent_comments_allowed flag to pastoral record
 */
ALTER TABLE pastoral
    ADD COLUMN parent_comments_allowed TINYINT(1) DEFAULT 0 AFTER points;
