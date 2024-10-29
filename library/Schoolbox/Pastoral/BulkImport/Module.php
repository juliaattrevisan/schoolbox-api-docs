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
use Schoolbox\Permission\Access\UserAccess;

/**
 * Module for bulk importation of pastoral records.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'pastoralBulkImport';

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
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    public function buildController(): Base
    {
        return new Controller();
    }
}
