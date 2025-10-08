SET @tourUuidAdmin = (
    SELECT t.uuid
    FROM tour t
    JOIN tour_trigger tt ON tt.tour_uuid = t.uuid
    WHERE t.title = '24.1 Release'
    AND tt.url_regex = '^/adminv2/home'
    AND t.deleted_at IS NULL
);

UPDATE tour SET
title = '24.1 Admin Release',
description = 'Introduce superusers and moderators to the new admin features of the 24.1 release'
WHERE uuid = @tourUuidAdmin;
