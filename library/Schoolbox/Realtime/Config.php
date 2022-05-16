<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime;

use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\Reroute;
use Schoolbox\Realtime\Message\RerouteMapper;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Message\Source;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use UnderflowException;

class Config
{

    /** @var RoleMapper */
    protected $roleMapper;

    /** @var SourceMapper */
    protected $sourceMapper;

    /** @var RoutePermissionMapper */
    protected $routePermissionMapper;

    /** @var RerouteOverrideMapper */
    protected $rerouteOverrideMapper;


    /**
     * @param RoleMapper            $roleMapper
     * @param SourceMapper          $sourceMapper
     * @param RoutePermissionMapper $permMapper ,
     * @param RerouteOverrideMapper $rerouteOverrideMapper
     */
    public function __construct(
        RoleMapper $roleMapper,
        SourceMapper $sourceMapper,
        RoutePermissionMapper $permMapper,
        RerouteOverrideMapper $rerouteOverrideMapper
    ) {
        $this->setRoleMapper($roleMapper)
             ->setSourceMapper($sourceMapper)
             ->setRoutePermissionMapper($permMapper)
             ->setRerouteOverrideMapper($rerouteOverrideMapper);
    }


    /**
     * @return RoleMapper
     */
    public function getRoleMapper()
    {
        return $this->roleMapper;
    }


    /**
     * @param  RoleMapper $roleMapper
     * @return static
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
        return $this->sourceMapper;
    }


    /**
     * @param  SourceMapper $sourceMapper
     * @return static
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
        return $this->routePermissionMapper;
    }


    /**
     * @param  RoutePermissionMapper $routePermissionMapper
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
        return $this->rerouteOverrideMapper;
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
     * @param  bool $withDigest
     * @param  bool $withMobile
     * @return array
     */
    public function getPermissionList($withDigest = true, $withMobile = true)
    {
        // the order here is intentional and important
        $set = [
            0 => ['&times;', 'Muted'],
            8 => ['D', 'Digest'],
            1 => ['I', 'Instant'],
            2 => ['M', 'Mobile'],
            4 => ['E', 'Email'],
        ];

        if (!$withDigest) {
            unset($set[8]);
        }
        if (!$withMobile) {
            unset($set[2]);
        }

        return $set;
    }


    /**
     * @param  int|array $roleIds
     * @param  string    $sourceKey
     * @param  string    $slug
     * @return array
     */
    public function find($roleIds, $sourceKey = null, $slug = null)
    {
        if (!count((array)$roleIds)) {
            return [];
        }
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
            SELECT n.id namespace_id, s.id source_id, s.description source_description,
              r.id role_id, COALESCE(rp.mask, 'MU') mask, COALESCE(rp.role_default, 'MU') def
            FROM msg_namespace n
            LEFT JOIN msg_source s ON n.id = s.namespace
            LEFT JOIN `groups` r ON r.group_type = s.role_type
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
                'mask'    => explode(',', $mask),
                'default' => explode(',', $default)
            ];
        }

        return $config;
    }


    /**
     * @param  int    $roleId
     * @param  string $slug
     * @param  string $key
     * @param  array  $settings
     * @throws \Schoolbox\Mapper\Exception
     * @return array
     */
    public function persistRole($roleId, $slug, $key, array $settings)
    {
        $role = $this->getRoleMapper()->findById($roleId);

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
        } catch (Mapper\Exception $e) {
            /** @var RoutePermission $routePermission */
            $routePermission = $this->getRoutePermissionMapper()->createModel();
            $routePermission->setRole($role);
            $routePermission->setMsgSource($src);
        }

        if (isset($settings['mask'])) {
            // change mask
            $mask    = $settings['mask'];
            $default = $settings['perm'];
        } elseif (isset($settings['perm'])) {
            // grab existing mask if exists, or turn everything on
            $existing = $routePermission->getMask();
            $mask     = $existing ? $this->removeMap($existing) : [ 0, 1, 2, 4, 8 ];
            $default  = $settings['perm'];
        } else {
            // turn off
            $mask    = [ 0 ];
            $default = [ 0 ];
        }

        $routePermission->setMask($this->applyMap($mask));
        $routePermission->setDefault($this->applyMap($default));

        $this->getRoutePermissionMapper()->save($routePermission);

        return $this->getSum($mask, $default);
    }


    /**
     * @param  User   $user
     * @param  string $slug
     * @param  string $key
     * @param  array  $settings
     * @throws \Schoolbox\Mapper\Exception if the source is not relevant to the given user
     * @return int
     */
    public function persistUser($user, $slug, $key, array $settings)
    {
        /** @var Source $src */
        $src = $this->getSourceMapper()->findOneByColumnList([
            'slug'      => $slug,
            'key'       => $key,
            'role_type' => $user->getRole()->getType()
        ]);

        /** @var Reroute $rr */
        $rr = RerouteMapper::getInstance()->createModel();

        $rr->setSource($src);
        $rr->setUser($user);
        $rr->setDelivery($this->applyMap($settings['perm']));

        RerouteMapper::getInstance()->save($rr);

        return array_sum($settings['perm']);
    }


    /**
     * @param User  $user
     * @param array $follow
     * @return array
     */
    public function persistFollow(User $user, $follow)
    {
        $involvements = $user->getAccessList()->getColumnValues('serviceIdentifier');

        // mute anything explicitly passed as 'off' which ALSO appears in the involvement list
        $unfollow = [];
        foreach ($follow as $k => $v) {
            if (in_array($k, $involvements) && $v == 0) {
                $unfollow[] = $k;
            }
        }

        asort($unfollow);
        asort($involvements);

        // anything not explicitly muted is turned on
        $follow = array_diff($involvements, $unfollow);
        asort($follow);

        // delete any overrides for homepages the user is RE-following
        RerouteOverrideMapper::getInstance()->deleteByColumnList(array(
            'user_id'        => $user->getId(),
            'msg_route_key'  => array_map([ $this, 'castToRoutingKey' ], $follow),
            'delivery'       => RoutePermission::MUTE
        ));

        // insert new entries for homepages the user is UN-following
        $overrides = new Collection();

        foreach ($unfollow as $id) {
            /** @var RerouteOverride $override */
            $override = $this->getRerouteOverrideMapper()->createModel();

            $override->setUser($user);
            $override->setRouteKey($this->castToRoutingKey($id));
            $override->setDelivery([ RoutePermission::MUTE ]);

            $overrides->add($override);
        }

        RerouteOverrideMapper::getInstance()->save($overrides);

        return $follow;
    }


    /**
     * Wrap an int into a routing-key string
     *
     * @param int $v
     * @return string
     */
    protected function castToRoutingKey($v)
    {
        return 'service.' . UserAccess::SERVICE_HOMEPAGE . ".$v";
    }


    /**
     * @param  array $mask
     * @param  array $default
     * @return array|bool
     */
    protected function getSum($mask, $default)
    {
        // non-array values means off
        if (!is_array($mask) || !is_array($default)) {
            return false;
        }

        // zeros also means off
        if ($mask === [ 0 ] && $default === [ 0 ]) {
            return false;
        }

        // return a summation
        return [
            'mask'    => [ in_array(0, $mask), array_sum($mask) ],
            'default' => array_sum($default)
        ];
    }


    /**
     * @param  array $array
     * @return array
     */
    public function applyMap($array)
    {
        $map = $this->getPermissionList();

        $result = [];

        foreach ((array)$array as $i) {
            $result[] = strtoupper(substr($map[$i][1], 0, 2));
        }

        return $result;
    }


    /**
     * @param  array $array
     * @return array
     */
    public function removeMap($array)
    {
        $result = [];
        $map = [];

        array_walk($this->getPermissionList(), function ($v, $k) use (&$map) {
            $map[strtoupper(substr($v[1], 0, 2))] = $k;
        });

        foreach ((array)$array as $i) {
            $result[] = $map[$i];
        }

        return $result;
    }
}
