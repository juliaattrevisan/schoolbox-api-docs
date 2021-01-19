UPDATE config SET sequence=sequence+1 WHERE type_id =  (SELECT id FROM `lu_config_type` WHERE `type` = 'LMS Policy') AND sequence >= 11;


UPDATE config
SET description = 'The entered time will be used as the default due time when creating new activities. If left blank, 11:59pm will be used instead',
    value='23:59',
    sequence=11
WHERE name = 'lms_duetime_default';


