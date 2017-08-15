<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Permission\Access\UserAccess;


$opts = require __DIR__ . '/../../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Alt Source Linkup', [
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
    'flags'   => $flags,
]);

/** @var IConnector $db */
$db = $kernel->get('database');

$accessLevels = [
    UserAccess::ACCESS_READ,
    UserAccess::ACCESS_WRITE,
    UserAccess::ACCESS_FULL,
];

// "Find the list of alt-sources, and transpose those into the `msg_redirect` table."
$stmt = $db->prepareQuery("
    INSERT INTO msg_redirect
    SELECT
        CONCAT_WS('.', '*.notify.service.1', cci.value, :access, cd.msg_namespace_slug, '#') AS `from`,
        CONCAT_WS('.', '*.notify.service.1', ci.identifier, :access, '#') AS `to`

    FROM component_config_instance cci
        INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
        INNER JOIN component_instance ci ON cci.component_instance_id = ci.id
        INNER JOIN component_definition cd ON ci.component_definition_id = cd.id

    WHERE ccd.key = 'src'
        AND ci.module = 'homepage'
        AND cci.value NOT IN (ci.identifier, '')
        AND cci.value IS NOT NULL
        AND cd.msg_namespace_slug IS NOT NULL

    ON DUPLICATE KEY UPDATE msg_redirect.from = `from`;
");

foreach ($accessLevels as $level) {
    // run the query for each access level
    $stmt->execute([ 'access' => $level ]);
}
