INSERT INTO config SET
`name` = 'advanced_search_result_page_size',
`value` = '20',
`title` = 'Advanced Search result page size',
`description` = 'The maximum number of search results to return in a single page.',
`field_id` = 1,
`options` = NULL,
`type_id` = NULL,
`editable` = 'false',
`hidden` = 'true',
`required` = 'false',
`module`  = 'settings',
`sequence` = NULL;

INSERT INTO config SET
`name` = 'advanced_search_result_pages_per_batch',
`value` = '10',
`title` = 'Advanced Search result pages per batch',
`description` = 'The maximum number of pages of potential search results to fetch in a single batch.  Potential search results are search results matching the search query, not considering user permissions.',
`field_id` = 1,
`options` = NULL,
`type_id` = NULL,
`editable` = 'false',
`hidden` = 'true',
`required` = 'false',
`module`  = 'settings',
`sequence` = NULL;
