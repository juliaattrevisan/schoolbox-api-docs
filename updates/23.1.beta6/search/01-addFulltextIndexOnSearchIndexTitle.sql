ALTER TABLE search_index ADD FULLTEXT search_index_title_index (title);

# This is to deal with an error I saw sometimes after running the above command.
# The error was 'DOUBLE VALUE IS OUT OF RANGE'.  It appeared when trying to run queries containing 'MATCH...AGAINST'.
# The 'ANALYZE TABLE' solution was found here:
# https://stackoverflow.com/questions/64397678/mysql-match-relevance-score-causes-double-value-is-out-of-range-error
ANALYZE TABLE search_index;
