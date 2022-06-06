INSERT IGNORE INTO config (
    name,
    value,
    title,
    description,
    field_id,
    options,
    editable,
    hidden,
    required,
    module
) VALUES (
    'tigs_crp_work_type_deans_comment',
    '',
    'Work Type - Dean''s Comment',
    'Marks given to assessments of this work type on the student''s year group page will populate their progress report''s Dean''s Comment (preferentially over a pastoral record).',
    (SELECT id FROM lu_config_field WHERE field = 'selectLookup'),
    'a:1:{s:6:"mapper";s:45:"Schoolbox\\Learning\\Assessment\\WorkType\\Mapper";}', # look up the work type
    'true',
    'false',
    'true',
    'custom'
), (
    'tigs_crp_year_group_class_code_suffix',
    '',
    'Year Group Class Code Suffix',
    'Year group classes will have (or have had) a class code with this suffix: it will be prefixed by the year level (e.g. 10xxx).',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    '',
    'true',
    'false',
    'true',
    'custom'
);
