ALTER TABLE search_index DROP KEY `search_index_fulltext_index_index`;
ALTER TABLE search_index DROP KEY `search_index_title_index`;
CREATE FULLTEXT INDEX `search_index_fulltext_index_index` ON search_index (`fulltext_index`);
CREATE FULLTEXT INDEX `search_index_title_index` ON search_index (`title`);

# The purpose of this line is described here: https://github.com/alaress/schoolbox/pull/29879
OPTIMIZE TABLE search_index;
