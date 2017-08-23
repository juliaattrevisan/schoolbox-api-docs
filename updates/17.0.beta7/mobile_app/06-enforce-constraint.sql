-- Remove registrations from non-existent users
DELETE r FROM mobile_app_registry r
  LEFT JOIN user u ON r.user_id = u.id
WHERE u.id IS NULL;

-- Remove registrations for non-existent apps
DELETE r FROM mobile_app_registry r
  LEFT JOIN mobile_app a ON r.app_id = a.id
WHERE a.id IS NULL;

/** Now that the data is in, enforce the foreign key */
ALTER TABLE `mobile_app_registry`
  ADD FOREIGN KEY (`app_id`) REFERENCES `mobile_app`(`id`) ON UPDATE NO ACTION ON DELETE NO ACTION,
  ADD FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON UPDATE NO ACTION ON DELETE NO ACTION;
