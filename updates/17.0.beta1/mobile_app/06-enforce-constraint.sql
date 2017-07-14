/** Now that the data is in, enforce the foreign key */
ALTER TABLE `mobile_app_registry`
  ADD FOREIGN KEY (`app_id`) REFERENCES `mobile_app`(`app_id`) ON UPDATE NO ACTION ON DELETE NO ACTION,
  ADD FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON UPDATE NO ACTION ON DELETE NO ACTION;
