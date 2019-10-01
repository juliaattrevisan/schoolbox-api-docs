INSERT INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
    'external_document_button_enable',
    (
        SELECT c.value FROM config c
        WHERE c.name = 'external_document_report_enabled'
    ),
    'Display "Academic Reports" button on student profile',
    'If turned on "Academic Reports" button to the list of documents from External SIS document displays on the students profile page',
    (
        SELECT id FROM lu_config_field
        WHERE field = 'boolean'
    ),
    '',
    (
        SELECT id FROM lu_config_type
        WHERE type = 'Document Management'
    ),
    'true',
    'false',
    'false',
    'settings'
);
