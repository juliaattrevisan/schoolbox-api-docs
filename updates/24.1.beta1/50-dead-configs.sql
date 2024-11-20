-- external_document_button_enable was superfluous and has been
-- combined with external_document_report_enabled

DELETE FROM config
WHERE name = 'external_document_button_enable' LIMIT 1;
