<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Textbox;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
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
            try {
                $result = $folderPreload($params);
            } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                throw new NotFound('Folder was deleted or does not exist.');
            }

            /** @var Component_InstanceMapper $componentMapper */
            $componentMapper = $this->getMapperFactory()->get(Component_InstanceMapper::class);
            $component       = $componentMapper->findByInstanceId($params['componentId'], $result['folder']->getId());
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
                    if (isset($params['folder'])) {
                        return $params['folder']->getId();
                    }

                    /* In unit planner you might know of a folder id, but
                     * not have its instance; it was multiplayer deleted
                     * or otherwise not hydrated into $params[folder]
                     */
                    if (isset($params['folderId'])) {
                        return $params['folderId'];
                    }

                    /* Neither a folder instance nor a folderId was provided
                     * Can't determine where you're trying to go; kick you out
                     */
                    throw new Unauthorized();
                },
                Access::getInstance()
            )
        );

        $folderAuthView = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasHomepageAccess(
                UserAccess::ACCESS_READ,
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
        $this->register('GET', '/folder/{folderId}/usage', 'getUsageForFolder', $folderAuth, $folderPreload);
        $this->register('POST', '/folder/{folderId:\d+}', 'createTextboxInFolder', $folderAuth, $folderPreload);
        $this->register(
            'POST',
            '/folder/{folderId:\d+}/component/{componentId:\d+}',
            'createTextboxInFolderAndComponent',
            $folderAuth,
            $componentPreload
        );
        $this->register('POST', '/rename/{folderId:\d+}', 'postRename', $folderAuth, $folderPreload);
        $this->register('PUT', '/{id:\d+}', 'updateTextbox', $folderAuth, $textboxPreload);
        $this->register('DELETE', '/{id:\d+}', 'deleteTextbox', $folderAuth, $textboxPreload);
        $this->register('GET', '/{id:\d+}', 'getTextbox', $folderAuthView, $textboxPreload);
        $this->register(
            'GET',
            '/textboxSearch',
            'textboxSearch',
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\IsSuperuser()
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(Component_ConfigMapper::class),
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
