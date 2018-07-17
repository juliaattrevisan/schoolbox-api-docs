-- Temporary test table
CREATE TABLE `comms_news_audience` (
  `article_id` int(11) NOT NULL,
  `entity_type` int(11) NOT NULL,
  `entity_value` int(11),
  `relationship` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8
