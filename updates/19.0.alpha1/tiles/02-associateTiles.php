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
    /**
     * @var LoggerInterface
     */
    private $log;

    private const INDENT = '  ';

    private const FIND_COMPONENT_CONFIG_QUERY = "
        SELECT 
            ci.id,
            ccd.key,
            cci.value
        FROM component_instance                ci
        INNER JOIN component_definition        cd ON ci.component_definition_id = cd.id
        LEFT JOIN component_config_instance   cci ON ci.id = cci.component_instance_id
        LEFT JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
                                                  AND ccd.key IN ('layoutId', 'tileSource')
        WHERE cd.class IN (:dashboardTile, :homepageTile)
        ORDER BY ci.id ASC, ccd.key ASC
    ";

    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts)
    {
        // Find component config values
        /** @var IConnector $db */
        $this->log   = $log;
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
                $log->info(self::INDENT.sprintf('component has alternate tile source #%d: connecting to a layout ID', $component['tileSource']));

                // Tile source will be a component ID, somewhere in this list.
                // It's possible to have circular references: break them if found
                // (this will happen by removing the tileSource config)

                if ($component['layoutId']) {
                    $log->warning(self::indent(1).sprintf('component also has layout ID #%d: ignoring, and using tile source', $component['layoutId']));
                }

                // Figure out which layout ID this falls back to
                $baseLayoutId = $this->findBaseLayoutId($component['tileSource'], $components);
                if ($baseLayoutId) {
                    $log->info(self::INDENT.sprintf('found layout ID #%d', $baseLayoutId));
                } else {
                    $log->warning(self::INDENT.'no base layout found!');
                }

            } elseif ($component['layoutId']) {
                $log->info(self::indent(1).sprintf('component has layout ID #%d: leaving as is', $component['layoutId']));
                // TODO: connect layout ID-based components to their source
            } else {
                $log->info(self::indent(1).'component has neither a tile source nor layout ID: leaving as is');
            }
        }
    }



    /**
     * Helper method for indenting log output for legibility.
     *
     * @param int $depth The depth to indent by
     * @return string
     */
    private static function indent(int $depth): string
    {
        return str_repeat(self::INDENT, $depth);
    }


    /**
     * Find a base layout ID for this component.
     *
     * @param int   $componentId
     * @param array $components
     * @param array $searchedComponentIds
     * @return int|null
     */
    private function findBaseLayoutId(int $componentId, array $components, array $searchedComponentIds = []): ?int
    {
        // Circular reference: abort
        if (isset($searchedComponentIds[$componentId])) {
            $this->log->warning(self::indent(1).'circular reference detected when finding base layout ID: skipping');
            return null;
        }
        $searchedComponentIds[$componentId] = true;


        // Find the base component in the list of components
        $baseComponent = $components[$componentId] ?? null;
        if (!isset($baseComponent)) {
            $this->log->warning(self::indent(1).'base component is not a tile component: skipping');
            return null;
        }
        if ($baseComponent['tileSource']) {
            return $this->findBaseLayoutId($baseComponent['tileSource'], $components, $searchedComponentIds);
        }
        if ($baseComponent['layoutId']) {
            return $baseComponent['layoutId'];
        }
        $this->log->warning(self::indent(1).'base component has neither a tile source nor layout ID: skipping');
        return null;
    }
};


try {
    $kernel->run($app);
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
