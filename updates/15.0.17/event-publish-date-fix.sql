-- How did 0000-00-00 even get in?
UPDATE `event` SET `publish_date` = NULL WHERE DATE(`publish_date`) = '0000-00-00';
