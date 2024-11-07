<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\External;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Class Module
 *
 * @package Schoolbox\Admin\Import\Profile
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminImportProfile';


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

        $this->register('GET', '', 'getIndex', $perms);
        $this->register('GET', '/export/{type}', 'getExport', $perms);
        $this->register('POST', '/upload', 'postUpload', $perms);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(External\Profile\Mapper::class),
            $this->getMapperFactory()->get(External\Profile\Parent\Mapper::class),
            $this->getMapperFactory()->get(External\Profile\Student\Mapper::class),
            $this->getMapperFactory()->get(External\Profile\Staff\Mapper::class),
            $this->getMapperFactory()->get(External\Profile\Relationship\Mapper::class),
            $this->injector->get('filesystem.storageProxy'),
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
