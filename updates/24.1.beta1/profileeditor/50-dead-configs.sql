-- external_document_button_enable was superfluous and has been
-- combined with external_document_report_enabled

DELETE FROM config
WHERE name = 'external_document_button_enable' LIMIT 1;

-- email_all_staff was only ever used in conjunction with mail_enable
-- Arguably the latter's description suggests the existence of the former,
-- though schools will now lose this more surgical setting.

DELETE FROM config
WHERE name = 'email_all_staff' LIMIT 1;

-- email_all_parents was only ever used in conjunction with mail_enable
-- Arguably the latter's description suggests the existence of the former,
-- though schools will now lose this more surgical setting. It has some
-- extended references in Learning where it's used to refer to email groups.

DELETE FROM config
WHERE name = 'email_all_parents' LIMIT 1;
