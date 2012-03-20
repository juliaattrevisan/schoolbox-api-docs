INSERT INTO config ( `name`, `value`, `title`, `description`, `field`,
                     `options`, `type`, `editable`, `hidden`, `required` )
VALUES ( 'restrict_parent_directory', '1', 'Restrict Parent Directory',
         'If ticked, parents can only see the parent directories for year levels in which they have a student, otherwise they can see all parent directories.',
         'boolean', 'true:false', 'Security', 'true', 'false', 'false' );