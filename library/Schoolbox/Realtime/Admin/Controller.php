<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Admin;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Realtime\Config;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Message\Source;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\RoleMapper;

class Controller extends BaseController
{

    /** @var RoleMapper */
    protected $roleMapper;

    /** @var NSMapper */
    protected $nsMapper;

    /** @var SourceMapper */
    protected $sourceMapper;

    /** @var RoutePermissionMapper */
    protected $routePermissionMapper;

    /** @var RerouteOverrideMapper */
    protected $rerouteOverrideMapper;

    /** @var Config */
    protected $config;


    /**
     * @return NSMapper
     */
    public function getNsMapper()
    {
        return $this->nsMapper ?: NSMapper::getInstance(); // TODO remove dingleton
    }


    /**
     * @param NSMapper $nsMapper
     * @return self
     */
    public function setNsMapper($nsMapper)
    {
        $this->nsMapper = $nsMapper;
        return $this;
    }


    /**
     * @return RoleMapper
     */
    public function getRoleMapper()
    {
        return $this->roleMapper ?: RoleMapper::getInstance(); // TODO remove dingleton
    }


    /**
     * @param RoleMapper $roleMapper
     * @return self
     */
    public function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
        return $this;
    }


    /**
     * @return SourceMapper
     */
    public function getSourceMapper()
    {
        return $this->sourceMapper ?: SourceMapper::getInstance(); // TODO remove dingleton
    }


    /**
     * @param SourceMapper $sourceMapper
     * @return self
     */
    public function setSourceMapper($sourceMapper)
    {
        $this->sourceMapper = $sourceMapper;
        return $this;
    }


    /**
     * @return RoutePermissionMapper
     */
    public function getRoutePermissionMapper()
    {
        return $this->routePermissionMapper ?: RoutePermissionMapper::getInstance(); // TODO remove dingleton
    }


    /**
     * @param RoutePermissionMapper $routePermissionMapper
     * @return static
     */
    public function setRoutePermissionMapper($routePermissionMapper)
    {
        $this->routePermissionMapper = $routePermissionMapper;
        return $this;
    }


    /**
     * @return RerouteOverrideMapper
     */
    public function getRerouteOverrideMapper()
    {
        return $this->rerouteOverrideMapper ?: RerouteOverrideMapper::getInstance(); // TODO remove dingleton
    }


    /**
     * @param RerouteOverrideMapper $rerouteOverrideMapper
     * @return static
     */
    public function setRerouteOverrideMapper($rerouteOverrideMapper)
    {
        $this->rerouteOverrideMapper = $rerouteOverrideMapper;
        return $this;
    }


    /**
     * @return Config
     */
    protected function getConfig()
    {
        // lazyload
        if (empty($this->config)) {
            $this->config = new Config($this->getRoleMapper(), $this->getSourceMapper(), $this->getRoutePermissionMapper(), $this->getRerouteOverrideMapper());
        }

        return $this->config;
    }


    /**
     * Shows the message configuration form
     *
     * @api   GET /adminv2/messages/configure{/roletype}
     * @level 150
     *
     * @param string $roletype
     */
    public function configure($roletype = null)
    {

        $roletypes = ['all', 'student', 'staff', 'parent'];

        $filters = [
            'options'  => $roletypes,
            'selected' => array_search($roletype, $roletypes, true) ?: 0
        ];

        $r = $this->getRoleMapper();

        $rolec = $roletype ? $r->findByColumn('group_type', $roletype) : $r->findAll();
        $roles = $rolec->indexBy('id')
                       ->getAll();

        $ns = $this->getNsMapper()
                   ->findAll()
                   ->indexBy('id')
                   ->getAll();

        $sources = SourceMapper::getInstance()
                               ->findAll()
                               ->indexBy('id')
                               ->getAll();

        $config = $this->getConfig()->find($rolec->getColumnValues('id'));

        $this->getResponse()->content->set([
            'title'    => 'Global Message Configuration',
            'template' => '@adminMessages/configure.twig',
            'data'     => [
                'filters' => $filters,
                'roles'   => $roles,
                'ns'      => $ns,
                'sources' => $sources,
                'perms'   => $this->getConfig()->getPermissionList(),
                'config'  => $config,
            ]
        ]);
    }



    /**
     * @api   GET /adminv2/messages/configure/custom
     * @level 150
     */
    public function customConfig()
    {
        $q = $this->getRequest()->query;

        $config = $this->getConfig()->find($q['role'], $q['source'], $q['slug']);

        if (count($config)) {
            $ns     = reset($config);
            $source = reset($ns['sources']);
            $role   = $source['roles'][$q['role']];

        } else {
            // no config set (not persisted?) mimic defaults
            $role = [
                'mask'    => [ 'DI', 'IN', 'MO', 'EM' ],
                'default' => [ 'IN' ]
            ];
        }

        // combine dry ingredients in a large bowl and mix thoroughly
        $role = array_merge($this->getRoleMapper()->dehydrate($this->getRoleMapper()->findById($q['role'])), $role, [ 'enabled' => true ]);

        /** @var Source $source */
        $source = $this->getSourceMapper()->findById($source['id']);

        /** @var NS $ns */
        $ns = $this->getNsMapper()->findById($source->getNamespace()->getId());

        $this->getResponse()->content->set([
            'template' => '@adminMessages/custom-config.twig',
            'data' => [
                'ns'        => [ $ns->getId() => $ns ],
                'namespace' => [ 'id' => $ns->getId() ],
                'sources'   => [ $source->getId() => $source ],
                'source'    => $source,
                'role'      => $role,
                'perms'     => $this->getConfig()->getPermissionList(),
                'roletype'  => $q['roletype'] ?: ''
            ],
            'format' => 'nochrome'
        ]);
    }


    /**
     * Note: roletype is actually ignored...
     *
     * @api   POST /adminv2/messages/configure{/roletype}
     * @level 155
     */
    public function saveConfig()
    {
        $post = $this->getRequest()->post;

        $postData = $post['data'];
        $copyTo   = isset($post['copy-to']) ? $post['copy-to'] : null;

        // $mode = 'simple';
        $conf = [];

        foreach ($postData as $slug => $sources) {
            foreach ($sources as $key => $roles) {

                // if this is a custom job being copied to all, replicate the relevant roles at this point
                if (count($roles) == 1 && $copyTo) {

                    // procure the settings
                    $settings = reset($roles);

                    // figure out what we're going to try to copy to
                    $list = $copyTo == 'all'
                        ? $this->getRoleMapper()->findAll()
                        : $this->getRoleMapper()->findByColumn('group_type', $copyTo);

                    // we need a list of relevant role IDs
                    foreach ($list->getColumnValues('id') as $roleId) {
                        try {
                            $conf[$slug][$key][$roleId] = $this->getConfig()->persistRole($roleId, $slug, $key, $settings);
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            // this roletype does not support this message
                        }
                    }

                } else {
                    foreach ($roles as $roleId => $settings) {
                        $conf[$slug][$key][$roleId] = $this->getConfig()->persistRole($roleId, $slug, $key, $settings);
                    }
                }
            }
        }

        $this->getResponse()->content->set([
            'data'   => $conf,
            'format' => 'json'
        ]);
    }


}