#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    "Fix Tile Associations",
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

$app = new class extends Daemon\App
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
    private $findComponentQuery;

    /**
     * @var IQuery
     */
    private $updateTileLayoutHeadingQuery;

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
        UPDATE tile_layout
        SET    heading = (
            SELECT cci.value
            FROM       component_instance ci
            INNER JOIN component_config_instance   cci ON  ci.id   = cci.component_instance_id
            INNER JOIN component_config_definition ccd ON  ccd.id  = cci.component_config_definition_id
                                                       AND ccd.key = 'componentname'
            WHERE ci.id = :componentId
            LIMIT 1
        ) WHERE id = :tileLayoutId
    ";


    private const FIND_UNASSOCIATED_TILE_LAYOUTS_QUERY = "
        SELECT tl.id
        FROM      tile_layout                       tl
        LEFT JOIN tile_layout_for_dashboard         tlfd  ON tl.id = tlfd.tile_layout_id
        LEFT JOIN tile_layout_for_folder            tlff  ON tl.id = tlff.tile_layout_id
        LEFT JOIN tile_layout_for_homepage_template tlfht ON tl.id = tlfht.tile_layout_id
        WHERE tlfd.tile_layout_id  IS NULL
        AND   tlff.tile_layout_id  IS NULL
        AND   tlfht.tile_layout_id IS NULL
        ORDER BY tl.id ASC;
    ";


    private const FIND_COMPONENT_QUERY = "
        SELECT
            ci.id                    AS componentId,
            CONVERT (cci.value, INT) AS layoutId,
            ci.module,
            ci.identifier
        FROM       component_config_instance   cci
        INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
        INNER JOIN component_instance          ci  ON cci.component_instance_id          = ci.id
        WHERE ccd.key   = 'layoutId'
        AND   cci.value = :layoutId
        ORDER BY componentId ASC
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
        $this->findComponentQuery            = $this->db->prepareQuery(self::FIND_COMPONENT_QUERY);
        $this->updateTileLayoutHeadingQuery  = $this->db->prepareQuery(self::UPDATE_TILE_LAYOUT_HEADING_QUERY);
        $this->attachToFolderQuery           = $this->db->prepareQuery(self::ATTACH_TO_FOLDER_QUERY);
        $this->attachToDashboardQuery        = $this->db->prepareQuery(self::ATTACH_TO_DASHBOARD_QUERY);
        $this->attachToHomepageTemplateQuery = $this->db->prepareQuery(self::ATTACH_TO_HOMEPAGE_TEMPLATE_QUERY);


        // Find unassociated layouts
        $findUnassociatedTileLayoutsQuery = $this->db->prepareQuery(self::FIND_UNASSOCIATED_TILE_LAYOUTS_QUERY);
        $findUnassociatedTileLayoutsQuery->execute();
        $this->log->notice(sprintf('%d unassociated layouts found', $findUnassociatedTileLayoutsQuery->rowCount()));

        // Fix each one
        $this->db->beginTransaction();
        foreach ($findUnassociatedTileLayoutsQuery->fetchAll() as $row) {
            $layoutId = (int)$row['id'];
            $this->log->notice(sprintf('Checking unassociated tile layout #%d', $layoutId));
            $this->findComponentQuery->execute(['layoutId' => $layoutId]);
            $layout = $this->findComponentQuery->fetch();
            if (!$layout) {
                $this->log->notice(self::indent(1) . 'No component uses this layout: skipping');
                continue;
            }

            // Set the component's heading regardless
            $componentId = (int)$layout['componentId'];
            $this->log->notice(self::indent(1) . sprintf('Setting name from component #%d to layout', $componentId));
            $this->updateTileLayoutHeadingQuery->execute([
                'tileLayoutId' => $layoutId,
                'componentId'  => $componentId,
            ]);

            $identifier   = (int)$layout['identifier'];
            $attachParams = [
                'tileLayoutId' => $layoutId,
                'identifier'   => $identifier,
            ];
            switch ($layout['module']) {
                case Component_Instance::MODULE_HOMEPAGE:
                    $this->log->notice(
                        self::indent(1)
                        . sprintf('Component belongs to folder #%d: attaching layout', $identifier)
                    );
                    $this->attachToFolderQuery->execute($attachParams);
                    break;

                case Component_Instance::MODULE_HOMEPAGE_TEMPLATE:
                    $this->log->notice(
                        self::indent(1)
                        . sprintf('Component belongs to homepage template #%d: attaching layout', $identifier)
                    );
                    $this->attachToHomepageTemplateQuery->execute($attachParams);
                    break;

                case Component_Instance::MODULE_DASHBOARD:
                    $this->log->notice(
                        self::indent(1)
                        . sprintf('Component belongs to dashboard #%d: attaching layout', $identifier)
                    );
                    $this->attachToDashboardQuery->execute($attachParams);
                    break;

                case Component_Instance::MODULE_EPORTFOLIO:
                case Component_Instance::MODULE_EPORTFOLIO_TEMPLATE:
                default:
                    $this->log->warning(
                        self::indent(1)
                        . 'Component belongs to something it should not belong to: skipping'
                    );
                    break;
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
};


try {
    $kernel->run($app);
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
