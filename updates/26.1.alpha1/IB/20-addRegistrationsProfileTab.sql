-- Adding Registrations Profile Tab for the IBIS Registration

UPDATE `profile_tab`
SET `sequence` = `sequence` + 1
WHERE `roletype` = 'student'
AND `tab` <> 'registration'
AND NOT EXISTS (
    SELECT 1
    FROM (
        SELECT 1
        FROM `profile_tab`
        WHERE `roletype` = 'student'
        AND `tab` = 'registration'
    ) AS `existing_registrations_profile_tab`
);

INSERT INTO `profile_tab` (`roletype`, `tab`, `display_as`, `sequence`)
SELECT 'student', 'registration', 'Registration', 1
WHERE NOT EXISTS (
    SELECT 1
    FROM `profile_tab`
    WHERE `roletype` = 'student'
    AND `tab` = 'registration'
);

UPDATE `profile_tab`
SET `sequence` = 1
WHERE `roletype` = 'student'
AND `tab` = 'registration';

SET @registration_profile_tab_id = (
    SELECT `id`
    FROM `profile_tab`
    WHERE `roletype` = 'student'
    AND `tab` = 'registration'
);

INSERT INTO `profile_tab_campus_availability` (`profile_tab_id`, `campus_id`)
SELECT @registration_profile_tab_id, `luCampus`.`id`
FROM `luCampus`
WHERE NOT EXISTS (
    SELECT 1
    FROM `profile_tab_campus_availability`
    WHERE `profile_tab_id` = @registration_profile_tab_id
    AND `campus_id` = `luCampus`.`id`
);

INSERT INTO `profile_tab_roletype_visibility` (`profile_tab_id`, `roletype`)
SELECT @registration_profile_tab_id, `registration_profile_tab_visibility`.`roletype`
FROM (
    SELECT 'self' AS `roletype`
    UNION ALL
    SELECT 'staff'
) AS `registration_profile_tab_visibility`
WHERE NOT EXISTS (
    SELECT 1
    FROM `profile_tab_roletype_visibility`
    WHERE `profile_tab_id` = @registration_profile_tab_id
    AND `roletype` = `registration_profile_tab_visibility`.`roletype`
);
