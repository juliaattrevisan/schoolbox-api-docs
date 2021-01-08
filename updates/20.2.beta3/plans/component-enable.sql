alter table component_definition
    add enabled enum('true', 'false') default 'true' null after component_category
