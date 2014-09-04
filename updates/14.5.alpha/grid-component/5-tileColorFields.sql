-- Increase size of color fields to fit a full CSS color spec
ALTER TABLE `tile`
    CHANGE `title_color`
        `title_color` VARCHAR(30)
        CHARSET utf8
        COLLATE utf8_general_ci
        NOT NULL
        COMMENT 'CSS color value',
    CHANGE `title_background_color`
        `title_background_color`
        VARCHAR(30)
        CHARSET utf8
        COLLATE utf8_general_ci
        NOT NULL
        COMMENT 'CSS color value',
    CHANGE `background_color`
        `background_color`
        VARCHAR(30)
        CHARSET utf8
        COLLATE utf8_general_ci
        NOT NULL
        COMMENT 'RRGGBBAA'
;
