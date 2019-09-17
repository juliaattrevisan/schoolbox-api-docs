-- BatchXML is no longer an option
-- schoolbox connector took over a lot of BatchXML's original duty

-- BatchXML Parent Campuses for ISAMS is no longer required
DELETE FROM ups_custom_hydrator
WHERE class = 'Schoolbox\\Sync\\Hydrator\\Custom\\BatchxmlParentCampuses';

-- Having spoke with Duane we probably don't need that config option at all
DELETE FROM config where name = 'external_ups_provider';
