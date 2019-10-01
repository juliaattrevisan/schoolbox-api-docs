UPDATE config
    SET title = regexp_replace(title, 'Academic Report', 'Document')
    WHERE NAME IN ('academic_reports_wysiwyg', 'academic_reports_available_to_students', 'external_document_report_minimum_date');
UPDATE config
    SET title = 'DocMan Categories To Show'
    WHERE NAME = 'external_document_docman_categories';
UPDATE config
    SET description = regexp_replace(description, 'academic report', 'document')
    WHERE NAME = 'external_document_report_minimum_date';
UPDATE config
    SET
        title = 'Enable Document Management',
        description = 'Enable the display of documents from External SIS document management feature'
    WHERE NAME = 'external_document_report_enabled';
