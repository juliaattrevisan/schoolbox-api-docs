<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\GoogleFile;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule\All;
use Alaress\Authorizer\Rule\Any;
use Alaress\Authorizer\Rule\HasHomepageAccess;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'googleFile';

    /**
     * @inheritDoc
     */
    function registerRoutes(): void
    {
        $this->register(
            self::METHOD_POST,
            '/{folderId:\d+}',
            'postGoogleFile',
            new All(
                new IsLoggedIn(),
                new Any(
                    new IsSuperuser(),
                    new HasHomepageAccess(
                        UserAccess::ACCESS_WRITE,
                        function (array $params = []) {
                            return $params['folderId'];
                        },
                        Access::getInstance()
                    )
                ),
            ),
            function ($params) {
                return $this->preloadFolder($params);
            }
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'googleFileMapper' => $this->getMapperFactory()->getLazy(Mapper::class),
                    'userMapper'       => $this->getMapperFactory()->getLazy(UserMapper::class),
                    'userAccessMapper' => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
                ]
            )
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
     * @throws BaseMapper\DefinitionException
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
            } catch (BaseMapper\Exception $e) {
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
        /** @var FolderMapper $mapper **/
        $mapper = $this->getMapperFactory()->get(FolderMapper::class);

        return $mapper;
    }
}
