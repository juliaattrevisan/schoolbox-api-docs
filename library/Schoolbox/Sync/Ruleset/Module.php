<?php

/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Ruleset;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\File\MimetypeMapper;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    protected $basename = 'upsRuleset';

    /**
     * Basic routes for this module
     *
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'logs', $basePath, "/logs");
        $this->addGetRoute($router, 'logFormat', $basePath, "/log/{logId}.{format}")->addTokens(['logId' => '\d+']);
        $this->addGetRoute($router, 'log', $basePath, "/log/{logId}");
        $this->addGetRoute($router, 'intro', $basePath, "");
        $this->addGetRoute($router, 'read', $basePath, "/{rulesetId}");
        $this->addPostRoute($router, 'updateRuleset', $basePath, "/ruleset/update");
        $this->addPostRoute($router, 'updateRule', $basePath, "/ruleset/rule/update");
        $this->addGetRoute($router, 'dryRun', $basePath, "/{rulesetId}/dry-run");
        $this->addGetRoute($router, 'performUpdate', $basePath, "/{rulesetId}/perform-update");
    }

    /**
     * Inject the provisioner into the dry run action
     *
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(?:dryRun|performUpdate)',
            function () {
                // AFAICT ups.redisKey is unused
                // // prep the key Redis will use for this dry run
                // $di->values['ups.redisKey'] = INSTANCE_ID . '.provision.user.' . getmypid() . '.dry';
                return [
                    'provisioner' => $this->injector->get('ups.provisioner'),
                ];
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
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
        $this->addTemplatePath($templateLoader, __DIR__ . '/../Navigator/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(MimetypeMapper::class),
            $this->injector->get('filesystem.storageProxy'),
        );
    }
}
