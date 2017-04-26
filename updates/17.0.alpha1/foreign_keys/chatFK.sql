
DELETE chat FROM chat
 LEFT JOIN folder ON category_id = folder.id
WHERE folder.id IS NULL;

DELETE chat FROM chat
  LEFT JOIN user ON user_id = user.id
WHERE user.id IS NULL;


ALTER TABLE `chat`
  ADD CONSTRAINT `fk_chat_folder_id`
FOREIGN KEY (`category_id`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_chat_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE chat_poll FROM chat_poll
  LEFT JOIN folder ON category_id = folder.id
WHERE folder.id IS NULL;

DELETE chat_poll FROM chat_poll
  LEFT JOIN user ON user_id = user.id
WHERE user.id IS NULL;

ALTER TABLE `chat_poll`
  ADD CONSTRAINT `fk_chat_poll_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_chat_poll_folder_id`
FOREIGN KEY (`category_id`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;