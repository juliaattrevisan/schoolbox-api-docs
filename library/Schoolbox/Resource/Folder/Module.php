<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Folder;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Alaress\Twig\Loader\IChainLoader;
use InvalidArgumentException;
use Schoolbox\Curriculum;
use Schoolbox\Error;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\File;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\RoleMapper;
use Schoolbox\YearLevel;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'folder';


    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $loggedInAccess = new Rule\IsLoggedIn();

        $parentWriteAccess = new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            static function (array $params): int {
                return (int)$params['parentId'];
            },
            Access::getInstance()
        );

        $preloadParent = function (array $params) {
            if (($params['parent'] ?? null) instanceof Model) {
                return ['parent' => $params['parent']];
            }
            try {
                return ['parent' => $this->getMapper()->findById($params['parentId'])];
            } catch (MapperException $e) {
                throw new InvalidPath($e->getMessage(), 0, $e);
            }
        };

        $folderWriteAccess = new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            static function (array $params): int {
                return (int)$params['id'];
            },
            Access::getInstance()
        );
        $preloadFolder     = function (array $params) {
            if (($params['folder'] ?? null) instanceof Model) {
                return ['folder' => $params['folder']];
            }
            try {
                return ['folder' => $this->getMapper()->findById($params['id'])];
            } catch (MapperException $e) {
                throw new InvalidPath($e->getMessage(), 0, $e);
            }
        };

        $permWriteBoth = new Rule\All($folderWriteAccess, $parentWriteAccess);
        $preloadBoth   = function (array $params) {
            try {
                return [
                    'folder'    => ($params['folder'] ?? null) instanceof Model
                        ? $params['folder']
                        : $this->getMapper()->findById($params['id']),
                    'newParent' => ($params['newParent'] ?? null) instanceof Model
                        ? $params['newParent']
                        : $this->getMapper()->findById($params['parentId']),
                ];
            } catch (MapperException $e) {
                throw new InvalidPath($e->getMessage(), 0, $e);
            }
        };

        // Must have WRITE+ access and folder must not be root folder
        $writeAccessAndNotRoot = new Rule\All(
            $folderWriteAccess,
            new Rule\Callback(static function (array $params = []) {
                /** @var Model $folder */
                $folder = $params['folder'];
                if ($folder->getId() === Model::ROOT_ID) {
                    throw new InvalidPath("Folder is root");
                }
            }));

        $this->register('GET',    '/mygroups',                            'getGroups',        $loggedInAccess);
        $this->register('GET',    '/folder/{parentId:\d+}/create',        'getFolderCreate',  $parentWriteAccess, $preloadParent);
        $this->register('POST',   '/folder/{parentId:\d+}/create',        'postCreateFolder', $parentWriteAccess, $preloadParent);
        $this->register('GET',    '/folder/{id:\d+}/modify',              'getFolderModify',  $writeAccessAndNotRoot, $preloadFolder);
        $this->register('POST',   '/folder/{id:\d+}/update',              'postUpdateFolder', $writeAccessAndNotRoot, $preloadFolder);
        $this->register('PUT',    '/folder/{id:\d+}/move/{parentId:\d+}', 'putMoveFolder',    $permWriteBoth,     $preloadBoth);
        $this->register('DELETE', '/folder/{id:\d+}',                     'deleteFolder',     $writeAccessAndNotRoot, $preloadFolder);
        $this->register('POST',   '/folder/{id:\d+}/reset',               'postFolderReset',  $writeAccessAndNotRoot, $preloadFolder);
    }


    /**
     * @return Mapper
     * @throws InvalidArgumentException
     */
    private function getMapper(): Mapper
    {
        return $this->getMapperFactory()->get(Mapper::class);
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/Templates');
    }

    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $mapperFactory = $this->getMapperFactory();
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'folderMapper'           => $mapperFactory->getLazy(Mapper::class),
                'homepageConfigMapper'   => $mapperFactory->getLazy(HomepageConfigMapper::class),
                'homepageTemplateMapper' => $mapperFactory->getLazy(HomepageTemplateMapper::class),
                'roleAccessMapper'       => $mapperFactory->getLazy(RoleAccessMapper::class),
                'userAccessMapper'       => $mapperFactory->getLazy(UserAccessMapper::class),
                'roleMapper'             => $mapperFactory->getLazy(RoleMapper::class),
                'errorMapper'            => $mapperFactory->getLazy(Error\Mapper::class),
                'access'                 => $mapperFactory->getLazy(Access::class),
                'curriculumNodeMapper'   => $mapperFactory->getLazy(Curriculum\Node\Mapper::class),
                'groupMapper'            => $mapperFactory->getLazy(GroupMapper::class),
                'fileMapper'             => $mapperFactory->getLazy(File\Mapper::class),
                'yearLevelMapper'        => $mapperFactory->getLazy(YearLevel\Mapper::class),
            ])
        );
    }
}
