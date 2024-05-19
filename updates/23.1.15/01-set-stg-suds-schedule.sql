UPDATE `config`
    SET `value` = (
        SELECT CASE
            WHEN `value` = "stg" THEN "daily"
            ELSE (SELECT `value` FROM `config` WHERE `name` = "schooldata_import_frequency")
        END AS frequency
        FROM `config`
        WHERE `name` = "site_type"
)
    WHERE `name` = "schooldata_import_frequency"
    AND `value` NOT in("NULL", "none");

UPDATE `config`
    SET `editable` = (
        SELECT CASE
            WHEN `value` = "stg" THEN "false"
            ELSE true
        END AS frequency
        FROM `config`
        WHERE `name` = "site_type"
)
    WHERE `name` = "schooldata_import_frequency"
    AND `value` NOT in("NULL", "none");

