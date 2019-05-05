-- Update skin_colour_off_canvas to accounts_skin_colour_off_canvas for easily distinguish the settings
UPDATE config SET `name` = 'accounts_menu_bg_colour' WHERE `name` = 'skin_colour_off_canvas';
