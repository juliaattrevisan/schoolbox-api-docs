SET @tourUuidStaff = (
    SELECT t.uuid
    FROM tour t
    JOIN tour_trigger tt ON tt.tour_uuid = t.uuid
    WHERE t.title = '24.1 Release'
    AND tt.url_regex NOT LIKE "%adminv2%"
);

SET @tourUuidAdmin = (
    SELECT t.uuid
    FROM tour t
    JOIN tour_trigger tt ON tt.tour_uuid = t.uuid
    WHERE t.title = '24.1 Release'
    AND tt.url_regex LIKE "%adminv2%"
);

UPDATE tour SET
title = '24.1 Release (displayed to staff users on login)',
description = 'Introduce staff to the new features of the 24.1 release'
WHERE uuid = @tourUuidStaff;

UPDATE tour SET
title = '24.1 Release (displayed on Admin Home page only)',
description = 'Introduce superusers and moderators to the new admin features of the 24.1 release'
WHERE uuid = @tourUuidAdmin;
