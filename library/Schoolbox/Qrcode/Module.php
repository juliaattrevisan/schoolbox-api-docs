<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Qrcode;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;

/**
 * Class Module
 *
 * @package Schoolbox\Qrcode
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'qrcode';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $preloadFolder = function (array $params) {
            try {
                if (($params['folder'] ?? null) instanceof Folder) {
                    $folder = $params['folder'];
                } else {
                    $folder = $this->getFolderMapper()->findById($params['folderId']);
                }
                return ['folder' => $folder];
            } catch (Mapper\Exception $e) {
                throw new NotFound("No folder found for id: {$params['folderId']}", 0, $e);
            }
        };

        $canGenerate = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\All(
                    new Rule\HasRole(Role::STAFF),
                    new Rule\HasHomepageAccess(
                        UserAccess::ACCESS_WRITE,
                        function ($params) {
                            if (($params['folder'] ?? null) instanceof Folder) {
                                return $params['folder'];
                            }
                            throw new NotFound("No folder found for id: {$params['folderId']}");
                        },
                        Access::getInstance()
                    )
                )
            ),
        );
        $this->register('GET', '/{folderId:\d+}', 'getListPage', $canGenerate, $preloadFolder);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'errorMapper' => $this->getMapperFactory()->getLazy(ErrorMapper::class),
        ];
    }


    /**
     * @return Mapper|FolderMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }
}
