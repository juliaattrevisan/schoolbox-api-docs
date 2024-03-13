# Create a custom stopwords list that is the same as the default list except for the inclusion of 'and'.
# Note: This will only have effect if innodb_ft_server_stopword_table = 'schoolbox/innodb_ft_stopword'.
CREATE TABLE innodb_ft_stopword AS SELECT * FROM INFORMATION_SCHEMA.INNODB_FT_DEFAULT_STOPWORD;
INSERT INTO innodb_ft_stopword SET value = 'and';

# Note: An infra change is required to set that config variable so that its value will be preserved after restart.
SET GLOBAL innodb_ft_server_stopword_table = 'schoolbox/innodb_ft_stopword';

# Rebuild the search indexes.
ALTER TABLE search_index DROP KEY `search_index_fulltext_index_index`;
ALTER TABLE search_index DROP KEY `search_index_title_index`;
CREATE FULLTEXT INDEX `search_index_fulltext_index_index` ON search_index (`fulltext_index`);
CREATE FULLTEXT INDEX `search_index_title_index` ON search_index (`title`);

# The purpose of this line is described here: https://github.com/alaress/schoolbox/pull/29879
OPTIMIZE TABLE search_index;
