ALTER TABLE `user`
  ADD COLUMN `portrait_hash`  VARCHAR(100) CHARACTER SET ascii COLLATE ascii_general_ci NULL AFTER `hash`;

ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_portrait_hash`
    FOREIGN KEY (`portrait_hash`) REFERENCES `storage` (`hash`)
      ON DELETE NO ACTION ON UPDATE NO ACTION;