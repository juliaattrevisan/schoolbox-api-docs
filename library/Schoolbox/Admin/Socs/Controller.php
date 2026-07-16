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
use Calendar_Api_Config_Connector_SocsMusic;
use Calendar_Api_Config_Mapper;
use Calendar_Api_Config_Record;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;

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

        // grab the calendar failed counts if they exist
        [$eventType, $socsCalendarClass, $socsMusicClass] = ['Event', Calendar_Api_Config_Connector_Socs::CONNECTOR_CLASS, Calendar_Api_Config_Connector_SocsMusic::CONNECTOR_CLASS];
        try {
            $calendarEvents = $this->calendarConfigMapper->findOneByColumnList([
                'event_type' => $eventType,
                'connector_class' => $socsCalendarClass,
            ]);
        } catch (MapperException\NotFound) {
            $calendarEvents = $this->calendarConfigMapper->createModel()
                ->setEventType($eventType)
                ->setConnectorClass($socsCalendarClass);
        }
        try {
            $musicLessons = $this->calendarConfigMapper->findOneByColumnList([
                'event_type' => $eventType,
                'connector_class' => $socsMusicClass,
            ]);
        } catch (MapperException\NotFound) {
            $musicLessons = $this->calendarConfigMapper->createModel()
                ->setEventType($eventType)
                ->setConnectorClass($socsMusicClass);
        }

        $this->response = $this->responseFromTemplate(
            '@socsadmin/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('socsadmin.getSettings'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))
                    ->generateBreadcrumbs($this->routeGenerator->generate('socsadmin.getSettings')),
                'configContext' => $configContext,
                'subscriptions' => [
                    'musicLessons' => $musicLessons,
                    'calendarEvents' => $calendarEvents,
                ],
                'calendarMaxFailed' => Calendar_Api_Config_Record::MAXFAILED,
            ],
            'SOCS System Settings',
        );
    }

    public function postSave(): void
    {
        $postData = $this->requestPost->getArrayCopy();

        $this->updateConfig($postData, $this->buildConfigPredicate());
        $this->syncCalendarConnector();

        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('socsadmin.getSettings'));
    }

    private function buildConfigPredicate(): Builder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_TYPE, ConfigMapper::TYPE_SOCS);
    }

    private function syncCalendarConnector(): void
    {
        $configValues = $this->getConfigMapper()
            ->findByPredicates($this->buildConfigPredicate())
            ->indexBy(ConfigMapper::COL_NAME);

        $connector = new Calendar_Api_Config_Connector_Socs();
        $calendarName = trim((string)$configValues[ConfigMapper::NAME_SOCS_CALENDAR_NAME]->getValue());
        if ($calendarName === '') {
            $calendarName = 'SOCS Events';
        }

        $connector->setLabel($calendarName)
            ->setEventType('Event')
            ->setConnectorClass(Calendar_Api_Config_Connector_Socs::CONNECTOR_CLASS)
            ->setLuEventType((int)$configValues[ConfigMapper::NAME_SOCS_CALENDAR_DEFAULT_EVENT_TYPE]->getValue());

        $this->syncManagedConnector(
            $connector,
            (bool)$configValues[ConfigMapper::NAME_SOCS_ENABLED]->getValue()
                && (bool)$configValues[ConfigMapper::NAME_SOCS_IMPORT_CALENDAR_EVENTS]->getValue()
        );

        $musicConnector = new Calendar_Api_Config_Connector_SocsMusic();
        $musicLessonsName = trim((string)$configValues[ConfigMapper::NAME_SOCS_MUSIC_LESSONS_NAME]->getValue());
        if ($musicLessonsName === '') {
            $musicLessonsName = 'SOCS Music Lessons';
        }

        $musicConnector->setLabel($musicLessonsName)
            ->setEventType('Event')
            ->setConnectorClass(Calendar_Api_Config_Connector_SocsMusic::CONNECTOR_CLASS)
            ->setLuEventType((int)$configValues[ConfigMapper::NAME_SOCS_MUSIC_LESSONS_DEFAULT_EVENT_TYPE]->getValue());

        $this->syncManagedConnector(
            $musicConnector,
            (bool)$configValues[ConfigMapper::NAME_SOCS_ENABLED]->getValue()
                && (bool)$configValues[ConfigMapper::NAME_SOCS_IMPORT_MUSIC_LESSONS]->getValue()
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
            $record = $this->calendarConfigMapper->createModel()
                ->setEventType($connector->getEventType())
                ->setConnectorClass($connector->getConnectorClass());
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
