<?php
/**
 * Course builder admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\LTI;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\LTI\Provider\Mapper as ProviderMapper;
use Alaress\Web\Router\PathMatcher;


class Module extends BaseModule
{
    protected $basename = 'adminLti';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getIndex', $basePath, "");
        $this->addGetRoute($router, 'getModify', $basePath, "/modify/{providerId}");
        $this->addPostRoute($router, 'postModify', $basePath, "/modify");
        $this->addPostRoute($router, 'postDelete', $basePath, "/delete/{providerId}");
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer) {

        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
             ->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector) {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader) {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Preload required data for the various routes

        $providerMapper = $this->getProviderMapper();

        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) use ($providerMapper) {

                $returnArray = [];

                // Load LTI provider

                if (array_key_exists('providerId', $params)) {
                    $returnArray['provider'] = $providerMapper->findById($params['providerId']);
                }

                return $returnArray;
            }
        );
    }

    /**
     * @return ProviderMapper
     */
    public function getProviderMapper()
    {
        return $this->getMapperFactory()->get(ProviderMapper::class);
    }

}
