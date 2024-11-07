<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\UserMapper;

/**
 * Class Module
 *
 * @package Schoolbox\Admin\Import
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminImportIndex';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_TIMETABLE, UserAccess::ACCESS_FULL)
            )
        );

        $this->register('POST', '/schoolDataImport/liss/delete', 'postDeleteLissPayload', $perms);
        $this->register('GET', '/schoolDataImport', 'getSchoolDataIndex', $perms);
        $this->register('POST', '/schoolDataImport', 'postSchoolDataOptions', $perms);
        $this->register('GET', '/schoolDataImport/status/indicator', 'getStatusIndicator', $perms);
        $this->register('GET', '/schoolDataImport/status/report', 'getStatusReport', $perms);
        $this->register('GET', '/schoolDataImport/performUpdate', 'getPerformUpdate', $perms);
        $this->register('GET', '/schoolDataImport/export', 'getExport', $perms);
        $this->register('GET', '/schoolDataImport/timetable/export', 'getTimetableExport', $perms);
        $this->register('GET', '/schoolDataImport/classes/export', 'getClassesExport', $perms);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ConfigMapper::class),
            new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory()),
            $this->injector->get('moduleCache.rebuilder'),
            $this->injector->get('oidc.cache'),
            $this->getMapperFactory()->get(\Schoolbox\External\Connector\Log\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\External\Event\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\External\ClassEntryMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Resource\Storage\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Resource\File\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\External\Connector\ImportQueue\Mapper::class),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
}
