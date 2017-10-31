<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Import;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Redis;
use Alaress\Web\Router\PathMatcher;
use Aura\Di;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Alaress\Twig\Loader\IChainLoader;

/**
 * Module for admin importer.
 *
 * @package Schoolbox\Import
 */
class ImportModule extends BaseModule
{
    public function __construct(ContainerInterface $injector, $basename)
    {
        parent::__construct($injector);
        $this->basename = $basename;
    }

    /**
     * Basic routes for this module
     *
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        // Importer paths
        $this->addGetRoute($router, 'upload', $basePath, "");
        $this->addPostRoute($router, 'preview', $basePath, "/preview");
        $this->addPostRoute($router, 'import', $basePath, "/import");
    }

    /**
     * Only admins can use this
     *
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }

    /**
     * Template locations for this module
     *
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates/' . $this->basename);
    }

    /**
     * Set up controllers
     *
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(
                ImportController::class,
                ['basename' => $this->basename]
            )
        );
    }
}
