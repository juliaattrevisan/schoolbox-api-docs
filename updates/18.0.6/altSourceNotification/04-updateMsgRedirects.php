#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../../conf/core/require.php';
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Alt Source Linkup',
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

/** @var IConnector $db */
$db = $kernel->get('databaseInternal');

$accessLevels = [
    UserAccess::ACCESS_READ,
    UserAccess::ACCESS_WRITE,
    UserAccess::ACCESS_FULL,
];

// Rebuild the alt-sources for CMS components
// "Find the list of alt-sources, and transpose those into the `msg_redirect` table."
$stmt = $db->prepareQuery("
    INSERT INTO msg_redirect
    SELECT
        CONCAT_WS('.', '*.notify.service', :service, cci.value, :access, cd.msg_namespace_slug, cd.msg_namespace_slug_suffix) AS `from`,
        CONCAT_WS('.', '*.notify.service', :service, ci.identifier, :access, '#') AS `to`

    FROM component_config_instance cci
        INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
        INNER JOIN component_instance ci ON cci.component_instance_id = ci.id
        INNER JOIN component_definition cd ON ci.component_definition_id = cd.id

    WHERE ccd.key = 'src'
        AND ci.module = 'homepage'
        AND cci.value NOT IN (ci.identifier, '')
        AND cci.value IS NOT NULL
        AND cd.msg_namespace_slug = 'cms'
        AND cd.msg_namespace_slug_suffix IS NOT NULL

    ON DUPLICATE KEY UPDATE msg_redirect.from = `from`;
");

foreach ($accessLevels as $level) {
    // run the query for each access level
    $stmt->execute([ 'access' => $level, 'service' => UserAccess::SERVICE_HOMEPAGE, ]);
}
