UPDATE `config`
SET `options` = CONCAT(`options`, ':schooledge')
WHERE `name` = 'external_type'
AND `options` NOT LIKE '%schooledge%'
LIMIT 1;
