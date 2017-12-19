-- Create demo data required for feature tour testing

-- Staff dashboard feature tour

SET @tourStaff := UUID();

SET @sql :=

CASE WHEN EXISTS (SELECT * FROM `config` WHERE `name` = 'site_location' AND `value` = 'demo') THEN

  '
      INSERT INTO tour (
          `uuid`,
          `author_user_id`,
          `title`,
          `description`,
          `active`,
          `created_at`,
          `updated_at`,
          `deleted_at`
      ) VALUES (
          @tourStaff,
          (SELECT `id` FROM `user` WHERE `username` = ''alaress'' LIMIT 1),
          ''Staff Dashboard Tour'',
          ''This is a demonstration of a feature tour targeted to the staff dashboard'',
          1,
          ''1980-04-07 00:00:00'',
          ''1980-04-07 00:00:00'',
          NULL
      );
  '

ELSE ''

END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Staff dashboard feature tour audience

SET @sql :=

CASE WHEN EXISTS (SELECT * FROM `config` WHERE `name` = 'site_location' AND `value` = 'demo') THEN

  '
      INSERT INTO   `tour_audience_role`
      (
                    `author_user_id`,
                    `tour_uuid`,
                    `role_id`
      )
      SELECT        (SELECT `id` FROM `user` WHERE `username` = ''alaress'' LIMIT 1),
                    @tourStaff,
                    `id`
      FROM          `groups`
      WHERE         `group_type` = ''staff'';
  '

ELSE ''

END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
