UPDATE `config`
SET
    `required` = 'true',
    `value` = CASE
        WHEN `value` IS NULL
        OR `value` = '' THEN '22:00'
        ELSE `value`
    END
WHERE
    `name` = 'ups_cron_run_time';
