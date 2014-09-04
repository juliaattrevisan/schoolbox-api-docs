-- Add a hover color
ALTER TABLE `tile`
    ADD `hover_color` VARCHAR(30)
        CHARSET utf8
        COLLATE utf8_general_ci
        NOT NULL
        COMMENT 'CSS color value'
        AFTER `background_color`
;
