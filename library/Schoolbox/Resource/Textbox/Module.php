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
use Component_ConfigMapper;
use Component_InstanceMapper;
use Schoolbox\Mapper\Exception\NotFound;
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


    /**
     * @inheritDoc
     */
    protected function registerRoutes()
    {
        parent::registerRoutes();

        $folderPreload = function ($params): array {
            /** @var FolderMapper $folderMapper */
            $folderMapper = $this->getMapperFactory()->get(FolderMapper::class);
            return ['folder' => $folderMapper->findById($params['folderId'])];
        };

        $componentPreload = function ($params) use ($folderPreload): array {
            $result = $folderPreload($params);

            /** @var Component_InstanceMapper $componentMapper */
            $componentMapper = $this->getMapperFactory()->get(Component_InstanceMapper::class);
            $component       = $componentMapper->findByInstanceId($params['componentId']);
            if (!$component) {
                throw new NotFound('Unable to find component');
            }
            if ($component->getDefinition()->getClass() !== Component\Homepage\Controller::class) {
                throw new NotFound('Component is not a textbox');
            }
            $result['component'] = $component;
            return $result;
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

        $this->register('GET', '/folder/{folderId}', 'getIndexForFolder', $folderAuth, $folderPreload);
        $this->register('POST', '/folder/{folderId:\d+}', 'createTextboxInFolder', $folderAuth, $folderPreload);
        $this->register(
            'POST',
            '/folder/{folderId:\d+}/component/{componentId:\d+}',
            'createTextboxInFolderAndComponent',
            $folderAuth,
            $componentPreload
        );
        $this->register('PUT', '/{id:\d+}', 'updateTextbox', $folderAuth, $textboxPreload);
        $this->register('DELETE', '/{id:\d+}', 'deleteTextbox', $folderAuth, $textboxPreload);
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
                    'mapper'                => $this->getMapperFactory()->getLazy(Mapper::class),
                    'componentConfigMapper' => $this->getMapperFactory()->getLazy(Component_ConfigMapper::class),
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
