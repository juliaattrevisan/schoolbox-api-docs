/**
 * Defaults are applicable to existing tools (which are all LTI 1.1),
 * though unused and unchangeable for those tools:
 *
 * * Always usable in components
 * * Never usable in assessments
 */
ALTER TABLE lti_provider
    ADD available_for_component BOOL DEFAULT TRUE NULL,
    ADD available_for_assessment BOOL DEFAULT FALSE NULL;
