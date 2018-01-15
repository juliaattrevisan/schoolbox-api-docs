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

ELSE 'SELECT TRUE'

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
                    `moderator_user_id`,
                    `tour_uuid`,
                    `role_id`
      )
      SELECT        (SELECT `id` FROM `user` WHERE `username` = ''alaress'' LIMIT 1),
                    @tourStaff,
                    `id`
      FROM          `groups`
      WHERE         `group_type` = ''staff'';
  '

ELSE 'SELECT TRUE'

END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Staff dashboard feature tour trigger

SET @sql :=

CASE WHEN EXISTS (SELECT * FROM `config` WHERE `name` = 'site_location' AND `value` = 'demo') THEN

  '
      INSERT INTO   `tour_trigger`
      (
                    `uuid`,
                    `author_user_id`,
                    `tour_uuid`,
                    `selector`,
                    `event`,
                    `url_regex`,
                    `created_at`,
                    `updated_at`,
                    `deleted_at`
      )
      SELECT        UUID(),
                    (SELECT `id` FROM `user` WHERE `username` = ''alaress'' LIMIT 1),
                    @tourStaff,
                    ''body'',
                    ''tourReady'',
                    ''^/$'',
                    ''1980-04-07 00:00:00'',
                    ''1980-04-07 00:00:00'',
                    NULL
  '

ELSE ''

END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Staff dashboard feature tour steps

SET @step1Uuid = UUID();
SET @step2Uuid = UUID();

SET @sql :=

CASE WHEN EXISTS (SELECT * FROM `config` WHERE `name` = 'site_location' AND `value` = 'demo') THEN

  '
      INSERT INTO   `tour_step`
      (
                    `uuid`,
                    `tour_uuid`,
                    `next_step_uuid`,
                    `author_user_id`,
                    `type`,
                    `created_at`,
                    `updated_at`,
                    `deleted_at`
      )
      SELECT        @step2Uuid,
                    @tourStaff,
                    null,
                    (SELECT `id` FROM `user` WHERE `username` = ''alaress'' LIMIT 1),
                    ''tooltip'',
                    ''1980-04-07 00:00:00'',
                    ''1980-04-07 00:00:00'',
                    NULL
  '

ELSE ''

END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql :=

CASE WHEN EXISTS (SELECT * FROM `config` WHERE `name` = 'site_location' AND `value` = 'demo') THEN

  '
      INSERT INTO   `tour_step`
      (
                    `uuid`,
                    `tour_uuid`,
                    `next_step_uuid`,
                    `author_user_id`,
                    `type`,
                    `created_at`,
                    `updated_at`,
                    `deleted_at`
      )
      SELECT        @step1Uuid,
                    @tourStaff,
                    @step2Uuid,
                    (SELECT `id` FROM `user` WHERE `username` = ''alaress'' LIMIT 1),
                    ''tooltip'',
                    ''1980-04-07 00:00:00'',
                    ''1980-04-07 00:00:00'',
                    NULL
  '

ELSE ''

END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Staff dashboard feature tour tooltips

SET @sql :=

CASE WHEN EXISTS (SELECT * FROM `config` WHERE `name` = 'site_location' AND `value` = 'demo') THEN

  '
      INSERT INTO   `tour_step_tooltip`
      (
                    `uuid`,
                    `tour_step_uuid`,
                    `author_user_id`,
                    `title`,
                    `body`,
                    `selector`,
                    `css_class`,
                    `created_at`,
                    `updated_at`,
                    `deleted_at`
      )
      SELECT        UUID(),
                    @step1Uuid,
                    (SELECT `id` FROM `user` WHERE `username` = ''alaress'' LIMIT 1),
                    ''Whale'',
                    ''This is a whale'',
                    ''.logo'',
                    null,
                    ''1980-04-07 00:00:00'',
                    ''1980-04-07 00:00:00'',
                    NULL
  '

ELSE ''

END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql :=

CASE WHEN EXISTS (SELECT * FROM `config` WHERE `name` = 'site_location' AND `value` = 'demo') THEN

  '
      INSERT INTO   `tour_step_tooltip`
      (
                    `uuid`,
                    `tour_step_uuid`,
                    `author_user_id`,
                    `title`,
                    `body`,
                    `selector`,
                    `css_class`,
                    `created_at`,
                    `updated_at`,
                    `deleted_at`
      )
      SELECT        UUID(),
                    @step2Uuid,
                    (SELECT `id` FROM `user` WHERE `username` = ''alaress'' LIMIT 1),
                    ''Wrong Way! Turn Back!'',
                    ''This is a dead-end...'',
                    ''#tile-10'',
                    ''danger'',
                    ''1980-04-07 00:00:00'',
                    ''1980-04-07 00:00:00'',
                    NULL
  '

ELSE ''

END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update tour with first step

SET @sql :=

CASE WHEN EXISTS (SELECT * FROM `config` WHERE `name` = 'site_location' AND `value` = 'demo') THEN

  '
      UPDATE `tour` SET `tour_step_uuid` = @step1Uuid
  '

ELSE ''

END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;



-- Create an event for a step

INSERT INTO     `tour_step_event`
(
                `uuid`,
                `tour_step_uuid`,
                `selector`,
                `event`,
                `author_user_id`,
                `created_at`,
                `updated_at`,
                `deleted_at`
)
SELECT          UUID(),
                @step2Uuid,
                '.profile-drop',
                'click',
                (SELECT `id` FROM `user` WHERE `username` = 'alaress' LIMIT 1),
                '1980-04-07 00:00:00',
                '1980-04-07 00:00:00',
                NULL;
