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
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Message\RoutePermission;
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
     * @return array
     */
    protected function getPermissionList()
    {
        // the order here is intentional and important
        return [
            0 => ['&times;', 'Muted'],
            8 => ['D', 'Digest'],
            1 => ['I', 'Instant'],
            2 => ['M', 'Mobile'],
            4 => ['E', 'Email'],
        ];
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

        $config = $this->findConfig($rolec->getColumnValues('id'));

        $this->getResponse()->content->set([
            'title'    => 'Global Message Configuration',
            'template' => '@adminMessages/configure.twig',
            'data'     => [
                'filters' => $filters,
                'roles'   => $roles,
                'ns'      => $ns,
                'sources' => $sources,
                'perms'   => $this->getPermissionList(),
                'config'  => $config,
            ]
        ]);
    }


    /**
     * @param int|array $roleIds
     * @param string    $sourceKey
     * @param string    $slug
     * @return array
     */
    protected function findConfig($roleIds, $sourceKey = null, $slug = null)
    {
        if (is_array($roleIds)) {
            $roleIds = implode(',', $roleIds);
        }

        if ($sourceKey && $slug) {
            $params = [ $sourceKey, $slug ];
            $sourceKey = "AND s.key = ? AND n.slug = ?";
        } else {
            $params = null;
        }

        // TODO move this to a mapper
        $stmt = \Database::getInstance()->query("
            SELECT n.id namespace_id, s.id source_id, s.description source_description, r.id role_id, rp.MASK mask, rp.DEF def
            FROM msg_namespace n
            LEFT JOIN msg_source s ON n.id = s.namespace
            LEFT JOIN groups r ON r.group_type = s.role_type
            LEFT JOIN msg_route_perm rp ON rp.msg_source_id = s.id AND rp.role_id = r.id

            WHERE r.id IN ($roleIds)
            $sourceKey

            ORDER BY namespace_id, source_id, role_id
        ", $params);

        $config = [];

        while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {

            list($namespaceId, $sourceId, $sDescription, $roleId, $mask, $default) = $r;

            $config[$namespaceId]['id']                           = $namespaceId;
            $config[$namespaceId]['sources'][$sDescription]['id'] = $sourceId;

            $config[$namespaceId]['sources'][$sDescription]['roles'][$roleId] = [
                'id'      => $roleId,
                'enabled' => $mask != null,
                'mask'    => explode(',', $mask),
                'default' => explode(',', $default)
            ];

        }

        return $config;
    }


    /**
     * @api   GET /adminv2/messages/configure/custom
     * @level 150
     */
    public function customConfig()
    {
        $q = $this->getRequest()->query;

        $config = $this->findConfig($q['role'], $q['source'], $q['slug']);

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
                'perms'     => $this->getPermissionList(),
                'roletype'  => $q['roletype'] ?: ''
            ],
            'format' => 'nochrome'
        ]);
    }


    /**
     * @api   POST /adminv2/messages/configure
     * @level 150
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
                            $conf[$slug][$key][$roleId] = $this->persist($roleId, $slug, $key, $settings);
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            // this roletype does not support this message
                        }
                    }

                } else {
                    foreach ($roles as $roleId => $settings) {
                        $conf[$slug][$key][$roleId] = $this->persist($roleId, $slug, $key, $settings);
                    }
                }

            }
        }

        $this->getResponse()->content->set([
            'data'   => $conf,
            'format' => 'json'
        ]);
    }


    /**
     * @param int    $roleId
     * @param string $slug
     * @param string $key
     * @param array  $settings
     * @throws \Schoolbox\Mapper\Exception
     * @return array
     */
    protected function persist($roleId, $slug, $key, array $settings)
    {
        $role = $this->getRoleMapper()->findById($roleId);

        if (isset($settings['mask'])) {
            // $mode = 'advanced';

            // do advanced (ironically the easiest to get)
            $mask    = $settings['mask'];
            $default = $settings['perm'];

        } elseif ($settings['enabled']) {
            // do simple
            $mask = [ 1, 2, 4, 8 ];

            if ($settings['mutable']) {
                array_unshift($mask, 0);
            }

            $default = $settings['perm'];

        } else {
            // do disabled
            $mask    = [ 0 ];
            $default = [ 0 ];
        }

        // acquire msg source, or throw exception if this message isn't relevant to this roletype
        $src = $this->getSourceMapper()->findOneByColumnList([
            'slug'      => $slug,
            'key'       => $key,
            'role_type' => $role->getType()
        ]);

        try {
            /** @var RoutePermission $routePermission */
            $routePermission = $this->getRoutePermissionMapper()->findOneByColumnList([
                'msg_source_id' => $src->getId(),
                'role_id'       => $roleId
            ]);

        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var RoutePermission $routePermission */
            $routePermission = $this->getRoutePermissionMapper()->createModel();
            $routePermission->setRole($role);
            $routePermission->setMsgSource($src);
        }


        $routePermission->setMask($this->applyMap($mask));
        $routePermission->setDefault($this->applyMap($default));

        $this->getRoutePermissionMapper()->save($routePermission);

        return $this->getSum($mask, $default);
    }

    /**
     * @param array $mask
     * @param array $default
     * @return array
     */
    protected function getSum($mask, $default)
    {
        if ($mask === [ 0 ] && $default === [ 0 ]) {
            return false;
        }

        return [
            'custom'  => array_sum($mask) != 15,
            'mutable' => in_array('MU', $mask),
            'default' => array_sum($default)
        ];
    }

    /**
     * @param array $array
     * @return array
     */
    protected function applyMap($array)
    {
        $map = $this->getPermissionList();

        $result = [];

        foreach ($array as $i) {
            $result[] = strtoupper(substr($map[$i][1], 0, 2));
        }

        return $result;
    }

}