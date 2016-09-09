ALTER TABLE `folder`
  ADD COLUMN `image_hash` VARCHAR(40) NULL AFTER `inherit`,
  ADD COLUMN `colour` VARCHAR(32) NULL AFTER `image_hash`;
