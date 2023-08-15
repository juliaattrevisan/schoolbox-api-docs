-- deprecate the column

ALTER TABLE `pastoral_audit`
    CHANGE COLUMN `search_result` `_search_result` TEXT NULL DEFAULT NULL ;
