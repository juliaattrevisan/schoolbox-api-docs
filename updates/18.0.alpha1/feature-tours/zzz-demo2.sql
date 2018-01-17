
SET @step1Uuid = UUID(); --This is uuid for the step
SET @step2Uuid = UUID(); --This is the uuid for the step type


INSERT INTO `tour_step` (`uuid`, `tour_uuid`, `next_step_uuid`, `author_user_id`, `type`, `created_at`, `updated_at`)
VALUES (@step1Uuid, (SELECT `uuid` FROM `tour` WHERE `title` = 'Staff Dashboard Tour') , (SELECT `tour_step_uuid` FROM `tour` WHERE `title` = 'Staff Dashboard Tour'),
'19', 'modal', '1980-04-07 00:00:00', '1980-04-07 00:00:00'); --Insert step, setting the current first step of the tour to the next step of the new step


UPDATE `tour` set `tour_step_uuid` = @step1Uuid; --Up date the tour to use the new step first


INSERT INTO `tour_step_modal` (`uuid`, `tour_step_uuid`, `author_user_id`, `title`, `body`, `created_at`, `updated_at`)
VALUES (@step2Uuid, @step1Uuid, '19', 'Welcome to your tour!',
'Welcome to your dashboard! This tour will guide you through an overview of the main areas of the first screen you see when you log in.
This includes; the dashboard, side menu and top menu.', '1980-04-07 00:00:00', '1980-04-07 00:00:00'); --Insert step type

