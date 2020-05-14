-- Purifier cache dir: adjacent to xml_dir
INSERT INTO `config` (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `options`,
    `editable`,
    `hidden`,
    `required`,
    `module`
)
SELECT
    'purifier_cache_dir',
    CONCAT(c2.value, '/../purifier/'),
    'HTMLPurifier Cache Location',
    'Location of HTMLPurifier caches. Cleared on version update',
    c2.field_id,
    c2.options,
    c2.editable,
    c2.hidden,
    c2.required,
    c2.module
FROM config c2
WHERE c2.name = 'xml_dir';

-- Twig cache dir: adjacent to xml_dir
INSERT INTO `config` (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `options`,
    `editable`,
    `hidden`,
    `required`,
    `module`
)
SELECT
    'twig_cache_dir',
    CONCAT(c2.value, '/../twig/'),
    'Twig Cache Location',
    'Location of Twig caches. Cleared on version update',
    c2.field_id,
    c2.options,
    c2.editable,
    c2.hidden,
    c2.required,
    c2.module
FROM config c2
WHERE c2.name = 'xml_dir';

-- No longer need xml_dir
DELETE FROM config WHERE `name` = 'xml_dir';
