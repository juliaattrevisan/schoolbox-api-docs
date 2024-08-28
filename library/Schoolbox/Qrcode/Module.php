<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Qrcode;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;

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
        $preloadFolder = function ($params) {
            return $this->preloadFolder($params);
        };

        $canGenerate = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsConfigOn(ConfigMapper::NAME_QR_LOGIN_ENABLED, $this->getConfig()),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\All(
                    new Rule\HasRole(Role::STAFF),
                    new Rule\HasHomepageAccess(
                        UserAccess::ACCESS_WRITE,
                        function ($params) {
                            return $params['folderId'];
                        },
                        Access::getInstance()
                    ),
                    // Check whether QR code generation is enabled via folder year level
                    new Rule\Callback(
                        function (array $params): void {
                            try {
                                if (($params['folder'] ?? null) instanceof Folder) {
                                    $folder = $params['folder'];
                                } else {
                                    $folder = $this->getFolderMapper()->findById($params['folderId']);
                                }
                                if (!$folder->isQrcodeEnabled()) {
                                    throw new InvalidPath("QR Generation is not available for folder: {$folder->getId()}");
                                }
                            } catch (Mapper\Exception $e) {
                                throw new InvalidPath("No folder found for id: {$params['folderId']}", 0, $e);
                            }
                        }
                    )
                )
            ),
        );
        $this->register('GET', '/{folderId:\d+}', 'getListPage', $canGenerate, $preloadFolder);
        $this->register('POST', '/{folderId:\d+}/generate', 'generate', $canGenerate, $preloadFolder);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(UserAccessMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\User\Token\Mapper::class),
            $this->injector->get('user.tokenBuilder'),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @return Mapper|FolderMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }


    /**
     * Loads Folder Model instance from route params
     *
     * @param array $params
     * @return array
     * @throws MapperException
     */
    private function preloadFolder(array $params): array
    {
        $folder   = 'folder';
        $folderId = 'folderId';

        if (($params[$folder] ?? null) instanceof Folder) {
            $preload[$folder] = $params[$folder];
        } elseif (!array_key_exists($folderId, $params)) {
            throw new \UnexpectedValueException("No 'folderId' found in route params");
        } else {
            $preload[$folder] = $this->getFolderMapper()->findById($params[$folderId]);
        }

        return $preload;
    }
}
