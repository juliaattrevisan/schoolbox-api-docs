<?php
/**
 * Merge the date and time component config options into a single datetime option.
 *
 * Only the countdown component has either a date or time option.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Update countdown component configuration', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {
    // Find all countdown components
    $db                    = Database::getInstance();

    $newConfigId           = $db->query("
        SELECT ccd.id
        FROM   component_config_definition ccd
        INNER JOIN component_definition cd ON ccd.component_definition_id = cd.id
        WHERE cd.class  = 'Component_Homepage_CountdownController'
        AND   ccd.`key` = 'datetime'
        LIMIT 1
    ")->fetchColumn();

    $insertCountdownConfigQ = $db->prepare("
        INSERT INTO component_config_instance (
            component_config_definition_id,
            component_instance_id,
            `value`
        ) VALUES (
            :configId,
            :componentId,
            :newValue
        )
    ");

    $countdownConfigs       = [];
    $selectCountdownConfigQ = $db->query("
        SELECT
            cci.component_instance_id AS id,
            ccd.`key`,
            COALESCE(cci.value, '') AS `value`
        FROM component_definition cd
        LEFT JOIN component_config_definition ccd ON cd.id  = ccd.component_definition_id
        LEFT JOIN component_config_instance   cci ON ccd.id = cci.component_config_definition_id
        WHERE cd.class = 'Component_Homepage_CountdownController'
        AND ccd.`key` IN ('date', 'time')
    ");
    foreach ($selectCountdownConfigQ as $row) {
        $countdownConfigs[$row['id']][$row['key']] = $row['value'];
    }

    // If date not set: insert blank
    // If date set and time is not: assume start of day
    // Otherwise: insert date + time
    foreach ($countdownConfigs as $componentId => $config) {
        if (empty($config['date'])) {
            $newValue = '';
        } elseif (empty($config['time'])) {
            $newValue = $config['date'] . ' 00:00:00';
        } else {
            $newValue = $config['date'] . ' ' . $config['time'];
        }
        $insertCountdownConfigQ->execute([
            'configId'    => $newConfigId,
            'componentId' => $componentId,
            'newValue'    => $newValue,
        ]);
    }
});
