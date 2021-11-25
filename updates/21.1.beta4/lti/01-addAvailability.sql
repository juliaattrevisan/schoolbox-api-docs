ALTER TABLE lti_provider
    ADD available_for_component BOOL DEFAULT TRUE NULL,
    ADD available_for_assessment BOOL DEFAULT TRUE NULL;
