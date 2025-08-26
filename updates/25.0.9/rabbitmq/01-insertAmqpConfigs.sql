SELECT @type := id FROM lu_config_type WHERE type = 'External DB';

/**
 * Reasoning behind config settings:
 * * amqp_heartbeat:     this simply makes the old hardcoded value configurable
 * * amqp_read_timeout:  this is set to double the old hardcoded value: doubling
 *                       the heartbeat value is a recommendation from
 *                       https://stackoverflow.com/questions/79440541/why-is-there-no-heartbeat-on-rabbitmq-php-connection.
 *                       Also see https://runebook.dev/en/articles/spring_boot/application-properties/application-properties.integration.spring.rabbitmq.template.receive-timeout
 * * amqp_write_timeout: like amqp_read_timeout, set to double the hardcoded
 *                       value.
 *                       See https://runebook.dev/en/articles/spring_boot/application-properties/application-properties.integration.spring.rabbitmq.template.reply-timeout
 * * amqp_keepalive:     this is set to true, so that connections are kept alive
 */
INSERT INTO `config`(
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
) VALUES (
    'amqp_heartbeat',
    '15',
    'Message Queue Heartbeat',
    'The number of seconds between which to send heartbeat signals to the message queue server.  Set to 0 to disable heartbeats.',
    (SELECT `id` FROM `lu_config_field` WHERE `field` = 'number'),
    @type,
    'false',
    'true',
    'true',
    null,
    (SELECT max(sequence) + 1 FROM `config` c WHERE c.type_id = @type)
), (
    'amqp_read_timeout',
    '30',
    'Message Queue Read Timeout',
    'The number of seconds the message queue connection will wait for a message before timing out and trying again.  This should generally be set to at least double the heartbeat.',
    (SELECT `id` FROM `lu_config_field` WHERE `field` = 'number'),
    @type,
    'false',
    'true',
    'true',
    null,
    (SELECT max(sequence) + 1 FROM `config` c WHERE c.type_id = @type)
), (
    'amqp_write_timeout',
    '30',
    'Message Queue Write Timeout',
    'The number of seconds the message queue connection will wait for a reply from the queue server before timing out.  This should generally be set to at least double the heartbeat.',
    (SELECT `id` FROM `lu_config_field` WHERE `field` = 'number'),
    @type,
    'false',
    'true',
    'true',
    null,
    (SELECT max(sequence) + 1 FROM `config` c WHERE c.type_id = @type)
), (
    'amqp_keepalive',
    '1',
    'Message Queue Keepalive',
    'Should connections to the message queue be kept alive?',
    (SELECT `id` FROM `lu_config_field` WHERE `field` = 'boolean'),
    @type,
    'false',
    'true',
    'true',
    null,
    (SELECT max(sequence) + 1 FROM `config` c WHERE c.type_id = @type)
);
