#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\Tile\Component\DashboardTileController;
use Schoolbox\Tile\Component\HomepageTileController;


$opts = require __DIR__ . '/../../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel("Associate Tiles", [
    'verbose' => [true, true, true],
    'errors'  => E_ALL,
]);

$app = new class extends App
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var IConnector
     */
    private $db;

    /**
     * @var IQuery
     */
    private $attachLayoutQuery;

    /**
     * @var IQuery
     */
    private $attachToFolderQuery;

    /**
     * @var IQuery
     */
    private $attachToDashboardQuery;

    /**
     * @var IQuery
     */
    private $attachToHomepageTemplateQuery;

    private const INDENT = '  ';

    private const FIND_COMPONENT_CONFIG_QUERY = "
        SELECT
            ci.id,
            ccd.key,
            cci.value,
            ci.module,
            ci.identifier,
            cd.class
        FROM component_instance                ci
        INNER JOIN component_definition        cd ON ci.component_definition_id = cd.id
        LEFT JOIN component_config_instance   cci ON ci.id = cci.component_instance_id
        LEFT JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
                                                  AND ccd.key IN ('layoutId', 'tileSource')
        WHERE cd.class IN (:dashboardTile, :homepageTile)
        ORDER BY ci.id ASC, ccd.key ASC
    ";

    private const ATTACH_LAYOUT_ID_QUERY = "
        REPLACE INTO component_config_instance (
            component_config_definition_id,
            component_instance_id,
            value
        ) VALUES (
            (
                SELECT ccd.id
                FROM component_config_definition ccd
                INNER JOIN component_definition cd ON ccd.component_definition_id = cd.id
                WHERE cd.class = :class
                AND   ccd.key  = 'layoutId'
            ),
            :componentId,
            :layoutId
        )
    ";

    private const ATTACH_TO_FOLDER_QUERY = "
        INSERT INTO tile_layout_for_folder (
            tile_layout_id,
            folder_id
        ) VALUES (
            :tileLayoutId,
            :identifier
        )
    ";

    private const ATTACH_TO_DASHBOARD_QUERY = "
        INSERT INTO tile_layout_for_dashboard (
            tile_layout_id,
            role_id
        ) VALUES (
            :tileLayoutId,
            :identifier
        )
    ";

    private const ATTACH_TO_HOMEPAGE_TEMPLATE_QUERY = "
        INSERT INTO tile_layout_for_homepage_template (
            tile_layout_id,
            homepage_template_id
        ) VALUES (
            :tileLayoutId,
            :identifier
        )
    ";

    /**
     * Run the app.
     *
     * @param Di\Container    $di
     * @param LoopInterface   $loop
     * @param LoggerInterface $log
     * @param array           $opts
     */
    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts)
    {
        // Find component config values
        /** @var IConnector $db */
        $this->log   = $log;
        $this->db    = $di->get('databaseInternal');
        $configQuery = $this->db->prepareQuery(self::FIND_COMPONENT_CONFIG_QUERY);
        $params      = [
            'dashboardTile' => DashboardTileController::class,
            'homepageTile'  => HomepageTileController::class,
        ];
        $configQuery->execute($params);


        $this->attachLayoutQuery = $this->db->prepareQuery(self::ATTACH_LAYOUT_ID_QUERY);

        $this->attachToFolderQuery           = $this->db->prepareQuery(self::ATTACH_TO_FOLDER_QUERY);
        $this->attachToDashboardQuery        = $this->db->prepareQuery(self::ATTACH_TO_DASHBOARD_QUERY);
        $this->attachToHomepageTemplateQuery = $this->db->prepareQuery(self::ATTACH_TO_HOMEPAGE_TEMPLATE_QUERY);


        // Collate them together
        $components = [];

        $this->db->beginTransaction();

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
                    'component'  => [
                        'class'      => $row['class'],
                        'module'     => $row['module'],
                        'identifier' => $row['identifier'],
                    ]
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
                $log->info(
                    self::INDENT . sprintf(
                        'component has alternate tile source #%d: connecting to a layout ID',
                        $component['tileSource']
                    )
                );

                // Tile source will be a component ID, somewhere in this list.
                // It's possible to have circular references: break them if found
                // (this will happen by removing the tileSource config)

                if ($component['layoutId']) {
                    $log->warning(
                        self::indent(1) . sprintf(
                            'component also has layout ID #%d: ignoring, and using tile source',
                            $component['layoutId']
                        )
                    );
                }

                // Figure out which layout ID this falls back to
                $baseLayoutId = $this->findBaseLayoutId($component['tileSource'], $components);
                if ($baseLayoutId) {
                    $log->notice(self::INDENT . sprintf('found layout ID #%d: attaching', $baseLayoutId));
                } else {
                    $log->warning(self::INDENT . 'no base layout found: this tile component will be empty');
                }

                // Now attach the layout ID
                $this->attachLayoutQuery->execute([
                    'class'       => $component['component']['class'],
                    'componentId' => $componentId,
                    'layoutId'    => $baseLayoutId,
                ]);

            } elseif ($component['layoutId']) {
                $log->info(
                    self::indent(1) . sprintf(
                        'component has layout ID #%d: attaching layout to a location',
                        $component['layoutId']
                    )
                );


                $params = [
                    'tileLayoutId' => $component['layoutId'],
                    'identifier'   => $component['component']['identifier'],
                ];
                switch ($component['component']['module']) {
                    case 'homepage':
                        $log->notice(self::INDENT . sprintf('attaching layout to folder #%d', $params['identifier']));
                        $this->attachToFolderQuery->execute($params);
                        break;

                    case 'homepage_template':
                        $log->notice(self::INDENT . sprintf('attaching layout to homepage template #%d', $params['identifier']));
                        $this->attachToHomepageTemplateQuery->execute($params);
                        break;

                    case 'dashboard':
                        $log->notice(self::INDENT . sprintf('attaching layout to dashboard for role #%d', $params['identifier']));
                        $this->attachToDashboardQuery->execute($params);
                        break;

                    default:
                        $log->warning(self::INDENT . sprintf('unknown module "%s": skipping', $component['component']['module']));
                        break;
                }
            } else {
                $log->notice(self::indent(1) . 'component has neither a tile source nor layout ID: leaving as is');
            }
        }


        $this->db->commit();
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
            $this->log->warning(self::indent(1) . 'circular reference detected when finding base layout ID: skipping');
            return null;
        }
        $searchedComponentIds[$componentId] = true;


        // Find the base component in the list of components
        $baseComponent = $components[$componentId] ?? null;
        if (!isset($baseComponent)) {
            $this->log->warning(self::indent(1) . 'base component is not a tile component: skipping');
            return null;
        }
        if ($baseComponent['tileSource']) {
            return $this->findBaseLayoutId($baseComponent['tileSource'], $components, $searchedComponentIds);
        }
        if ($baseComponent['layoutId']) {
            return $baseComponent['layoutId'];
        }
        $this->log->warning(self::indent(1) . 'base component has neither a tile source nor layout ID: skipping');
        return null;
    }
};


try {
    $kernel->run($app);
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
