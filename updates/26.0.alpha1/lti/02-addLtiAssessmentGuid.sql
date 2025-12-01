ALTER TABLE lti_assessment
    ADD guid UUID NULL AFTER id,
    ADD INDEX lti_assessment_guid_index (guid);
