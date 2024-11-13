<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import\Timetable;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Import\ImporterFactory;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Class Module
 *
 * @package Schoolbox\Admin\Import\Timetable
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminImportTimetable';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSchoolboxConnector($this->getConfig()),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_TIMETABLE, UserAccess::ACCESS_FULL)
            )
        );

        $this->register('GET', '', 'getUpload', $perms);
        $this->register('POST', '/preview', 'postPreview', $perms);
        $this->register('POST', '/import', 'postImport', $perms);
        $this->register('GET', '/export', 'getExport', $perms);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            new ImporterFactory(),
            $this->getConfig(),
            $this->injector->get('filesystem.storageProxy'),
            $this->getMapperFactory()->get(TimetableCycleMapper::class),
            $this->getMapperFactory()->get(TimetablePeriodMapper::class),
            $this->getMapperFactory()->get(ClassPeriodMapper::class),
            $this->getMapperFactory()->get(ClassEntryMapper::class),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
