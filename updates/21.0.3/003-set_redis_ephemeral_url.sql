UPDATE `config`
    SET `config`.`value` = 'tcp://127.0.0.1:6380'
    WHERE `config`.`name` = 'redis_ephemeral_url';
