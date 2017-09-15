UPDATE `config`
SET `description` = 'Comma separated list of categories used in Synergetic DocMan to categorise documents as academic reports. If blank, no DocMan documents will be included.'
WHERE `name` = 'external_document_docman_categories';