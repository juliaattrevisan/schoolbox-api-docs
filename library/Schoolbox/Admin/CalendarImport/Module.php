<?php

/**
 * Calendar import admin page
 *
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\CalendarImport;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Component_ConfigDefinitionMapper;
use Component_ConfigMapper;
use Component_InstanceMapper;
use Schoolbox\Assessment\Course\Import\ImportFactory;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\UserMapper;

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
            '/Import',
            'getCalendarImportForm',
            $permission,
        );

        $this->register(
            self::METHOD_POST,
            '/Import',
            'postCalendarImport',
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
