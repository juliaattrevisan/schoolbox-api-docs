<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Socs;

use Alaress\Database\Predicate\Builder;
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Calendar_Api_Config_Connector_Abstract;
use Calendar_Api_Config_Connector_Socs;
use Calendar_Api_Config_Mapper;
use Calendar_Api_Config_Record;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Config\ConfigMapper;

class Controller extends ConfigController
{
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
        private Calendar_Api_Config_Mapper $calendarConfigMapper,
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }

    public function getSettings(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->routeGenerator->generate('socsadmin.postSave'),
            [ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false],
        );

        $this->response = $this->responseFromTemplate(
            '@socsadmin/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('socsadmin.getSettings'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))
                    ->generateBreadcrumbs($this->routeGenerator->generate('socsadmin.getSettings')),
                'configContext' => $configContext,
            ],
            'SOCS System Settings',
        );
    }

    public function postSave(): void
    {
        $postData = $this->requestPost->getArrayCopy();

        $this->updateConfig($postData, $this->buildConfigPredicate());
        $this->syncCalendarConnector($postData);

        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('socsadmin.getSettings'));
    }

    private function buildConfigPredicate(): Builder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_TYPE, ConfigMapper::TYPE_SOCS);
    }

    private function syncCalendarConnector(array $postData): void
    {
        $connector = new Calendar_Api_Config_Connector_Socs();
        $calendarName = trim((string)($postData[ConfigMapper::NAME_SOCS_CALENDAR_NAME] ?? ''));
        if ($calendarName === '') {
            $calendarName = 'SOCS Events';
        }

        $connector->setLabel($calendarName);
        $connector->setEventType('Event');
        $connector->setConnectorClass('Socs');
        $connector->setLuEventType((int)($postData[ConfigMapper::NAME_SOCS_CALENDAR_DEFAULT_EVENT_TYPE] ?? 1));

        $this->syncManagedConnector(
            $connector,
            !empty($postData[ConfigMapper::NAME_SOCS_ENABLED])
                && !empty($postData[ConfigMapper::NAME_SOCS_IMPORT_CALENDAR_EVENTS])
        );
    }

    private function syncManagedConnector(Calendar_Api_Config_Connector_Abstract $connector, bool $isActive): void
    {
        try {
            $record = $this->calendarConfigMapper->findOneByColumnList([
                'event_type' => $connector->getEventType(),
                'connector_class' => $connector->getConnectorClass(),
            ]);
        } catch (\Throwable) {
            $record = new Calendar_Api_Config_Record();
            $record->setEventType($connector->getEventType());
            $record->setConnectorClass($connector->getConnectorClass());
        }

        $record->updateConfig($connector);
        if ($isActive) {
            $record->activate();
        } else {
            $record->deactivate();
        }

        $this->calendarConfigMapper->save($record);
    }
}
