ALTER TABLE subject_news MODIFY datetime_published DATETIME NOT NULL DEFAULT '0000-00-00';
ALTER TABLE subject_news MODIFY datetime_expires DATETIME NOT NULL DEFAULT '0000-00-00';
UPDATE subject_news SET datetime_expires =concat(date(datetime_expires), ' 11:59:59');