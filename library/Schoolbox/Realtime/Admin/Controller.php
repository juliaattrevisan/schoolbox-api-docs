<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Admin;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Lumberjack\Logger;
use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Collection;
use Schoolbox\MobileApp\Service;
use Schoolbox\Realtime\Config;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Message\Source;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\Resource;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\RoleMapper;
use Alaress\Redis;
use Schoolbox\MobileApp;
use Schoolbox\UserMapper;

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
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getApps()
    {
        $apps = MobileApp\ServiceMapper::getInstance()->findAll();

        $zones = [];

        /** @var Collection|Service[] $apples */
        $apples = $apps->filterBy('providerName', MobileApp\Registry::PROVIDER_APPLE);

        foreach ($apples as $app) {
            $appId = $app->getId();
            $zones[$appId] = (new Zone($appId))->getTwigContext();
        }

        $data = [
            'pageCurrent' => 'messages',
            'apps'        => $apps,
            'attachzones' => $zones,
            'providers'   => [
                'apns' => 'Apple Push Notification Service',
                'gcm'  => 'Google Cloud Messaging'
            ],
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@adminMessages/apps.twig', $data, 'Admin > Messages > Apps'));
    }


    /**
     */
    public function getCreateAppForm()
    {
        $provider = strtoupper($this->getRequest()->query->get('provider'));

        $data = [];

        if ($provider === 'APNS') {
            $data['attachzone'] = (new Zone('apns'))->getTwigContext();
        }

        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput("@adminMessages/providers/{$provider}/create.twig", $data));
    }


    /**
     * @throws \Alaress\Web\Controller\Exception
     */
    public function postModifyApp()
    {
        $resolutions = [];

        $rq   = $this->getRequest();
        $post = $rq->post;

        $provider    = strtoupper($post->get('provider'));
        $appId       = trim($post->get('app'));
        $key         = trim($post->get('key'));
        $certificate = null;

        if ($provider === MobileApp\Registry::PROVIDER_GOOGLE) {
            // require key for GCM
            if (empty($key)) {
                $resolutions[] = "specify the {$provider} API key";
            }
        } elseif ($provider === MobileApp\Registry::PROVIDER_APPLE) {
            // require a certificate for APNS
            try {
                $certificate = (new CollectorFactory())->build($post->get('attachment', []))->getFirst();

                if (empty($certificate)) {
                    // yes this looks contrived but it saves on duplicating the error message
                    throw new Resource\Exception();
                }

            } catch (Resource\Exception $e) {
                // no file supplied
                $resolutions[] = "provide the {$provider} certificate";
            }
        }

        if (empty($appId)) {
            $resolutions[] = "specify the {$provider} app identifier";
        }

        if (count($resolutions)) {
            $this->sendError('Could not register app', $resolutions);
            return;
        }

        $serviceMapper = MobileApp\ServiceMapper::getInstance();

        $serviceMapper->save(
            $serviceMapper
                ->createModel()
                ->setAppIdentifier($appId)
                ->setProviderName($provider)
                ->setKey($key)
                ->setCertificate($certificate)
        );

        if ($this->getRequest()->isXhr()) {
            // respond with an empty 200 OK
            $this->getResponse()->content->set($this->buildJsonOutput(null));
            return;
        }

        $this->getResponse()->redirect->afterPost($this->getRouteIfAuthorized('adminMessages.getApps'));
    }


    /**
     * Shows the message configuration form
     *
     * @api   GET /adminv2/messages/configure{/roletype}
     * @since 150
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

        //Filtering out $config display further in Twig. Is there a more elegant solution?

        $this->getResponse()->content->set([
            'title'    => 'Global Message Configuration',
            'template' => '@adminMessages/configure.twig',
            'data'     => [
                'filters' => $filters,
                'roles'   => $roles,
                'ns'      => $ns,
                'sources' => $sources,
                'perms'   => $this->getConfig()->getPermissionList(),
                'config'  => $this->getConfig()->find($rolec->getColumnValues('id')),
            ]
        ]);
    }

    /**
     * @api   GET /adminv2/messages/configure/custom
     * @since 150
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
     * @since 155
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


    /**
     * @param int $userId
     */
    public function testPush($userId = null)
    {
        $write = fopen('php://memory', 'rb+');

        $log = new Logger($write, $write, \Monolog\Logger::DEBUG, [], true);

        $cloudManager = new MobileApp\ServiceManager(
            $log,
            MobileApp\RegistryMapper::getInstance(),
            $this->getDi()->get('instance')
        );

        // just grab... anything
        $redis = Redis::getClientInstance();
        $keys  = $redis->keys('msg*:m:*');
        $data  = $redis->get($keys[1]);

        $content = [
            'template' => '@adminMessages/puush.twig',
            'data'     => []
        ];

        if ($userId) {
            try {
                $user = UserMapper::getInstance()->findById($userId);

                $content['data']['user'] = $user;

                $cloudManager->push(
                    $user->getId(),
                    unserialize(gzuncompress($data), ['allowed_classes' => [Instance::class, Param::class,],])
                );
            } catch (\Schoolbox\Mapper\Exception $e) {
                $log->warning("Failed to retrieve user $userId: {$e->getMessage()}", [ 'exception'=>$e ]);
            }

            $log->warning('-- END --');

        } else {
            $content['data']['registry'] = MobileApp\RegistryMapper::getInstance()->findAll([ 'order' => [ 'user_id' => 'asc' ] ]);
        }

        rewind($write);

        $content['data']['logs'] = array_filter(explode("\n", trim(stream_get_contents($write))));

        $this->getResponse()->content->set($content);
    }

}
