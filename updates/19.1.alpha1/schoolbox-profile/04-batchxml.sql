-- BatchXML is no longer an option
-- schoolbox connector took over a lot of BatchXML's original duty

-- BatchXML Parent Campuses for ISAMS is no longer required
DELETE FROM ups_custom_hydrator
WHERE class = 'Schoolbox\\Sync\\Hydrator\\Custom\\BatchxmlParentCampuses';

-- External UPS Provider only uses the default connector behaviour
-- there are no custom behaviours anymore
-- Hide this config option for now
UPDATE `config`
SET `value`='', `options`='default;Default', `hidden`='true'
WHERE `name`='external_ups_provider';
