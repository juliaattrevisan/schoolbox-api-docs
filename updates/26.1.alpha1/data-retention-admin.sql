### create new dataRetention area
INSERT INTO lu_config_type (id, type, sequence)
VALUES (25, 'DataRetention', 24);

SET
@dataRetention_config_id = (
        SELECT id
        FROM lu_config_type
        WHERE
            type = 'DataRetention'
    );



INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `options`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
           'dataRetention_ePortfolio',
           'Retention Period for ePortfolio',
           'How long should soft deleted ePortfolios be retained?',
           (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
           @dataRetention_config_id,
           '1;1 Day:30;30 Days:365;365 Days',
           'true',
           'false',
           'false',
           'settings',
           1
       ),
       (
           'dataRetention_LearningMoments',
           'Retention Period for Learning Moments',
           'How long should soft deleted Learning Moments be retained?',
           (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
           @dataRetention_config_id,
           '1;1 Day:30;30 Days:365;365 Days',
           'true',
           'false',
           'false',
           'settings',
           1
       ),
       (
           'dataRetention_MyFiles',
           'Retention Period for My Files',
           'How long should soft deleted My Files be retained?',
           (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
           @dataRetention_config_id,
           '1;1 Day:30;30 Days:365;365 Days',
           'true',
           'false',
           'false',
           'settings',
           1
       ),
       (
           'dataRetention_CMS',
           'Retention Period for Resources (CMS)',
           'How long should soft deleted Resources (CMS) be retained?',
           (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
           @dataRetention_config_id,
           '1;1 Day:30;30 Days:365;365 Days',
           'true',
           'false',
           'false',
           'settings',
           1
       ),
       (
           'dataRetention_StudentSubmissions',
           'Retention Period for Student Submissions',
           'How long should soft deleted Student Submissions be retained?',
           (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
           @dataRetention_config_id,
           '1;1 Day:30;30 Days:365;365 Days',
           'true',
           'false',
           'false',
           'settings',
           1
       );
