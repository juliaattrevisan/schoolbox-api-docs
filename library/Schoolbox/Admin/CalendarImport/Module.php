<?php

/**
 * Calendar import admin page
 *
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\CalendarImport;

use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\CampusMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\RoleMapper;

class Module extends BaseModule
{
    protected $basename = 'adminCalendarImport';

    /**
     * @return void
     */
    protected function registerRoutes(): void
    {
        $permission  = new Rule\All(
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(
                    UserAccess::SERVICE_CALENDAR,
                    UserAccess::ACCESS_FULL
                )
            )
        );

        $this->register(
            self::METHOD_GET,
            '/import/school-events',
            'getCalendarImportForm',
            $permission,
        );

        $this->register(
            self::METHOD_POST,
            '/import/school-events',
            'postCalendarImport',
            $permission,
        );

        $this->register(
            self::METHOD_POST,
            '/import/school-events/run',
            'postRunCalendarImport',
            $permission,
        );

        $this->register(
            self::METHOD_GET,
            '/import/group-events',
            'getCalendarGroupEventsImportForm',
            $permission,
        );

        $this->register(
            self::METHOD_POST,
            '/import/group-events',
            'postCalendarGroupEventsImport',
            $permission,
        );

        $this->register(
            self::METHOD_POST,
            '/import/group-events/run',
            'postRunCalendarGroupEventsImport',
            $permission,
        );

        $this->register(
            self::METHOD_GET,
            '/import/icalendar-events',
            'getCalendarVcalImportForm',
            $permission,
        );

        $this->register(
            self::METHOD_POST,
            '/import/icalendar-events',
            'postCalendarVcalImportForm',
            $permission,
        );

        $this->register(
            self::METHOD_POST,
            '/import/icalendar-events/run',
            'postRunCalendarVcalImportForm',
            $permission,
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->getMapperFactory()->get(CollectorFactory::class),
            $this->injector->get('filesystem.storageProxy'),
            $this->getMapperFactory()->get(CampusMapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(StorageMapper::class),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
