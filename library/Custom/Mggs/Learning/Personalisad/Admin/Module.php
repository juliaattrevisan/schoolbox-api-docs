<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Mggs\Learning\Personalisad\Admin;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\RoleMapper;
use Twig_Loader_Chain;


class Module extends BaseModule
{
    protected $basename = 'mggsAdminPersonalised';


    /**
     * @inheritdoc
     */
    public function registerRoutes()
    {
        $this->register('GET', '', 'getIndex');
        $this->register('POST', '', 'postRoleInvolvement');
    }


    /**
     * @inheritdoc
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }

    /**
     * @param Container|ContainerInterface $di
     */
    public function initControllers(ContainerInterface $di)
    {
        $di->params[Controller::class] = [
            'roleMapper' => $di->get('mapperFactory')->getLazy(RoleMapper::class),
            'configMapper' => $di->get('mapperFactory')->getLazy(ConfigMapper::class)
        ];

        $di->set("controller:{$this->basename}", $di->lazyNew(Controller::class));
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

}