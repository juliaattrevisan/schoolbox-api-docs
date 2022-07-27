<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule\All;
use Alaress\Authorizer\Rule\IsConfigOn;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'group';

    /**
     * @inheritDoc
     */
    public function registerRoutes(): void
    {
        $perm = new All(
            new IsLoggedIn(),
        );

        $preloadFolder = function ($params) {
            return $this->preloadFolder($params);
        };

        $this->register(self::METHOD_GET, '/modify/{folderId:\d+}', 'getModify', $perm, $preloadFolder);
        $this->register(self::METHOD_GET, '/getData/{folderId:\d+}', 'getData', $perm, $preloadFolder);
        $this->register(self::METHOD_DELETE, '/removeMembers/{folderId:\d+}', 'removeMembers', $perm, $preloadFolder);
        $this->register(self::METHOD_PATCH, '/updateMembers/{folderId:\d+}', 'updateMembers', $perm, $preloadFolder);
        $this->register(self::METHOD_POST, '/addMembers/{folderId:\d+}', 'addMembers', $perm, $preloadFolder);
        $this->register(self::METHOD_POST, '/updateSettings/{folderId:\d+}', 'updateSettings', $perm, $preloadFolder);
        $this->register(self::METHOD_POST, '/disband/{folderId:\d+}', 'disband', $perm, $preloadFolder);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(GroupMapper::class),
            $this->getMapperFactory()->get(UserAccessMapper::class),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(\Timetable_ColourMapper::class),
            $this->getMapperFactory()->get(RerouteOverrideMapper::class),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @param array $params
     * @return array
     * @throws InvalidPath
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    private function preloadFolder(array $params): array
    {
        if (($params['folder'] ?? null) instanceof \Schoolbox\Resource\Folder\Model) {
            $preload['folder'] = $params['folder'];
        } elseif (!\array_key_exists('folderId', $params)) {
            throw new \UnexpectedValueException("No 'folderId' found in route params");
        } else {
            try {
                $preload['folder'] = $this->getFolderMapper()->findById($params['folderId']);
            } catch (MapperException $e) {
                throw new InvalidPath('Folder Not Found', 0, $e);
            }
        }
        return $preload;
    }

    /**
     * @return FolderMapper
     * @throws \InvalidArgumentException
     */
    private function getFolderMapper(): FolderMapper
    {
        /** @var FolderMapper $mapper * */
        $mapper = $this->getMapperFactory()->get(FolderMapper::class);

        return $mapper;
    }

}
