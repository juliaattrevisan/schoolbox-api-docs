-- SB-1966 - change news fields to be published and expire on a date, not a datetime.
ALTER TABLE news_items CHANGE item_datetime_published item_datetime_published DATE NOT NULL;
ALTER TABLE news_items CHANGE item_datetime_expires   item_datetime_expires   DATE NOT NULL DEFAULT '0000-00-00';

ALTER TABLE subject_news CHANGE datetime_published datetime_published DATE NOT NULL;
ALTER TABLE subject_news CHANGE datetime_expires   datetime_expires   DATE NOT NULL DEFAULT '0000-00-00';
