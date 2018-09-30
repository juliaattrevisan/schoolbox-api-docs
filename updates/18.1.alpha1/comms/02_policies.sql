-- Add a new column to the config table to specify to which admin module the config belongs
-- Create the column, set all values to 'settings'
ALTER TABLE config
  ADD COLUMN module ENUM ('settings', 'comms') DEFAULT 'settings';
-- Remove the default and enforce non nullable
ALTER TABLE config
  CHANGE COLUMN module module ENUM ('settings', 'comms') NOT NULL;

-- Fix configs that are set to boolean but are actually ints
UPDATE config SET field_id = 1 WHERE name like 'search_all_limit' OR name like 'search_type_limit';

-- Add/Update Comms News config settings

-- Global moderation (new setting)
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module) VALUES (
  'global_moderation',
  false,
  'Global moderation',
  'Enable to require moderation on articles before they are made publicly available to school-wide audiences.',
  (SELECT id FROM lu_config_field WHERE field = 'boolean'),
  null,
  null,
  true,
  false,
  true,
 'comms'
);

-- Publish date offset (new setting)
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module) VALUES (
  'publish_date_offset',
  '',
  'Publish date offset',
  'News will initially have their default publish date set to the current datetime plus this value.',
  (SELECT id FROM lu_config_field WHERE field = 'input'),
  null,
  null,
  true,
  false,
  true,
  'comms'
);

-- Expiry date offset (existing setting)
UPDATE config SET
  title         = 'Expiry date offset',
  description   = 'News will initially have their default expiry date set to the current datetime plus this value.',
  type_id       = NULL,
  config.module = 'comms'
WHERE name = 'news_default_expiry';


-- Read More (new setting)
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module) VALUES (
  'read_more',
  '',
  '"Read More" link for long articles',
  'News that are truncated will have a link containing this text, prompting audience to "read more". To disable it, leave this blank.',
  (SELECT id FROM lu_config_field WHERE field = 'input'),
  null,
  null,
  true,
  false,
  true,
  'comms'
);

-- Save For Later (new setting)
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module) VALUES (
  'save_for_later',
  1,
  'Saving articles',
  'Allow users to add news articles to their saved items.',
  (SELECT id FROM lu_config_field WHERE field = 'boolean'),
  null,
  null,
  true,
  false,
  true,
  'comms'
);

-- Staff can post to non-folder audiences (existing setting)
UPDATE config SET
  title       = 'Allow staff to post to all audiences',
  description = 'If enabled, staff members can send news to campuses, roles, houses and year levels. Note that if moderation is enabled, articles will need to be approved by a moderator before going public.',
  module      = 'comms'
WHERE name = 'news_staff_post_enabled';

-- Allow CMS instructors to edit news created by other users (existing setting)
-- When enabled, users can edit other users news if they have write access to the page
-- When disabled, users can only edit their own news, unless they are a news moderator
UPDATE config SET
  title       = 'Allow staff to modify news',
  description = 'Allows staff to modify news posted by other users if they have write access.',
  module      = 'comms'
WHERE name = 'news_edit_other';



