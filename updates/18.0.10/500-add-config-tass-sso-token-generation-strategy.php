#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use External\Tass\Portal\SSO;
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../conf/core/require.php';
$kernel = new Daemon\Kernel(
    'Insert SSO token generation strategy config',
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

/** @var Alaress\Database\Connector\IConnector $db */
/** @noinspection PhpUnhandledExceptionInspection */
$db = $kernel->get('databaseInternal');

$sql  = "
    INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
    VALUES (
      'external_tass_sso_token_generation_strategy',
      :value,
      'Tass SSO token generation strategy',
      'Set whether your school should attempt to connect to TASS products using SSO v1 or v2. If you know you do not have v2 deep-links, you should use v1 SSO tokens',
      (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
      :options,
      (SELECT id FROM lu_config_type WHERE type = 'External DB'),
      'true',
      'false',
      'true'
    );
";
$options = [
    SSO::SSO_STRATEGY_V1_ONLY    => 'Version 1 only',
    SSO::SSO_STRATEGY_V2_ONLY    => 'Version 2 only',
    SSO::SSO_STRATEGY_V2_THEN_V1 => 'Version 2 with fallback to Version 1',
];
$optionParts = [];
foreach ($options as $key => $val) {
    $optionParts[] = "{$key};{$val}";
}
$stmt = $db->executeQuery($sql, [
    'value'   => SSO::SSO_STRATEGY_DEFAULT,
    'options' => implode(':', $optionParts),
]);
