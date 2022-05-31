<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Manager;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Twig\Error\LoaderError;

/**
 * Module for the rubric criteria management form.
 */
class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'rubricManager';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        // Criteria form actions
        $permission = new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            function (array $params = []) {
                return $params['folderId'] ?? null;
            },
            Access::getInstance()
        );
        $preload    = function ($params) {
            return [
                'folder' => $this->getMapperFactory()->get(FolderMapper::class)->findById($params['folderId']),
            ];
        };
        $this->register('GET', '/{folderId}', 'getForm', $permission, $preload);
        $this->register('POST', '/{folderId}', 'postForm', $permission, $preload);
    }


    /**
     * @param IChainLoader $templateLoader
     * @throws LoaderError
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(NodeMapper::class),
            $this->getMapperFactory()->get(RubricDataSource::class),
            $this->injector->get('l10n'),
        );
    }
}
