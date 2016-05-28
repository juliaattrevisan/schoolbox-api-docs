ALTER TABLE news_items ADD item_blurb TEXT NULL;
ALTER TABLE news_items MODIFY COLUMN item_blurb TEXT AFTER item_mainpage;