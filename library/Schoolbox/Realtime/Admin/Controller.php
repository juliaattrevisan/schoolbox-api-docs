<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Admin;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Lumberjack\Logger;
use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Collection;
use Schoolbox\MobileApp\Service;
use Schoolbox\Realtime\Config;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Message\Source;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\Realtime\Notification\Parameter\SimpleParam;
use Schoolbox\Resource;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\RoleMapper;
use Schoolbox\MobileApp;
use Schoolbox\UserMapper;
use Schoolbox\Realtime\Subscription\Redis\UnreadRetriever;

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
     * Constructor
     *
     * @param Resource\Storage\FilesystemProxy $filesystemProxy
     */
    public function __construct(private Resource\Storage\FilesystemProxy $filesystemProxy)
    {
    }

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
     * Show list of apps.
     *
     * @api GET /adminv2/notifications/apps
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getApps()
    {
        $apps = MobileApp\ServiceMapper::getInstance()->findAll();

        $zones = [];

        /** @var Collection|Service[] $apples */
        $apples = $apps->filterBy('providerName', MobileApp\Registry::PROVIDER_APPLE);

        foreach ($apples as $app) {
            $appId         = $app->getId();
            $zones[$appId] = (new Zone($appId))->getTwigContext();
        }

        $data = [
            'pageCurrent'    => $this->routeGenerator->generate('adminNotifications.getApps'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminNotifications.getApps')),
            'apps'           => $apps,
            'attachzones'    => $zones,
            'providers'      => [
                MobileApp\Registry::PROVIDER_APPLE     => [
                    'template'    => '@adminNotifications/providers/APNS/entity.twig',
                    'description' => 'Apple Push Notification Service'
                ],
                MobileApp\Registry::PROVIDER_GOOGLE    => [
                    'template'    => '@adminNotifications/providers/GCM/entity.twig',
                    'description' => 'Google Cloud Messaging',
                ],
                MobileApp\Registry::PROVIDER_DIGISTORM => [
                    'template'    => '@adminNotifications/providers/Digistorm/entity.twig',
                    'description' => 'Digistorm',
                ],
                MobileApp\Registry::PROVIDER_TASS_ORBIT => [
                    'template'    => '@adminNotifications/providers/TASS/entity.twig',
                    'description' => 'TASS Orbit',
                ],
            ],
        ];

        $this->response = $this->responseFromTemplate('@adminNotifications/apps.twig', $data, 'Admin > Notifications > Apps');
    }


    /**
     * Get the create app form.
     *
     * @api GET /adminv2/notifications/apps/new
     */
    public function getCreateAppForm()
    {
        $provider = $this->requestQuery->get('provider');
        $data     = ['provider' => $provider];
        switch ($provider) {
            case MobileApp\Registry::PROVIDER_APPLE:
                $template           = '@adminNotifications/providers/APNS/create.twig';
                $data['attachzone'] = (new Zone('apns'))->getTwigContext();
                break;

            case MobileApp\Registry::PROVIDER_GOOGLE:
                $template = '@adminNotifications/providers/GCM/create.twig';
                break;

            case MobileApp\Registry::PROVIDER_DIGISTORM:
                $template = '@adminNotifications/providers/Digistorm/create.twig';
                break;

            case MobileApp\Registry::PROVIDER_FIREBASE:
                $template = '@adminNotifications/providers/Firebase/create.twig';
                break;

            case MobileApp\Registry::PROVIDER_TASS_ORBIT:
                $template = '@adminNotifications/providers/TASS/create.twig';
                break;

            default:
                $this->sendError('Service does not exist');
                return;
        }

        $this->response = $this->responseFromTemplateUnthemed($template, $data);
    }


    /**
     * Create or modify an app.
     *
     * @api POST /adminv2/notifications/apps
     *
     * @throws \Alaress\Web\Controller\Exception
     */
    public function postModifyApp()
    {
        $post                = $this->requestPost;
        $provider            = $post->get('provider');
        $appId               = trim($post->get('app') ?? "");
        $key                 = trim($post->get('key') ?? "");
        $publicConfiguration = null;
        $webPushPublicKey    = null;
        $certificate         = null;

        switch ($provider) {
            case MobileApp\Registry::PROVIDER_APPLE:
                // require a certificate for APNS
                try {
                    $certificate = (new CollectorFactory())->build($post->get('attachment', []))->getFirst();
                } catch (Resource\Exception $e) {
                    $this->sendError('A certificate is required to register a new Apple push notification service.');
                    return;
                }
                break;

            case MobileApp\Registry::PROVIDER_GOOGLE:
                // require key for GCM
                if (empty($key)) {
                    $this->sendError("Unable to setup Google service, please provide a key");
                    return;
                }
                break;

            case MobileApp\Registry::PROVIDER_DIGISTORM:
                // require key for Digistorm
                if (empty($key)) {
                    $this->sendError("Unable to setup Digistorm service, please provide a key");
                    return;
                }
                break;

            case MobileApp\Registry::PROVIDER_FIREBASE:
                // require keys for Firebase
                $publicConfiguration = trim($post->get('publicConfiguration', ''));
                $webPushPublicKey    = trim($post->get('webPushPublicKey', ''));
                if (empty($key) || empty($publicConfiguration) || empty($webPushPublicKey)) {
                    $this->sendError("Unable to setup Firebase service, please provide a private key");
                    return;
                }

                break;
            case MobileApp\Registry::PROVIDER_TASS_ORBIT:
                // require keys for TASS Orbit
                $error = "Unable to setup TASS Orbit service,";
                $host = trim($post->get('host', ''));
                $companycode    = trim($post->get('companycode', ''));

                if (empty($key) || empty($companycode) || empty($host)) {
                    $this->sendError("$error please provide all configurations.");
                    return;
                }

                $key = json_encode(
                    [
                        "token" => $key,
                        "host" => $host,
                        "companycode" => $companycode
                    ]
                );

                break;
            default:
                $this->sendError('Please select a valid service provider.');
                return;
        }

        if (empty($appId)) {
            $this->sendError('Unable to setup service, please provide an app ID');
            return;
        }

        $serviceMapper = MobileApp\ServiceMapper::getInstance();

        // Find the existing app, if one was passed
        $id = trim($post->get('id', ''));
        if ($id) {
            //check id, if exists use that record (update), else find if it was deleted, or create new
            try {
                $serviceModel = $serviceMapper->findById($id);
            } catch (\Schoolbox\Mapper\Exception $e) {
                /* You tried to modify a thing that doesn't exist anymore
                 */
                $this->sendError("This App does not exist.");
                return;
            }
        } else {
            /**
             * Check for previously deleted object with the same app_identifier, this is supposed to be unique
             * if it is exists, throw error. as the DB will throw its own errors if there are dupes
             */
            try {
                $serviceMapper->findOneByColumn('app_identifier', $appId);
                //search including deleted, and other providers, the app_identifier is a unique column in the db.

                $this->sendError("This App Identifier already exists. Please use a unique 'App Identifier'");
                return;
            } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                /** @var Service $serviceModel */
                $serviceModel = $e->getDeletedObject();
                $serviceModel->setDeletedAt(null);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $serviceModel = $serviceMapper->createModel();
            }
        }
        $serviceMapper->save(
            $serviceModel
                ->setAppIdentifier($appId)
                ->setProviderName($provider)
                ->setKey($key)
                ->setPublicConfiguration($publicConfiguration)
                ->setWebPushPublicKey($webPushPublicKey)
                ->setCertificate($certificate)
        );

        if ($this->isXhrRequest()) {
            // respond with an empty 200 OK
            $this->response = $this->responseAsJson(null);
            return;
        }

        $this->response = $this->redirectAfterPost($this->getRouteIfAuthorized('adminNotifications.getApps'));
    }


    /**
     * Shows the message configuration form
     *
     * @api   GET /adminv2/notifications/configure{/roletype}
     * @since 150
     *
     * @param string $roletype
     */
    public function configure($roletype = null)
    {
        $roletypes = ['all', 'student', 'staff', 'parent'];

        $filters = [
            'options'  => $roletypes,
            'selected' => array_search($roletype, $roletypes, true) ?: 0,
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
        $checkEnabled = [
            'eotc'             => [NSMapper::NS_EOTC],
            'resource_booking' => [NSMapper::NS_RESOURCE_BOOKING],
            'pastoral'         => [NSMapper::NS_PASTORAL, NSMapper::NS_PASTORAL_LOG, NSMapper::NS_PASTORAL_COMMENT],
            'fixtures'         => [NSMapper::NS_SPORTS_FIXTURES, NSMapper::NS_SPORTS_FIXTURES_IMPORT],
            'lms'              => [NSMapper::NS_DUEWORK, NSMapper::NS_ONLINE_ASSESS, NSMapper::NS_REPORT_CARD],
            'badges'           => [NSMapper::NS_BADGE],
            'eportfolio'       => [NSMapper::NS_EPORTFOLIO_BLOG],
            'learning_moments' => [NSMapper::NS_EVIDENCE, NSMapper::NS_EVIDENCE_COMMENT]
        ];

        $config = $this->getConfigLoader();

        foreach ($ns as $i => $namespace) {
            foreach ($checkEnabled as $notifType => $notifs) {
                if (
                    in_array($namespace->getSlug(), $notifs, true)
                    && !$config->get($notifType . '_enabled')
                ) {
                    unset($ns[$i]);
                }
            }
        }

        $sources = SourceMapper::getInstance()
                               ->findAll()
                               ->indexBy('id')
                               ->getAll();

        //Filtering out $config display further in Twig. Is there a more elegant solution?

        $this->response = $this->responseFromTemplate(
            '@adminNotifications/configure.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminNotifications.configure'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminNotifications.configure')),
                'filters' => $filters,
                'roles'   => $roles,
                'ns'      => $ns,
                'sources' => $sources,
                'perms'   => $this->getConfig()->getPermissionList(),
                'msgConfig'  => $this->getConfig()->find($rolec->getPropertyValues('id')),
            ],
            'Global Message Configuration',
        );
    }

    /**
     * @api   GET /adminv2/notifications/configure/custom
     * @since 150
     */
    public function customConfig()
    {
        $q = $this->requestQuery;

        $config = $this->getConfig()->find($q['role'], $q['source'], $q['slug']);

        if (count($config)) {
            $ns     = reset($config);
            $source = reset($ns['sources']);
            $role   = $source['roles'][$q['role']];
        } else {
            // no config set (not persisted?) mimic defaults
            $role = [
                'mask'    => ['DI', 'IN', 'MO', 'EM'],
                'default' => ['IN'],
            ];
        }

        // combine dry ingredients in a large bowl and mix thoroughly
        $role = array_merge($this->getRoleMapper()->dehydrate($this->getRoleMapper()->findById($q['role'])), $role, ['enabled' => true]);

        /** @var Source $source */
        $source = $this->getSourceMapper()->findById($source['id']);

        /** @var NS $ns */
        $ns = $this->getNsMapper()->findById($source->getNamespace()->getId());

        $this->response = $this->responseFromTemplateUnthemed(
            '@adminNotifications/custom-config.twig',
            [
                'ns'        => [$ns->getId() => $ns],
                'namespace' => ['id' => $ns->getId()],
                'sources'   => [$source->getId() => $source],
                'source'    => $source,
                'role'      => $role,
                'perms'     => $this->getConfig()->getPermissionList(),
                'roletype'  => $q['roletype'] ?? '',
            ],
        );
    }


    /**
     * Note: roletype is actually ignored...
     *
     * @api   POST /adminv2/notifications/configure{/roletype}
     * @since 155
     */
    public function saveConfig()
    {
        $post = $this->requestPost;

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
                    foreach ($list->getPropertyValues('id') as $roleId) {
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

        $this->response = $this->responseAsJson($conf);
    }


    /**
     * @param int $userId
     */
    public function testPush($userId = null)
    {
        $liveMessage = $this->requestQuery->getBool('live', false);
        $write = fopen('php://memory', 'rb+');

        $log = new Logger($write, $write, \Monolog\Logger::DEBUG, [], true);

        $cloudManager = new MobileApp\ServiceManager(
            $log,
            MobileApp\RegistryMapper::getInstance(),
            $this->getDi()->get('instance'),
            $this->getConfigLoader()->get('title'),
            $this->filesystemProxy,
            new UnreadRetriever($this->getDi()->get('redis.storage')),
            $liveMessage
                ? MobileApp\ServiceManager::SERVICE_TYPE_PRODUCTION
                : MobileApp\ServiceManager::SERVICE_TYPE_TEST,
            //Getting length of par_code
            $this->getConfigLoader(),
        );

        $content = [
            'template' => '@adminNotifications/puush.twig',
            'data'     => [
                'liveMessage'    => $liveMessage,
                'pageCurrent'    => $this->routeGenerator->generate('adminNotifications.testPush'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))
                    ->generateBreadcrumbs($this->routeGenerator->generate('adminNotifications.testPush')),
            ],
        ];

        if ($userId) {
            try {
                $user = UserMapper::getInstance()->findById($userId);

                $content['data']['user'] = $user;

                // Build a fake message
                $builder  = new Builder(false);
                $instance = $builder
                    ->setTitle('Test message from :instance')
                    ->setParam('instance', new SimpleParam($this->getConfigLoader()->get('title')))
                    ->build();
                $cloudManager->push($user->getId(), $instance);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $log->warning("Failed to retrieve user $userId: {$e->getMessage()}", ['exception' => $e]);
            }

            $log->warning('-- END --');
            $content['data']['registry'] = [];
        } else {
            $content['data']['registry'] = MobileApp\RegistryMapper::getInstance()->findAll(['order' => ['user_id' => 'asc']]);
        }

        rewind($write);

        $content['data']['logs'] = array_filter(explode("\n", trim(stream_get_contents($write))));

        $this->response = $this->responseFromTemplate(
            $content['template'],
            $content['data'],
        );
    }

    /**
     * Deletes notification provider, and user registrations for that provider.
     *
     * @api POST /adminv2/notifications/apps/delete
     *
     * @throws \Alaress\Web\Controller\Exception
     */
    public function postDeleteApp()
    {
        //get the ID to remove
        $id = trim($this->requestPost->get('id', null));

        //lets confirm there is actually an id...
        if ($id === "") {
            $this->sendError('Provider id not provided');
            return;
        }

        //steve: were you going to show me dependency injection?
        $serviceMapper = MobileApp\ServiceMapper::getInstance();
        $registryMapper = MobileApp\RegistryMapper::getInstance();

        try {
            $service = $serviceMapper->findById($id);
        } catch (\Schoolbox\Mapper\Exception $e) {
            //maybe this has already been deleted, or other error, just return to notification provider screen
            $this->sendError('Provider not found, have they already been deleted?');
            return;
        }

        //set deleted id in service / mobile_app table
        $serviceMapper->delete($service);

        //set deleted in app registries
        $registryMapper->deleteByColumn('app_id', $service->getId());

        $this->response = $this->responseAsJson([
            'id' => $service->getId(),
        ]);
    }
}
