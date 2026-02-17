### (hard code the id to ConfigMapper::TYPE_DATA_RETENTION)
SET
    @dataRetention_config_id = 25;

### create new dataRetention area
INSERT INTO lu_config_type (id, type, sequence)
VALUES (@dataRetention_config_id, 'DataRetention', 24);

INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `options`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
           'dataRetention_ePortfolio',
           'Retention Period for ePortfolio',
           'Soft-deleted ePortfolio data will be permanently removed after this period.
Leave empty to retain data indefinitely.

ePortfolio data includes:
- ePortfolio page
- ePortfolio blogs
- ePortfolio blog comments
- ePortfolio blog images
- ePortfolio filelists
- ePortfolio permissions
- ePortfolio components',
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
           'Soft-deleted Learning Moments data will be permanently removed after this period.
Leave empty to retain data indefinitely.

Learning Moments data includes:
- Learning Moments
- Learning Moment Evidence',
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
           'Soft-deleted My Files data will be permanently removed after this period.
Leave empty to retain data indefinitely.

My Files data includes:
- My Folders
- My Files',
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
           'Soft-deleted Resources (CMS) data will be permanently removed after this period.
Leave empty to retain data indefinitely.

Resources (CMS) data includes:
- Resource folder
- Resource folder contents including, but not limited to,
  - Blogs
  - Chats
  - Files
  - Homepages
  - Links
  - Polls
  - Text boxes
  - User groups
  - Wikis',
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
           'Soft-deleted Student Submission data will be permanently removed after this period.
Leave empty to retain data indefinitely.

Student Submission data includes:
- Student Submissions
- Student Submission Files
  - Kami Documents
  - Plagscan Documents
  - Turnitin Reports',
           (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
           @dataRetention_config_id,
           '1;1 Day:30;30 Days:365;365 Days',
           'true',
           'false',
           'false',
           'settings',
           1
       );
