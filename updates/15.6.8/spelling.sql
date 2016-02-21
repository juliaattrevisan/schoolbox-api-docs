UPDATE `config` SET `description` = 'If on, parents will have access to the staff directory and staff details on the profile.  Otherwise it is only accessible to staff.' WHERE `name` = 'report_staff_access';

UPDATE `config` SET `title` = 'Crest Background Colour' WHERE `name` = 'skin_colour_logo_background';

UPDATE `config` SET `title` = 'Allow Parents Access to Staff Details' WHERE `name` = 'report_staff_access';
