#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Custom\Tigs;
use Schoolbox\Tile\Component\DashboardTileController;
use Schoolbox\Tile\Component\HomepageTileController;


$opts = require __DIR__ . '/../../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel("Associate Tiles", [
    'verbose' => $opts['v'] ?? null,
    'errors'  => E_ALL,
]);

$app = new class extends App
{

    private const INDENT = '  ';

    private const FIND_COMPONENT_CONFIG_QUERY = "
        SELECT 
            ci.id,
            ccd.key,
            cci.value
        FROM component_instance                 ci
        INNER JOIN component_config_instance   cci ON ci.id = cci.component_instance_id
        INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
                                                   AND ccd.key IN ('layoutId', 'tileSource')
        INNER JOIN component_definition         cd ON ci.component_definition_id = cd.id
        WHERE cd.class IN (:dashboardTile, :homepageTile)
        ORDER BY ci.id ASC, ccd.key ASC
    ";

    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts)
    {
        // Find component config values
        /** @var IConnector $db */
        $db          = $di->get('databaseInternal');
        $configQuery = $db->prepareQuery(self::FIND_COMPONENT_CONFIG_QUERY);
        $params      = [
            'dashboardTile' => DashboardTileController::class,
            'homepageTile'  => HomepageTileController::class,
        ];
        $configQuery->execute($params);


        // Collate them together
        $components = [];
        foreach ($configQuery->getIterator() as $row) {
            $id    = (int)$row['id'];
            $key   = $row['key'];
            $value = !empty($row['value'])
                ? (int)$row['value']
                : null;
            if (!isset($components[$id])) {
                $components[$id] = [
                    'layoutId'   => null,
                    'tileSource' => null,
                ];
            }
            $components[$id][$key] = $value;
        }


        // Do something with them
        foreach ($components as $componentId => $component) {
            // If this has a layout ID: join this layout to this component.
            // If this has a tileSource instead: figure out which component this belongs to
            // If I can't do that: this component is useless, delete it
            $log->notice(sprintf('Checking tile component #%d', $componentId));

            if ($component['tileSource']) {
                $log->info(self::INDENT.sprintf('component has tile source #%d', $component['tileSource']));

                if ($component['layoutId']) {
                    $log->warning(self::INDENT.sprintf('component also has layout ID #%d: using tile source', $component['layoutId']));
                }

                // Figure out which layout ID this falls back to

            } elseif ($component['layoutId']) {
                $log->info(self::INDENT.sprintf('component has layout ID #%d', $component['layoutId']));
            } else {
                $log->warning(self::INDENT.'component has neither a tile source nor layout ID: how did you get here?');
            }
        }
    }
};


try {
    $kernel->run($app);
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
