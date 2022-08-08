-- make myFolder soft-deletable
ALTER TABLE `myFolder`
    ADD COLUMN `deleted_at` datetime DEFAULT NULL after `name`;
