<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Ruleset;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Redis;
use Aura\Di;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Twig_Loader_Chain;

class Module extends BaseModule
{
    protected $basename = 'upsRuleset';

    /**
     * Basic routes for this module
     *
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath) {
        $this->addGetRoute($router, 'intro', $basePath, "");
        $this->addGetRoute($router, 'read', $basePath, "/{rulesetId}");
        $this->addPostRoute($router, 'update', $basePath, "/ruleset/update");
        $this->addGetRoute($router, 'dryRun', $basePath, "/{rulesetId}/dry-run");
    }

    /**
     * Inject the provisioner into the dry run action
     *
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher) {
        $this->addRoutePreload(
            $matcher,
            '(?:dryRun)',
            function () {

                /** @var Di\Container $di */
                $di = $this->getInjector();

                // prep the key Redis will use for this dry run
                $di->values['ups.redisKey'] = INSTANCE_ID . '.provision.user.' . getmypid() . '.dry';

                return array(
                    'provisioner' => $this->getInjector()->get('ups.provisioner'),
                );
            }
        );
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
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
        $this->addTemplatePath($templateLoader, __DIR__ . '/../Navigator/templates');
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
            $injector->lazyNew(Controller::class)
        );
    }
}
