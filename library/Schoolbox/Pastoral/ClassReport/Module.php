<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\ClassReport;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\Role;

class Module extends BaseModule
{
    /**
     * @var string $basename Module basename
     */
    protected $basename = 'pastoralClassReportModule';

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
            new Rule\IsConfigOn(ConfigMapper::NAME_PASTORAL_ENABLED, $this->getConfig()),
            new Rule\HasRolePermission(Role::PERMISSION_ACCESS_PASTORAL, Role::STAFF),
            new Rule\HasHomepageAccess(
                UserAccess::ACCESS_WRITE,
                function (array $params = []) {
                    if ($params['folder'] instanceof Model) {
                        return $params['folder']->getId();
                    } else {
                        return null;
                    }
                },
                Access::getInstance()
            )
        );

        $preload = function ($params) {
            return $this->preloadFolder($params);
        };

        $this->register('GET', '/{folderId}', 'getReport', $perms, $preload);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @param array $params
     * @return array
     */
    private function preloadFolder(array $params): array
    {
        try {
            $folder = $this->getFolderMapper()->findById($params['folderId']);
        } catch (MapperException $e) {
            throw new NotFound();
        }

        return ['folder' => $folder];
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper(): FolderMapper
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }
}
