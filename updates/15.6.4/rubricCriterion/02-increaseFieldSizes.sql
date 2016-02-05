ALTER TABLE `rubric_criterion`
    CHANGE `grouping` `grouping` VARCHAR(1000) CHARSET utf8 COLLATE utf8_general_ci NOT NULL,
    CHANGE `name` `name` VARCHAR(1000) CHARSET utf8 COLLATE utf8_general_ci NULL
;
