<?php

/**
 * Course builder admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\LTI;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\LTI\Provider;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

class Module extends BaseModule
{
    protected $basename = 'adminLti';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(string $basePath)
    {
        $this->addGetRoute('getIndex', $basePath, "");
        $this->addGetRoute('getModify', $basePath, "/modify/{providerId}");
        $this->addPostRoute('postModify', $basePath, "/modify");
        $this->addPostRoute('postDelete', $basePath, "/delete/{providerId}");
        $this->addPostRoute('postConfig', $basePath, '/postConfig');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
             ->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ConfigMapper::class),
            new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory()),
            $this->injector->get('moduleCache.rebuilder'),
            $this->injector->get('oidc.cache'),
            $this->getMapperFactory()->get(Provider\Mapper::class),
            $this->getMapperFactory()->get(Provider\RedirectUrl\Mapper::class),
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
     * @return Provider\Mapper
     */
    public function getProviderMapper()
    {
        return $this->getMapperFactory()->get(Provider\Mapper::class);
    }
}
