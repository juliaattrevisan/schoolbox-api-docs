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
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Permission\Access\UserAccess;
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
            new Rule\IsConfigOn(ConfigMapper::NAME_PASTORAL_BULK_IMPORT_ENABLED, $this->getConfig()),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_WRITE),
            ),
        );
        $preload = fn (array $params): array => [
            'import' => $this->getMapperFactory()->get(Mapper::class)->findById($params['id']),
        ];
        $this->register(
            self::METHOD_GET,
            '',
            'getForm',
            $permission,
        );
        $this->register(
            self::METHOD_POST,
            '/create',
            'postCreateImport',
            $permission,
        );
        $this->register(
            self::METHOD_GET,
            '/{id}',
            'getImport',
            $permission,
            $preload,
        );
        $this->register(
            self::METHOD_POST,
            '/{id}/run',
            'postRunImport',
            $permission,
            $preload,
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
        $pastoralRecordProcessor = new Pastoral\RecordProcessor(
            $this->injector->get('config'),
            $this->injector->get('pastoralDataSource'),
            $this->getMapperFactory()->get(Pastoral\SubType\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Type\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Severity\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Tag\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Action\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\RecordAction\Mapper::class),
            $this->getMapperFactory()->get(PastoralAttachmentMapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(Pastoral\Access\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Group\Mapper::class),
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->injector->get('sentry'),
        );
        return new Controller(
            $this->injector->get('l10n'),
            $this->getMapperFactory()->get(Mapper::class),
            $this->injector->get('pastoralDataSource'),
            $this->getMapperFactory()->get(Pastoral\SubType\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Type\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Severity\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Tag\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Access\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Group\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Attachment\Mapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(Pastoral\Audience\Mapper::class),
            $this->injector->get('filesystem.storageProxy'),
            $this->injector->get('filesystem.receiver'),
            $pastoralRecordProcessor,
        );
    }
}
