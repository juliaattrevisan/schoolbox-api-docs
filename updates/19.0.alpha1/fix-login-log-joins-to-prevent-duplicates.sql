# Add uid column into login_log table
ALTER TABLE login_log
ADD COLUMN IF NOT EXISTS uid INT(11) UNSIGNED AFTER id;


# Update login_log tables to set values into the uid column with their respective active user id
UPDATE login_log SET uid = (
  SELECT user.id FROM user WHERE user.username = login_log.username
  AND user.deleted_at IS NULL
);


# Do a cleanup. Delete logs that we can't relate into a user
DELETE FROM login_log WHERE login_log.uid IS NULL;

# Add constraints
ALTER TABLE `login_log`
  ADD CONSTRAINT `fk_login_log`
    FOREIGN KEY (`uid`)
      REFERENCES `user` (`id`)
      ON DELETE CASCADE
      ON UPDATE NO ACTION;
