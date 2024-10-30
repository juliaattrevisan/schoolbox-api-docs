<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

/**
 * Module for bulk importation of pastoral records.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'pastoralBulkImport';

    /**
     * @return void
     */
    protected function registerRoutes(): void
    {
        $permission = new Rule\All(
            new Rule\IsConfigOn(ConfigMapper::NAME_PASTORAL_BULK_UPLOAD_ENABLED, $this->getConfig()),
            new Rule\HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL),
        );
        $this->register(
            self::METHOD_GET,
            '',
            'getForm',
            $permission,
        );
        $this->register(
            self::METHOD_POST,
            '/preview',
            'postImportPreview',
            $permission,
        );
        $this->register(
            self::METHOD_POST,
            '',
            'postImport',
            $permission,
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('l10n'),
            $this->getMapperFactory()->get(Pastoral\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Type\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\SubType\Mapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(Pastoral\Access\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Group\Mapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(Pastoral\Audience\Mapper::class),
            $this->injector->get('filesystem.storageProxy'),
        );
    }
}
