-- Deconflict table names

ALTER TABLE `profile_button_campus`
  RENAME TO  `profile_button_campus_availability` ;

ALTER TABLE `profile_button_role`
  RENAME TO  `profile_button_role_availability` ;
