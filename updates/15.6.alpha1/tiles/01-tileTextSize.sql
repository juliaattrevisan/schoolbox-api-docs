ALTER TABLE `tile`
    ADD COLUMN `title_text_min_size` MEDIUMINT(3)
        DEFAULT 75 NOT NULL
        COMMENT 'Min text size as a % of default font size'
        AFTER `title_text`,
    ADD COLUMN `title_text_max_size` MEDIUMINT(3)
        DEFAULT 175 NOT NULL
        COMMENT 'Max text size as a % of default font size'
        AFTER `title_text_min_size`;
