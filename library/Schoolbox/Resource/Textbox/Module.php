<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Textbox;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;


/**
 * Module for textbox actions and components.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'textbox';


    protected function registerRoutes()
    {
        parent::registerRoutes();

        $folderPreload = function ($params): array {
            /** @var FolderMapper $folderMapper */
            $folderMapper = $this->getMapperFactory()->get(FolderMapper::class);
            return ['folder' => $folderMapper->findById($params['folderId'])];
        };

        $folderAuth = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasHomepageAccess(
                UserAccess::ACCESS_WRITE,
                function ($params): int {
                    return $params['folder']->getId();
                },
                Access::getInstance()
            )
        );

        $textboxPreload = function ($params): array {
            /** @var Mapper $textboxMapper */
            $textboxMapper = $this->getMapperFactory()->get(Mapper::class);
            /** @var Model $textbox */
            $textbox = $textboxMapper->findById($params['id']);
            return [
                'textbox' => $textbox,
                'folder'  => $textbox->getCategory(),
            ];
        };

        $this->register('POST', '/folder/{folderId:\d}', 'createTextboxInFolder', $folderAuth, $folderPreload);
        $this->register('PUT', '/{id:\d}', 'updateTextbox', $folderAuth, $textboxPreload);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'mapper' => $this->getMapperFactory()->getLazy(Mapper::class),
                ]
            )
        );
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
