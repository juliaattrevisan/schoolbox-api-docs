<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Admin\Import\Folder;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use InvalidArgumentException;
use Schoolbox\Error;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\RoleMapper;

/**
 * Class Module
 *
 * Bulk add folders admin page
 *
 * @package Schoolbox\Admin\Import\Folder
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'adminImportFolder';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getIndex', $basePath, '');
        $this->addPostRoute($router, 'postImportResults', $basePath, '');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer) {

        $this->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }

    /**
     * {@inheritdoc}
     * @throws ServiceNotFound
     * @throws InvalidArgumentException
     */
    public function initControllers(ContainerInterface $injector): void
    {
        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $injector->get('mapperFactory');
        $injector->set('controller:' . $this->basename, $injector->lazyNew(
            Controller::class,
            [
                'roleMapper' => $mapperFactory->getLazy(RoleMapper::class),
                'errorMapper' => $mapperFactory->getLazy(Error\Mapper::class),
                'homepageTemplateMapper' => $mapperFactory->getLazy(HomepageTemplateMapper::class),
                'configLoader' => $injector->lazyGet('config'),
            ]
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader) {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
