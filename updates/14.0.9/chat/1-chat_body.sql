-- Make chat body text instead of varchar
ALTER TABLE `chat`
  CHANGE `body` `body` TEXT CHARSET utf8 COLLATE utf8_general_ci NOT NULL;
