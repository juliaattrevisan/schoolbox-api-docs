INSERT INTO `eotc_config` (`name`) VALUES ('rams_warning');

-- Set SK's EOTC config
UPDATE `eotc_config`
SET `value` = '<p>
    The organiser is required to submit a completed <strong>RAMS</strong>
    (Risk Analysis Management System) form if the EOTC is associated with
    certain activities which are deemed to have significant associated risks.
    These include: Activities close to or on <strong>WATER</strong>;
    <strong>TRAMPS</strong>; <strong>TRIPS</strong> (overseas, outside of
    Auckland, as well as requiring activities outdoors or fieldwork);
    <strong>CAMPS</strong>; <strong>OUTDOOR EDUCATION</strong> and
    <strong>PRESCHOOL</strong> (all excursions off-site).
</p>'
WHERE `name` = 'rams_warning' AND (SELECT `value` FROM `config` WHERE `name` = 'site_location') = 'sk';
