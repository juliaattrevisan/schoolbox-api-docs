-- vic gov now uses a 5 (at *least*) submission state code
-- like "DNP-SI" instead of 4. If we allow 8 we'll be futureproofing ourselves
-- similarly with display_as also now containing some waffly phrasing.
ALTER TABLE `submission_errors`
    CHANGE COLUMN `code` `code` VARCHAR(8) NULL DEFAULT NULL ,
    CHANGE COLUMN `display_as` `display_as` VARCHAR(64) NULL DEFAULT NULL ;
