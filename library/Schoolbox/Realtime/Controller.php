<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime;

use Alaress\Web\Controller\Base as BaseController;
use InvalidArgumentException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\Resource\Folder;
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

    /** @var UserAccessMapper */
    protected $userAccessMapper;

    /** @var Folder\Mapper */
    protected $folderMapper;

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
        return $this->rerouteOverrideMapper ?: RerouteOverrideMapper::getInstance(); // TODO remove dingleton;
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
     * @return Folder\Mapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper ?: Folder\Mapper::getInstance(); // TODO remove dingleton
    }


    /**
     * @param Folder\Mapper $folderMapper
     * @return static
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }


    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper()
    {
        return $this->userAccessMapper ?: UserAccessMapper::getInstance(); // TODO remove dingleton
    }


    /**
     * @param UserAccessMapper $userAccessMapper
     * @return static
     */
    public function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
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
     * Does this user have
     *
     * @return array
     */
    protected function calculateFollowment()
    {
        // as much as we'd like get this as cached in session, we can't include subjects here
        $accesses  = $this->getUserAccessMapper()->findAllGroupsForUser($this->getUser());

        $folderIds = $accesses->getColumnValues('serviceIdentifier');

        /** @var \Schoolbox\Collection|Folder\Model[] $folders */
        $folders   = $this->getFolderMapper()->findByColumn('id', $folderIds)->sortBy('name', 'asc');
        $service   = UserAccess::SERVICE_HOMEPAGE;

        $muted = array(
            'unfollow' => $this->getUser()->getId(),
            'disabled' => 0
        );

        $routeKeyRegex = "/service\\.$service\\.([0-9]+)/";
        foreach ($muted as $k => $id) {
            $muted[$k] = $this->getRerouteOverrideMapper()
                ->getMatchingRoutes("service.$service", RoutePermission::MUTE, $id)
                ->map(
                    function ($override) use ($routeKeyRegex) {
                        /** @var RerouteOverride $override */
                        preg_match($routeKeyRegex, $override->getRouteKey(), $matches);
                        return is_array($matches) ? $matches[1] : null;
                    },
                    false
                )
                ->getAll();
        }

        $followment = [];

        // following
        foreach ($folders as $folder) {

            $forced = false;

            // forced hydration
            $folder = $this->getFolderMapper()
                           ->findById($folder->getId());

            if ($grp = $folder->getGroup()) {
                $forced = $grp->getForceFollow() || in_array($folder->getId(), $muted['disabled']);
            }

            $follow = !(in_array($folder->getId(), $muted['unfollow']) || in_array($folder->getId(), $muted['disabled']));


            $followment[$folder->getId()] = [
                'name'   => $folder->getName(),
                'follow' => $follow,
                'force'  => $forced,
            ];

        }

        return $followment;
    }


    /**
     * Shows the message configuration form
     *
     * @api   GET /settings/messages
     * @since 150
     */
    public function configure()
    {
        $c = new Cronterpreter($this->getConfigLoader()->get('date_time'));

        // TODO lookup on mobile registry table
        $mobile = true;
        $digest = false;

        $schedule = $this->getUser()->getRole()->getDigestSchedule();

        if ($schedule) {
            try {
                $digest = $c->interpret($schedule);
            } catch (InvalidArgumentException $e) {
                // noop
            }
        }

        /** @var NS[] $ns */
        $ns = $this->getNsMapper()
                   ->findAll()
                   ->indexBy('id')
                   ->getAll();

        //Filtering out disabled modules
        //   Modules            => Related Notifications
        $checkEnabled = Array(
            'eotc'              => [NSMapper::NS_EOTC],
            'resource_booking'  => [NSMapper::NS_RESOURCE_BOOKING],
            'pastoral'          => [NSMapper::NS_PASTORAL, NSMapper::NS_PASTORAL_LOG, NSMapper::NS_PASTORAL_COMMENT],
            'fixtures'          => [NSMapper::NS_SPORTS_FIXTURES, NSMapper::NS_SPORTS_FIXTURES_IMPORT]
        );

        $config = $this->getConfigLoader();

        foreach ($ns as $i=>$namespace) {
            foreach ($checkEnabled as $notifType=>$notifs) {
                if(in_array($namespace->getSlug(), $notifs)){
                    if(0 == $config->get($notifType.'_enabled')){
                        unset($ns[$i]);
                    }
                }
            }
        }

        $sources = SourceMapper::getInstance()
                               ->findAll()
                               ->indexBy('id')
                               ->getAll();

        $this->getResponse()->content->set([
            'title'    => 'Message Settings',
            'template' => '@messages/settings.twig',
            'data'     => [
                'ns'        => $ns,
                'sources'   => $sources,
                'perms'     => $this->getConfig()->getPermissionList($digest, $mobile),
                'digest'    => $digest,
                'mobile'    => $mobile,
                'groups'    => $this->calculateFollowment(),
                'config'    => $this->getRoutePermissionMapper()->findAllRoutePermissionsForUser($this->getUser()),
            ]
        ]);
    }


    /**
     * @api   POST /settings/messages
     * @since 150
     */
    public function saveConfig()
    {
        $post = $this->getRequest()->post;

        $postData   = isset($post['data']) ? $post['data'] : [];
        $postFollow = isset($post['follow']) ? $post['follow'] : [];

        $conf = [];

        foreach ($postData as $slug => $sources) {
            foreach ($sources as $key => $sourceIds) {
                foreach ($sourceIds as $sourceId => $settings) {
                    // yes, we have access to the source ID. however passing in the other vars allows us validation
                    $conf[$slug][$key][$sourceId] = $this->getConfig()->persistUser($this->getUser(), $slug, $key, $settings);
                }
            }
        }

        // update following if it was sent
        if (count($postFollow)) {
            $conf['follow'] = $this->getConfig()->persistFollow($this->getUser(), $postFollow);
        }

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set([
                'data'   => $conf,
                'format' => 'json'
            ]);
        } else {
            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('messages.configure'));
        }
    }

}
