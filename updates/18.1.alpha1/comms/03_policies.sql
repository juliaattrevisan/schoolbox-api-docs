-- News Policy: Global Moderation
-- When enabled, every news article requires moderation before it can reach the masses
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('global_moderation', 1);

-- News Policy: Publish Date Offset
-- When enabled, every article's publish date is offset by this amount
-- When empty, publish date is set to the current datetime for immediate effect
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('publish_date_offset', '');

-- News Policy: Expiry Date Offset
-- When enabled, every article's expiry date is offset by this amount
-- When empty, expiry date is set to the current datetime plus 1 week
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('expiry_date_offset', '+1 week');

-- News Policy: Save For Later
-- When enabled, articles can be saved for later
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('save_for_later', 1);