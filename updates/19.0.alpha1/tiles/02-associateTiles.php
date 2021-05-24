#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../../conf/core/require.php';

$kernel = new Schoolbox\Application\Daemon\Kernel(
    "Associate Tiles",
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

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
    private $updateTitleLayoutHeadingQuery;

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

    private const UPDATE_TILE_LAYOUT_HEADING_QUERY = "
        UPDATE tile_layout SET heading = :heading WHERE id = :tileLayoutId
    ";

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
                                                  AND ccd.key IN ('layoutId', 'tileSource', 'componentname')
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
        INSERT IGNORE INTO tile_layout_for_folder (
            tile_layout_id,
            folder_id
        ) VALUES (
            :tileLayoutId,
            :identifier
        )
    ";

    private const ATTACH_TO_DASHBOARD_QUERY = "
        INSERT IGNORE INTO tile_layout_for_dashboard (
            tile_layout_id,
            role_id
        ) VALUES (
            :tileLayoutId,
            :identifier
        )
    ";

    private const ATTACH_TO_HOMEPAGE_TEMPLATE_QUERY = "
        INSERT IGNORE INTO tile_layout_for_homepage_template (
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
     * @inheritdoc
     */
    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags): void
    {
        $this->log                           = $log;
        $this->db                            = $di->get('databaseInternal');
        $this->updateTitleLayoutHeadingQuery = $this->db->prepareQuery(self::UPDATE_TILE_LAYOUT_HEADING_QUERY);
        $this->attachLayoutQuery             = $this->db->prepareQuery(self::ATTACH_LAYOUT_ID_QUERY);
        $this->attachToFolderQuery           = $this->db->prepareQuery(self::ATTACH_TO_FOLDER_QUERY);
        $this->attachToDashboardQuery        = $this->db->prepareQuery(self::ATTACH_TO_DASHBOARD_QUERY);
        $this->attachToHomepageTemplateQuery = $this->db->prepareQuery(self::ATTACH_TO_HOMEPAGE_TEMPLATE_QUERY);


        // Find tiles components
        $components  = [];
        $configQuery = $this->db->prepareQuery(self::FIND_COMPONENT_CONFIG_QUERY);
        $params      = [
            'dashboardTile' => DashboardTileController::class,
            'homepageTile'  => HomepageTileController::class,
        ];
        $configQuery->execute($params);
        foreach ($configQuery->getIterator() as $row) {
            $id  = (int)$row['id'];
            $key = $row['key'];

            // Tile source and layout ID are ints: component name is a string
            if ($key === 'layoutId' || $key === 'tileSource') {
                $value = !empty($row['value'])
                    ? (int)$row['value']
                    : null;
            } else {
                $value = $row['value'];
            }

            if (!isset($components[$id])) {
                $components[$id] = [
                    'layoutId'      => null,
                    'tileSource'    => null,
                    'componentname' => '',
                    'component'     => [
                        'id'         => $id,
                        'class'      => $row['class'],
                        'module'     => $row['module'],
                        'identifier' => $row['identifier'],
                    ],
                ];
            }
            $components[$id][$key] = $value;
        }


        // Go
        $this->db->beginTransaction();
        foreach ($components as $componentId => $component) {
            // Ensure layout ID and tile source are ints
            $this->log->notice(sprintf('Checking tile component #%d', $componentId));

            $tileSourceId = filter_var($component['tileSource'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            $layoutId     = filter_var($component['layoutId'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            if ($tileSourceId === $componentId) {
                $this->log->notice(self::indent(1) . 'component\'s tile source is itself: ignoring and associating with layout ID');
                $this->attachLayoutToLocation($component);
                $this->updateTileLayoutHeading($component);
            } elseif ($tileSourceId) {
                $this->attachLayoutToComponent($component, $components);
            } elseif ($layoutId) {
                $this->attachLayoutToLocation($component);
                $this->updateTileLayoutHeading($component);
            } else {
                $this->log->notice(self::indent(1) . 'component has neither a tile source nor layout ID: leaving as is');
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
     * Returns the layout ID for `$components[$componentId]`: if that component
     * has a tile source instead, then call this recursively with that value.
     *
     * If no layout ID was found, or a circular reference was found: return null.
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


        // Recursion protection: let's say 50 levels
        if (count($searchedComponentIds) > 50) {
            $this->log->warning(self::indent(1) . 'no base layout found after 50 traversals: skipping');
            return null;
        }


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


    /**
     * For tile components with an alternate source selected (tileSource):
     * figure out which layout is actually shown in the component, and attach
     * it directly.
     *
     * @param array $component
     * @param array $components
     */
    private function attachLayoutToComponent(array $component, array $components): void
    {
        $this->log->info(
            self::INDENT . sprintf(
                'component has alternate tile source #%d: connecting to a layout ID',
                $component['tileSource']
            )
        );

        // Due to various bugs, a component may have both a tile source and a
        // layout ID.
        // Use the tile source instead.
        if ($component['layoutId']) {
            $this->log->warning(
                self::indent(1) . sprintf(
                    'component also has layout ID #%d: ignoring, and using tile source',
                    $component['layoutId']
                )
            );
        }

        // Figure out which layout ID this falls back to
        $baseLayoutId = $this->findBaseLayoutId($component['tileSource'], $components);
        if ($baseLayoutId) {
            $this->log->notice(self::INDENT . sprintf('found layout ID #%d: attaching', $baseLayoutId));
        } else {
            $this->log->warning(self::INDENT . 'no base layout found: this tile component will be empty');
            return;
        }

        // Now attach the layout ID
        $this->attachLayoutQuery->execute([
            'class'       => $component['component']['class'],
            'componentId' => $component['component']['id'],
            'layoutId'    => $baseLayoutId,
        ]);
    }


    /**
     * Set the heading of the tile component referenced here.
     *
     * @param array $component
     */
    private function updateTileLayoutHeading(array $component): void
    {
        $heading = trim($component['componentname'] ?? '');
        if ($heading === '') {
            $heading = 'Tiles';
            $this->log->notice(self::INDENT . sprintf('layout has no heading: setting to "%s"', $heading));
        } else {
            $this->log->notice(self::INDENT . sprintf('setting heading for layout to "%s"', $heading));
        }
        $this->updateTitleLayoutHeadingQuery->execute([
            'tileLayoutId' => $component['layoutId'],
            'heading'      => $heading,
        ]);
    }


    /**
     * For tile components with a layout selected, but no alternate source:
     * Relate the tile source directly to the tile's component.
     *
     * @param array $component
     */
    private function attachLayoutToLocation(array $component): void
    {
        $this->log->info(
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
            case Component_Instance::MODULE_HOMEPAGE:
                $this->log->notice(self::INDENT . sprintf('attaching layout to folder #%d', $params['identifier']));
                $this->attachToFolderQuery->execute($params);
                break;

            case Component_Instance::MODULE_HOMEPAGE_TEMPLATE:
                $this->log->notice(
                    self::INDENT . sprintf(
                        'attaching layout to homepage template #%d',
                        $params['identifier']
                    )
                );
                $this->attachToHomepageTemplateQuery->execute($params);
                break;

            case Component_Instance::MODULE_DASHBOARD:
                $this->log->notice(
                    self::INDENT . sprintf(
                        'attaching layout to dashboard for role #%d',
                        $params['identifier']
                    )
                );
                $this->attachToDashboardQuery->execute($params);
                break;

            case Component_Instance::MODULE_EPORTFOLIO:
            case Component_Instance::MODULE_EPORTFOLIO_TEMPLATE:
            default:
                $this->log->warning(
                    self::INDENT . sprintf(
                        'unknown module "%s": skipping',
                        $component['component']['module']
                    )
                );
                break;
        }
    }
};


try {
    $kernel->run($app);
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
