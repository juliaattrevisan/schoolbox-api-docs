<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Application;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Connector\NullConnector;
use Alaress\Database\Exception as AlaressDatabaseException;
use Alaress\Database\Factory as AlaressDatabaseFactory;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Image\ProcessorFactory;
use Alaress\Module\Cache as ModuleCache;
use Alaress\Module\Loader as ModuleLoader;
use Alaress\Module\Registry;
use Alaress\Redis;
use Alaress\Redis\Cache\ItemPool;
use Alaress\Redis\Cache\KeyGenerator;
use Alaress\Session\Factory;
use Alaress\Session\Session;
use Alaress\Twig\Filter as AlaressFilter;
use Alaress\Twig\Loader\ChainLoader;
use Alaress\Twig\Method as TwigMethod;
use Alaress\Web\Output;
use Alaress\Web\Router\PathMatcher;
use Alaress\Web\Router\RouterFactory;
use Alaress_Date_Format;
use Alaress_Date_Locale_Config;
use Aura\Di;
use Aura\Router\Router;
use Aura\Web\WebFactory;
use Calendar\Api\ModuleChecker;
use Calendar_Api_Factory;
use enshrined\svgSanitize\Sanitizer as BaseSvgSanitizer;
use External_Class;
use External_ClassInterface;
use External_Markbook;
use External_Timetable;
use External_User;
use External_UserInterface;
use GuzzleHttp;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;
use InvalidArgumentException;
use League\Flysystem\FilesystemInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Key\KeyChainRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\ClientRepository;
use Predis;
use Proxy\Server as ProxyServer;
use RuntimeException;
use Schoolbox\Admin\Navigation\Renderer as AdminNavigationRenderer;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\WebRequest\RequestUriParser;
use Schoolbox\Application\WebRequest\ShutdownHandler;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Assessment\Course\Task\CompletionMapper as TaskCompletionMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource;
use Schoolbox\Auth\Remember;
use Schoolbox\Bookmark\Mapper as BookmarkMapper;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Database\Factory as SchoolboxDatabaseFactory;
use Schoolbox\External\Connector\CompositeSync;
use Schoolbox\External\Connector\CustomSync;
use Schoolbox\External\Connector\Factory as ExternalConnectorFactory;
use Schoolbox\External\Connector\ICommunityPortal;
use Schoolbox\External\Connector\Offline\Base\Sync as OfflineSync;
use Schoolbox\Filter;
use Schoolbox\Gotenberg\RequestFactory;
use Schoolbox\IConfigLoader;
use Schoolbox\Instance;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\L10n\RosettaStoneFactory;
use Schoolbox\Learning\Assessment\DataModel\ExternalMarkbook\Adapter as MarkbookAdapter;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DataModel\Translator as AssessmentDataModelTranslator;
use Schoolbox\Learning\Assessment\DueWork\Feedback\MapperProxy as DueWorkFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Response\MapperProxy as DueWorkSubmissionMapperProxy;
use Schoolbox\Learning\Assessment\Feedback\DataSource as AssessmentFeedbackDataSource;
use Schoolbox\Learning\Assessment\Feedback\SubSource\DueWork as FeedbackSubSourceDueWork;
use Schoolbox\Learning\Assessment\Feedback\SubSource\Lti as FeedbackSubSourceLti;
use Schoolbox\Learning\Assessment\Feedback\SubSource\Quiz as FeedbackSubSourceQuiz;
use Schoolbox\Learning\Assessment\Feedback\SubSource\Task as FeedbackSubSourceTask;
use Schoolbox\Learning\Assessment\Lti\Feedback\MapperProxy as LtiFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\Lti\Response\MapperProxy as LtiResponseMapperProxy;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Quiz\Attempt\MapperProxy as QuizAttemptMapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Feedback\MapperProxy as QuizFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Response\DataSource as AssessmentResponseDataSource;
use Schoolbox\Learning\Assessment\Response\SubSource\DueWork as ResponseSubSourceDueWork;
use Schoolbox\Learning\Assessment\Response\SubSource\Lti as ResponseSubSourceLti;
use Schoolbox\Learning\Assessment\Response\SubSource\Quiz as ResponseSubSourceQuiz;
use Schoolbox\Learning\Assessment\Response\SubSource\Task as ResponseSubSourceTask;
use Schoolbox\Learning\Assessment\Task\Feedback\MapperProxy as TaskFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\Task\Response\MapperProxy as TaskResponseMapperProxy;
use Schoolbox\Learning\Assessment\Transactor as LearningAssessmentTransactor;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Renderer as LearningRenderer;
use Schoolbox\LTI\AccessToken\Codec;
use Schoolbox\LTI\Assessment\Score\Mapper as LtiScoreMapper;
use Schoolbox\LTI\Auth\UserAuthenticator;
use Schoolbox\LTI\KeyChain\RepositoryFactory as KeyChainRepositoryFactory;
use Schoolbox\LTI\Provider\Mapper as LTIProviderMapper;
use Schoolbox\LTI\Provider\Mapper as ToolMapper;
use Schoolbox\LTI\Provider\Model as ToolModel;
use Schoolbox\LTI\Registration\RepositoryFactory as RegistrationRepositoryFactory;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\Mapper;
use Schoolbox\MapperFactory;
use Schoolbox\Menu\Side\Mapper as SideMenuMapper;
use Schoolbox\Menu\Side\Section\Mapper as SideMenuSectionMapper;
use Schoolbox\Menu\Top\Mapper as TopMenuMapper;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Notif\StorefileLogger as NotifStorefileLogger;
use Schoolbox\Pastoral\AccessLevelCensor;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;
use Schoolbox\Pastoral\DataSource as PastoralDataSource;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Revision\DataSource as PastoralRevisionDataSource;
use Schoolbox\Pastoral\Revision\Mapper as PastoralRevisionMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Realtime\Routing\Cache\Redis\ReadCache;
use Schoolbox\Realtime\Routing\Cache\Redis\WriteCache;
use Schoolbox\Realtime\Schedule\Trigger\Test\MockPropagator;
use Schoolbox\Realtime\Subscription\Redis\UnreadRetriever;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\HashGenerator;
use Schoolbox\Resource\Storage\Manager;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Sanitizer;
use Schoolbox\Role;
use Schoolbox\Sync\Grammar\Factory as SyncGrammarFactory;
use Schoolbox\Sync\Log\Mapper as SyncLogMapper;
use Schoolbox\Sync\Provisioner\Provisioner;
use Schoolbox\Sync\Provisioner\UserMapper as SyncUserMapper;
use Schoolbox\Sync\Rule\Mapper as SyncRuleMapper;
use Schoolbox\Sync\Ruleset\Factory as SyncRulesetFactory;
use Schoolbox\TaskDaemon\ProducerFactory;
use Schoolbox\TaskDaemon\Request\MessageFactory;
use Schoolbox\Tour\Mapper as TourMapper;
use Schoolbox\Tour\Step\Mapper as StepMapper;
use Schoolbox\UserMapper;
use Scrub;
use SitePage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;
use TheCodingMachine\Gotenberg\Client as GotenbergClient;
use Throwable;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Class Kernel
 * Also known as a FrontController or Mediator.
 *
 * @package Alaress\Application
 */
abstract class Kernel
{
    /** @var Di\Container */
    protected Di\Container $di;

    /**
     * Wrapper to enable prioritisation of shutdown functions
     *
     * @var ShutdownHandler
     */
    private ShutdownHandler $shutdownHandler;

    /**
     * A list of callbacks to call after an uncaught exception has been raised.
     * This includes ErrorException instances.
     *
     * @var callable[]
     */
    private $exceptionCallbacks = [];

    /**
     * @throws Di\Exception
     * @throws Di\Exception\ContainerLocked
     * @throws Di\Exception\ServiceNotFound
     * @throws Di\Exception\ServiceNotObject
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws \Schoolbox\External\Exception
     */
    public function __construct()
    {
        $this->di              = (new Di\ContainerBuilder())->newInstance();
        $this->shutdownHandler = new ShutdownHandler();

        // run the launch sequence
        $this->initialiseExternalConnections();
        $this->registerErrorHandling();
        $this->registerExceptionHandling();
        $this->registerShutdownHandling();
        $this->installDependencies($this->di);
        $this->postInstallDependencies();
        $this->initialiseDebugging();
        $this->checkServerApiEnvironment();
    }

    /**
     * @return ShutdownHandler
     */
    public function getShutdownHandler(): ShutdownHandler
    {
        return $this->shutdownHandler;
    }


    /**
     * Returns whether the current PHP process is being run by the system root user
     *
     * @return bool
     */
    protected function isRunAsSuperuser(): bool
    {
        return \posix_getuid() === 0;
    }


    /**
     * Initialise connections to databases, etc.
     *
     * @return void
     * @throws \Schoolbox\External\Exception
     */
    abstract protected function initialiseExternalConnections(): void;

    /**
     * Called after DI services are registered.
     */
    protected function postInstallDependencies(): void
    {
        $config = new ConfigLoader();
        session_save_path(PipeManager::getSessionSavePath($config->get('redis_url')));
    }

    /**
     * Initialise debugging
     *
     * @return void
     */
    abstract protected function initialiseDebugging(): void;

    /**
     * Register any error handling required
     *
     * @return void
     */
    abstract protected function registerErrorHandling(): void;

    /**
     * Register any out-the-top exception handling required
     *
     * @return void
     */
    abstract protected function registerExceptionHandling(): void;

    /**
     * Register any shutdown handling required
     *
     * @return void
     */
    abstract protected function registerShutdownHandling(): void;


    /**
     * @return Di\Container
     */
    public function getDependencyInjector(): Di\Container
    {
        return $this->di;
    }


    /**
     * Convenience method to retrieve service from DI
     *
     * @param string $service
     * @throws Di\Exception\ServiceNotFound
     * @return mixed
     */
    public function get($service)
    {
        return $this->getDependencyInjector()->get($service);
    }


    /**
     * @param callable $callable
     */
    public function registerCustomShutdown(callable $callable): void
    {
        $this->shutdownHandler->registerFunction($callable);
    }

    /**
     * @param callable $callable
     */
    public function registerExceptionCallback($callable)
    {
        if (is_callable($callable)) {
            $this->exceptionCallbacks[] = $callable;
        }
    }

    /**
     * Calls all exception callbacks with the given exception.
     *
     * @param Throwable $e
     */
    protected function callExceptionCallbacks($e)
    {
        foreach ($this->exceptionCallbacks as $callback) {
            call_user_func($callback, $e);
        }
    }


    /**
     * Ensures this kernel is being run in appropriate environment.
     *
     * The different Kernels have implementation tied to their Server API environments.
     * eg, the Daemon kernel uses Process Control functions, whereas the WebServer kernel requires a request.
     *
     * @see http://php.net/manual/en/function.php-sapi-name.php
     * @throws WrongSapiException If this Kernel has been constructed in an invalid environment.
     */
    abstract protected function checkServerApiEnvironment(): void;


    /**
     * Install any internal dependencies into the kernel (routing, etc.)
     *
     * This method should only SET services, not GET any of them: getting a
     * service locks the DI container, which raises an error on the next set.
     *
     * @param Di\Container $di
     * @return Di\Container
     * @throws Di\Exception
     * @throws Di\Exception\ContainerLocked
     * @throws Di\Exception\ServiceNotFound
     * @throws Di\Exception\ServiceNotObject
     * @throws InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function installDependencies(Di\Container $di): Di\Container
    {
        // Config loader
        $config = new ConfigLoader();
        $di->set('config', $config);

        // Redis cache (memory-only, not persistent)
        Redis::getCacheInstance()->connect($config->get('redis_ephemeral_url'), null, INSTANCE_ID);
        $di->set('redis.cache', function () {
            return Redis::getCacheClientInstance();
        });

        // Redis storage (persisted to disk)
        Redis::getStorageInstance()->connect($config->get('redis_url'), null, INSTANCE_ID);
        $di->set('redis.storage', function () {
            return Redis::getStorageClientInstance();
        });

        // Redis queue publish (not persistent)
        // (The subscription to this queue doesn't exist in the standard kernel,
        //  only in the daemon kernel: this is because it requires the event
        //  loop)
        Redis::aliasInstance(Redis::INSTANCE_CACHE, Redis::INSTANCE_SIGNAL_QUEUE);
        $di->set('redis.signalQueue.pub', function () {
            return Redis::getSignalQueueClientInstance();
        });

        // Default Redis connection:
        // uses cache, and raises a warning telling you to choose a connector
        $di->set('redis', $di->lazy(function () use ($di) {
            trigger_error(
                'The default "redis" connection is deprecated; falling back to using "redis.storage". (Use "redis.cache" for memory-only or "redis.storage" for persistent storage)',
                E_USER_WARNING,
            );
            return $di->get('redis.storage');
        }));

        $di->set(
            'moduleCache',
            $di->lazyNew(
                ModuleCache\Cache::class,
                ['redis' => $di->lazyGet('redis.cache')]
            )
        );
        $di->set(
            'moduleCache.rebuilder',
            $di->lazyNew(
                ModuleCache\Rebuilder::class,
                [
                    'di'     => $di,
                    'config' => $di->lazyGet('config'),
                    'router' => $di->lazyGet('router'),
                    'redis'  => $di->lazyGet('redis.cache'),
                    'logger' => null,
                ]
            )
        );

        // Task daemon message producer
        $di->set(
            'taskd.messageProducer',
            $di->lazy(
                function () use ($config) {
                    return (new ProducerFactory())->build(
                        $config->get(ConfigMapper::NAME_TASKD_REDIS_URL),
                        $config->get(ConfigMapper::NAME_TASKD_REDIS_QUEUE)
                    );
                }
            )
        );
        $di->set(
            'taskd.messageFactory',
            $di->lazyNew(
                MessageFactory::class,
                [
                    'instance'     => $di->lazyGet('instance'),
                    'tokenBuilder' => $di->lazyGet('user.tokenBuilder'),
                ],
            )
        );

        $di->set('amqp', $di->lazy(function () {
            return Connector::getInstance();
        }));

        $di->set('instance', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $conf */
            $conf = $di->get('config');
            return new Instance(
                INSTANCE_ID,
                $conf->get('site_hostname'),
                $conf->get('site_domain'),
                $conf->get('site_version'),
                $conf->get('site_type'),
                $conf->get('site_school_code'),
                $conf->get('site_environment'),
            );
        }));

        // install mapper DI
        // (yes I know this is icky, but it will have to do until mapper construction is set up with DI properly)
        Mapper::di($di);

        // Set correct timezone
        date_default_timezone_set($config->get('date_timezone'));

        // Notification factory
        $di->set(
            'notif',
            $di->lazy(
                function () {
                    // TODO replace this with $di->lazyGetCall() once we upgrade Aura.Di
                    return Notifactory::create();
                }
            )
        );

        // Database connection factory
        $di->set(
            'databaseFactory',
            function () {
                return new SchoolboxDatabaseFactory(
                    new AlaressDatabaseFactory()
                );
            }
        );

        // Schoolbox internal database connection
        $di->set(
            'databaseInternal',
            function () use ($di) {
                /** @var SchoolboxDatabaseFactory $factory */
                $factory = $di->get('databaseFactory');
                return $factory->getInternalDatabase();
            }
        );

        // External SIS database connection(s)
        $di->set(
            'databaseExternal',
            $di->lazy(
                function () use ($di) {
                    /** @var SchoolboxDatabaseFactory $factory */
                    $factory = $di->get('databaseFactory');

                    /** @var IConfigLoader $config */
                    $config = $di->get('config');

                    return $factory->getExternalDatabase($config);
                }
            )
        );

        // Web router
        $di->set(
            'router',
            $di->lazy(
                function () {
                    $factory = new RouterFactory();
                    return $factory->newInstance();
                }
            )
        );

        $this->installSessionService($di);

        // User from session
        $di->set(
            'user',
            $di->lazy(
                function () use ($di) {
                    $session = $di->get('session');
                    return $session->getAuthUser();
                }
            )
        );

        // Localisation
        $di->set(
            'l10n',
            $di->lazy(
                function () use ($di) {
                    $stoneFactory = new RosettaStoneFactory();
                    return $stoneFactory->build($di->get('mapperFactory')->get(L10nMapper::class));
                }
            )
        );

        // CDN prefix generator
        $di->set(
            'cdnPrefixGenerator',
            $di->lazyNew(PrefixGenerator::class, ['config' => $di->lazyGet('config')])
        );

        // Enable mappers to be injectable, but instantiated only when required
        $mapperFactory = new MapperFactory($di);
        $di->set('mapperFactory', $mapperFactory);

        // Preload of route parameters, and its module loader
        $di->set(
            'moduleLoader',
            $di->lazyNew(
                ModuleLoader::class,
                [
                    'di'          => $di,
                    'moduleRegex' => '/^(?P<module>[^\\.]+)\\./',
                ]
            ),
        );
        $di->set(
            'preloader',
            $di->lazyNew(
                PathMatcher::class,
                ['moduleLoader' => $di->lazyGet('moduleLoader'),]
            ),
        );

        // Authorization
        $di->set(
            'authorizer',
            $di->lazyNew(
                Authorizer::class,
                ['moduleLoader' => $di->lazyGet('moduleLoader'),]
            )
        );

        // Web request/response factory
        $di->set('webFactory', $di->lazyNew(WebFactory::class, ['globals' => $GLOBALS]));

        // SitePage :D
        $di->set(
            'sitePage',
            $di->lazyNew(SitePage::class, ['di' => $di])
        );

        $di->set(
            'commsNewsArticleResolver',
            $di->lazy(
                static function () use ($di) {

                    /** @var IConnector $db */
                    $db = $di->get('databaseInternal');

                    /** @var External_UserInterface $externalUser */
                    $externalUser = $di->get('externalUser');

                    /** @var External_ClassInterface $externalClass */
                    $externalClass = $di->get('externalClass');

                    /** @var News\Audience\Segment\StrategyFactory $strategyFactory */
                    $strategyFactory = $di->get('commsNewsAudienceStrategyFactory');

                    /** @var MapperFactory $mapperFactory */
                    $mapperFactory = $di->get('mapperFactory');

                    /** @var UserAccessMapper $userAccessMapper */
                    $userAccessMapper = $mapperFactory->get(UserAccessMapper::class);

                    /** @var Folder\Mapper $folderMapper */
                    $folderMapper = $mapperFactory->get(Folder\Mapper::class);

                    /** @var UserMapper $userMapper */
                    $userMapper = $mapperFactory->get(UserMapper::class);

                    /** @var News\Audience\Complex\Mapper $complexMapper */
                    $complexMapper = $mapperFactory->get(News\Audience\Complex\Mapper::class);

                    /** @var News\Mapper $newsMapper */
                    $newsMapper = $mapperFactory->get(News\Mapper::class);

                    return new News\Audience\ArticleResolver(
                        StrategyFactory::getValidNullEntities(),
                        $strategyFactory->getValidRelationshipEntityMap(),
                        $db,
                        $folderMapper,
                        new News\Audience\RelationshipFilterer(
                            $db,
                            $userMapper,
                            $externalUser,
                            $folderMapper,
                            $externalClass,
                            $userAccessMapper
                        ),
                        $strategyFactory->makeAllSimpleStrategies(),
                        $complexMapper,
                        $newsMapper
                    );
                }
            )
        );

        $di->set(
            'commsNewsUserResolver',
            $di->lazy(
                static function () use ($di) {

                    /** @var IConnector $db */
                    $db = $di->get('databaseInternal');

                    /** @var External_UserInterface $externalUser */
                    $externalUser = $di->get('externalUser');

                    /** @var External_ClassInterface $externalClass */
                    $externalClass = $di->get('externalClass');

                    /** @var News\Audience\Segment\StrategyFactory $strategyFactory */
                    $strategyFactory = $di->get('commsNewsAudienceStrategyFactory');

                    /** @var MapperFactory $mapperFactory */
                    $mapperFactory = $di->get('mapperFactory');

                    /** @var Folder\Mapper $folderMapper */
                    $folderMapper = $mapperFactory->get(Folder\Mapper::class);

                    /** @var UserMapper $userMapper */
                    $userMapper = $mapperFactory->get(UserMapper::class);

                    /** @var UserAccessMapper $userAccessMapper */
                    $userAccessMapper = $mapperFactory->get(UserAccessMapper::class);

                    return new News\Audience\UserResolver(
                        $db,
                        new News\Audience\RelationshipFilterer(
                            $db,
                            $userMapper,
                            $externalUser,
                            $folderMapper,
                            $externalClass,
                            $userAccessMapper
                        ),
                        $strategyFactory
                    );
                }
            )
        );

        $di->set(
            'commsNewsAudienceStrategyFactory',
            $di->lazy(
                function () use ($di) {

                    /** @var MapperFactory $mapperFactory */
                    $mapperFactory = $di->get('mapperFactory');

                    /** @var UserAccessMapper $userAccessMapper */
                    $userAccessMapper = $mapperFactory->get(UserAccessMapper::class);

                    /** @var Folder\Mapper $folderMapper */
                    $folderMapper = $mapperFactory->get(Folder\Mapper::class);

                    /** @var RoleAccessMapper $roleAccessMapper */
                    $roleAccessMapper = $mapperFactory->get(RoleAccessMapper::class);

                    /** @var Access $access */
                    $access = $mapperFactory->get(Access::class);

                    /** @var IConfigLoader $config */
                    $config = $di->get('config');

                    /** @var External_UserInterface $externalUser */
                    $externalUser = $di->get('externalUser');

                    /** @var IConnector $db */
                    $db = $di->get('databaseInternal');

                    $knownUserClasses = $this->getKnownUserClassesFromSession($di);

                    return new News\Audience\Segment\StrategyFactory(
                        $userAccessMapper,
                        $config->get(ConfigMapper::_STAFF_CAN_POST_NEWS),
                        $config->get(ConfigMapper::_GLOBAL_MODERATION),
                        $config->get(ConfigMapper::_AUDIENCE_TO_FOLDER_TEACHERS),
                        $config->get(ConfigMapper::NAME_FIXTURES_ENABLED),
                        $folderMapper,
                        $roleAccessMapper,
                        $access,
                        $externalUser,
                        $db,
                        $knownUserClasses
                    );
                }
            )
        );

        // Web output class - currently uses Sitepage
        $di->set('webOutput:base', $di->lazyNew(Output\Base::class));
        $di->set('webOutput:html', $di->lazy(function () use ($di, $mapperFactory) {

            /** @var IConfigLoader $config */
            $config  = $di->get('config');

            /** @var Session $session */
            $session = $di->get('session');

            /** @var TourMapper $tourMapper */
            $tourMapper = $mapperFactory->get(TourMapper::class);

            /** @var StepMapper $tourStepMapper */
            $tourStepMapper = $mapperFactory->get(StepMapper::class);
            $tourList = new \Schoolbox\Tour\Lists($tourMapper, $tourStepMapper);
            $output   = new Output\ThemedHtml();

            /** @var Environment $templateEngine */
            $templateEngine = $di->get('templateEngine');

            /** @var TopMenuMapper $topMenuMapper */
            $topMenuMapper = $mapperFactory->get(TopMenuMapper::class);

            /** @var SideMenuSectionMapper $sideMenuSectionMapper */
            $sideMenuSectionMapper = $mapperFactory->get(SideMenuSectionMapper::class);

            /** @var SideMenuMapper $sideMenuMapper */
            $sideMenuMapper = $mapperFactory->get(SideMenuMapper::class);

            /** @var BookmarkMapper $bookMarkMapper */
            $bookMarkMapper = $mapperFactory->get(BookmarkMapper::class);

            /** @var Predis\ClientInterface $redisStorage */
            $redisStorage = $di->get('redis.storage');
            $output
                ->setTemplateEngine($templateEngine)
                ->addDataGenerator(
                    'ws',
                    new Output\DataGenerator\Websocket(
                        $config,
                        $session->getAuthUser(),
                        new UnreadRetriever($redisStorage),
                        INSTANCE_ID
                    )
                )
                ->addDataGenerator(
                    'tour',
                    new Output\DataGenerator\Tour($config, $session->getAuthUser(), $tourList, $_SERVER)
                )
                ->addDataGenerator('user', new Output\DataGenerator\User($session->getAuthUser(), $session))
                ->addDataGenerator(
                    'topMenu',
                    new Output\DataGenerator\TopMenu(
                        $session->getAuthUser(),
                        $topMenuMapper
                    )
                )
                ->addDataGenerator(
                    'sideMenu',
                    new Output\DataGenerator\SideMenu(
                        $config,
                        $session->getAuthUser(),
                        $session,
                        $sideMenuSectionMapper,
                        $sideMenuMapper,
                        $bookMarkMapper
                    )
                )
                ->addDataGenerator('scripts', new Output\DataGenerator\Scripts($config, $_SERVER));

            if ($config->get('debug_mode')) {
                $output->addDataGenerator(
                    'debugBar',
                    new Output\DataGenerator\DebugBar(\Alaress\DebugBar\Instance::getInstance(), '/debugbar/')
                );
            }

            return $output;
        }));

        $outputSetters = ['setTemplateEngine' => $di->lazyGet('templateEngine')];
        $di->set('webOutput:nochrome', $di->lazyNew(Output\Html::class, [], $outputSetters));
        $di->set('webOutput:json', $di->lazyNew(Output\Json::class));
        $di->set('webOutput:file', $di->lazyNew(Output\File::class));
        $di->set('webOutput:stream', $di->lazyNew(Output\Stream::class, [], $outputSetters));
        $di->set('webOutput:noContent', $di->lazyNew(Output\NoContent::class));
        $di->set('webOutput:xml', $di->lazyNew(Output\Xml::class, [], $outputSetters));

        // Template engine and functions
        // Template loader: sets up link to templates without a namespace.
        $di->set(
            'templateEngine:loader',
            $di->lazy(
                function () use ($di) {
                    $config      = $di->get('config');
                    $chainLoader = new ChainLoader(
                        new ModuleLoader($di, '[^@(?P<module>[^/]+)/]'),
                        new \Twig\Loader\ChainLoader()
                    );
                    $baseLoader  = new FilesystemLoader(array());
                    // Paths: prefer templates/{site_school_code} over templates/core
                    $basePath     = SITE_root . '/templates/';
                    $overridePath = $basePath . strtolower($config->get('site_school_code'));
                    $corePath     = $basePath . 'core';
                    if (is_dir($overridePath)) {
                        $baseLoader->addPath($overridePath);
                    }
                    $baseLoader->addPath($corePath);
                    // Core path: always uses templates/core, even if templates/{site_school_code} exists
                    // Used as a base for default path templates to inherit from.
                    $baseLoader->addPath($corePath, 'core');
                    $chainLoader->addLoader($baseLoader);
                    return $chainLoader;
                }
            )
        );

        $di->set('templateEngine:requireModule', $di->lazyNew(TwigMethod\RequireModule::class, ['di' => $di]));
        $di->set(
            'templateEngine:routeLookup',
            $di->lazyNew(
                TwigMethod\RouteLookup::class,
                [],
                ['setRouter' => $di->lazyGet('router')],
            )
        );
        $di->set(
            'templateEngine:isRouteAuthorized',
            $di->lazyNew(
                TwigMethod\IsRouteAuthorized::class,
                [],
                [
                    'setRouter'     => $di->lazyGet('router'),
                    'setAuthorizer' => $di->lazyGet('authorizer'),
                    'setSession'    => $di->lazyGet('session'),
                ],
            )
        );
        $di->set(
            'templateEngine:routeLookupIfAuthorized',
            $di->lazyNew(
                TwigMethod\RouteLookupIfAuthorized::class,
                [
                    'routeLookup'       => $di->lazyGet('templateEngine:routeLookup'),
                    'isRouteAuthorized' => $di->lazyGet('templateEngine:isRouteAuthorized'),
                ]
            )
        );

        $di->set(
            'templateEngine',
            $di->lazy(
                function () use ($di) {
                    /** @var IConfigLoader $config */
                    $config = $di->get('config');

                    $options = [
                        'debug'            => $config->get('debug_templates'),
                        'strict_variables' => $config->get('strict_templates'),
                    ];

                    // do not cache when in debug mode
                    if (!$config->get('debug_mode')) {
                        $options['cache'] = $config->get('twig_cache_dir');
                    } else {
                        if (class_exists('\Alaress\Web\Template\DebugTwig')) {
                            $twigClass = '\Alaress\Web\Template\DebugTwig';
                        } else {
                            $twigClass = 'Twig_Template';
                        }
                        $options['base_template_class'] = $twigClass;
                    }

                    $twig = new Environment($di->get('templateEngine:loader'), $options);

                    // HTML Purifier filter - known to output safe HTML
                    $twig->addFilter(
                        new TwigFilter(
                            'purify',
                            [Scrub::class, 'html'],
                            ['is_safe' => ['html']]
                        )
                    );

                    // HTML2Text filter
                    $twig->addFilter(
                        new TwigFilter(
                            'html2text',
                            [Scrub::class, 'html2text']
                        )
                    );

                    // Linkify filter
                    $twig->addFilter(
                        new TwigFilter(
                            'linkify',
                            [Scrub::class, 'linkifyText'],
                            ['is_safe' => ['html']]
                        )
                    );

                    // Markdown filter
                    $twig->addFilter(
                        new TwigFilter(
                            'markdown',
                            [Scrub::class, 'markdown'],
                            ['is_safe' => ['html']]
                        )
                    );

                    // Cut sentence filter
                    $twig->addFilter(
                        new TwigFilter(
                            'cutSentence',
                            [\TextFormatter::class, 'cutSentence']
                        )
                    );

                    // Bytes to string filter
                    $twig->addFilter(
                        new TwigFilter(
                            'bytes',
                            [\Alaress_Byte_Format::class, 'bytesToString']
                        )
                    );

                    $twig->addFilter(
                        new TwigFilter(
                            'column',
                            [Collection::class, 'arrayColumn']
                        )
                    );

                    // MomentJsFormat filter
                    $twig->addFilter(
                        new TwigFilter(
                            'dayjsFormat',
                            [\Alaress_Date_Format_Converter::class, 'toDayjs']
                        )
                    );

                    // Case insenstive replace function
                    $twig->addFilter(
                        new TwigFilter(
                            'ireplace',
                            function ($string, array $tokens = []) {
                                return str_ireplace(array_keys($tokens), array_values($tokens), $string);
                            }
                        )
                    );

                    // Relative date function
                    $twig->addFilter(new TwigFilter('relativeDate', new AlaressFilter\RelativeDate()));

                    // Global access to config object and localisation
                    // TODO capitalize globals
                    $twig->addGlobal('config', $config);
                    $twig->addGlobal('locale', $di->get('l10n'));
                    $dateFormats = new Alaress_Date_Locale_Config();
                    $dateFormats->setConfig($config);
                    $twig->addGlobal('dateFormats', $dateFormats);
                    $twig->addGlobal('NOW', Alaress_Date_Format::now());

                    // This value is liable to be overridden by the user within
                    // the controller
                    $twig->addGlobal('USER', $di->get('user'));

                    /** @var PrefixGenerator $cdnPrefixGenerator */
                    $cdnPrefixGenerator = $di->get('cdnPrefixGenerator');
                    $twig->addGlobal('CDN_PREFIX', $cdnPrefixGenerator->generate());

                    // Global access to returnUrl vars
                    $encodedUrl = Redirect::encode(RequestUriParser::parseUrlPath($_SERVER));
                    $twig->addGlobal(
                        'RETURN_URL',
                        [
                            'name'     => Redirect::RETURN_URL,
                            'current'  => $encodedUrl,
                            'previous' => [
                                'encoded' => $_REQUEST[Redirect::RETURN_URL] ?? null,
                                'decoded' => isset($_REQUEST[Redirect::RETURN_URL])
                                    ? Redirect::decode($_REQUEST[Redirect::RETURN_URL])
                                    : null,
                            ]
                        ]
                    );

                    // Define modules that are required by a template
                    $twig->addFunction(
                        new TwigFunction('requireModule', $di->get('templateEngine:requireModule'))
                    );

                    // Add the route lookup and authorization methods
                    $twig->addFunction(
                        new TwigFunction(
                            'routeLookup',
                            array($di->get('templateEngine:routeLookup'), 'execute')
                        )
                    );
                    $twig->addFunction(
                        new TwigFunction(
                            'isRouteAuthorized',
                            array($di->get('templateEngine:isRouteAuthorized'), 'execute')
                        )
                    );

                    $twig->addFunction(
                        new TwigFunction(
                            'getGuid',
                            function () {
                                mt_srand((double)microtime() * 10000);
                                return strtoupper(md5(uniqid((string)rand(), true)));
                            }
                        )
                    );

                    $twig->addFunction(new TwigFunction('routeLookupIfAuthorized', $di->get('templateEngine:routeLookupIfAuthorized')));

                    $twig->addFunction(
                        new TwigFunction(
                            'generateHiddenInputs',
                            new TwigMethod\GenerateHiddenInputs(),
                            ['is_safe' => ['html']]
                        )
                    );

                    // Add extensions after everything else.
                    // According to https://twig.symfony.com/doc/1.x/deprecated.html#globals
                    // globals must be loaded before extensions.
                    $twig->addExtension(new DebugExtension());
                    $twig->addExtension(new AdminNavigationRenderer());
                    $twig->addExtension(new LearningRenderer());

                    return $twig;
                }
            )
        );

        // Build a user provisioner
        // TODO unify this definition and the one in Daemon\Bootstrap
        $di->set(
            'ups.logger',
            $di->lazyNew(
                NotifStorefileLogger::class,
                [
                    'receiver' => $di->lazyNew(StorageMapper::class),
                    'filename' => $di->lazy(function () {
                        return tempnam(sys_get_temp_dir(), 'sb-storefile-');
                    }),
                ]
            )
        );

        // Build a user provisioner
        $di->set(
            'ups.provisioner',
            $di->lazyNew(
                Provisioner::class,
                [
                    'rulesetFactory' => $di->lazyNew(SyncRulesetFactory::class),
                    'grammarFactory' => $di->lazyNew(
                        SyncGrammarFactory::class,
                        [
                            'log'        => $di->lazyGet('ups.logger'),
                            'userMapper' => $di->lazy(function () {
                                $map = new SyncUserMapper();
                                $map->setEventPropagator(new MockPropagator());
                                return $map;
                            }),
                            'ruleMapper' => $di->lazyNew(SyncRuleMapper::class),
                        ]
                    ),
                    'externalUserSource' => $di->lazy(function () use ($di) {
                        /** @var IConfigLoader $config */
                        $config = $di->get('config');

                        /** @var array|IConnector $databaseExternal */
                        $databaseExternal = $di->get('databaseExternal');
                        $databaseExternal = ($databaseExternal instanceof NullConnector) ? null : $databaseExternal;

                        $defaultSync = (new ExternalConnectorFactory())->build($config, $databaseExternal, ExternalConnectorFactory::EXTERNAL_SYNC);
                        if ($defaultSync instanceof OfflineSync) {
                            return $defaultSync;
                        }

                        $customSync = new CustomSync($config, $databaseExternal);
                        return (new CompositeSync())
                            ->addSubSync($customSync)
                            ->addSubSync($defaultSync);
                    }),
                    'userMapper'       => $di->lazyNew(SyncUserMapper::class),
                    'logger'           => $di->lazyGet('ups.logger'),
                    'logMapper'        => $di->lazyNew(SyncLogMapper::class),
                    'userAccessMapper' => $di->lazyNew(UserAccessMapper::class),
                    'folderMapper'     => $di->lazyNew(Folder\Mapper::class),
                    'config'           => $config,
                ]
            )
        );

        // Register External_User service
        $di->set('externalUser', $di->lazyNew(External_User::class));

        // Register External_Timetable service
        $di->set('externalTimetable', $di->lazyNew(External_Timetable::class));

        $di->set('externalCommunityPortal', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $config */
            $config = $di->get('config');

            /** @var array|IConnector $databaseExternal */
            $databaseExternal = $di->get('databaseExternal');

            // Create the external connection to community portal

            $factory = new ExternalConnectorFactory();

            /** @var ICommunityPortal $communityPortal */
            $communityPortal = $factory->build(
                $config,
                $databaseExternal,
                ExternalConnectorFactory::EXTERNAL_COMMUNITY_PORTAL
            );

            // Load in the key and initialization vector that are required before it can be used

            $webServicesKeyFile = '/etc/schoolbox-synergetic/config.json';

            if (is_readable($webServicesKeyFile) && is_file($webServicesKeyFile)) {
                $config = json_decode(file_get_contents($webServicesKeyFile), true);

                $communityPortal->setSecrets(
                    base64_decode($config['key']),
                    base64_decode($config['iv'])
                );
            } else {
                trigger_error('Cannot read community portal key - password will not be changed', E_USER_WARNING);
            }

            return $communityPortal;
        }));

        // Register external markbook service
        $di->set('externalMarkbook', $di->lazyNew(External_Markbook::class));
        $di->set(
            'externalMarkbookAdapter',
            $di->lazyNew(
                MarkbookAdapter::class,
                [
                    'dataSource'       => $di->lazyGet('assessmentDataSource'),
                    'externalMarkbook' => $di->lazyGet('externalMarkbook'),
                ]
            )
        );

        // Document Manager
        $di->set('externalDocumentManager', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $config */
            $config = $di->get('config');

            /** @var array|IConnector $databaseExternal */
            $databaseExternal = $di->get('databaseExternal');

            // Create the external connection to docman
            $factory = new ExternalConnectorFactory();
            return $factory->build(
                $config,
                $databaseExternal,
                ExternalConnectorFactory::EXTERNAL_DOCUMENT
            );
        }));

        // Register external NDS object services

        $di->set('externalNDSUser', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $config */
            $config = $di->get('config');

            /** @var array|IConnector $databaseExternal */
            $databaseExternal = $di->get('databaseExternal');

            $factory = new ExternalConnectorFactory();
            return $factory->build(
                $config,
                $databaseExternal,
                ExternalConnectorFactory::EXTERNAL_USER
            );
        }));

        $di->set('externalNDSTimetable', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $config */
            $config = $di->get('config');

            /** @var array|IConnector $databaseExternal */
            $databaseExternal = $di->get('databaseExternal');

            $factory = new ExternalConnectorFactory();
            return $factory->build(
                $config,
                $databaseExternal,
                ExternalConnectorFactory::EXTERNAL_TIMETABLE
            );
        }));

        // LTI Provider mapper
        $di->set('providerMapper', $di->lazyNew(LTIProviderMapper::class));

        // Register External_Class service
        $di->set('externalClass', $di->lazyNew(External_Class::class));

        // Register assessment data source
        $di->set(
            'assessmentTranslator',
            $di->lazy(function () use ($mapperFactory) {
                /** @var AssessmentMapper $assessmentMapper */
                $assessmentMapper = $mapperFactory->get(AssessmentMapper::class);
                $translator       = new AssessmentDataModelTranslator(
                    new MarkSchemeFactory(),
                    $assessmentMapper,
                    AssessmentDataModelTranslator::MAX_PROJECT_LEVELS_DEFAULT
                );
                $translator
                    ->addSubTranslator(new AssessmentDataModelTranslator\Task())
                    ->addSubTranslator(new AssessmentDataModelTranslator\DueWork())
                    ->addSubTranslator(new AssessmentDataModelTranslator\Quiz())
                    ->addSubTranslator(new AssessmentDataModelTranslator\Lti())
                    ->addSubTranslator(new AssessmentDataModelTranslator\Project($translator))
                ;
                return $translator;
            })
        );

        // Register assessment data source
        $di->set(
            'assessmentDataSource',
            $di->lazyNew(
                AssessmentDataSource::class,
                [
                    'baseMapper'            => $mapperFactory->getLazy(AssessmentMapper::class),
                    'translator'            => $di->lazyGet('assessmentTranslator'),
                    'participantDataSource' => $di->lazyGet('participantDataSource'),
                ]
            )
        );

        // Register participant data source (with responses and feedback)
        $di->set(
            'participantDataSource',
            $di->lazyNew(
                ParticipantDataSource::class,
                [
                    'externalDb'         => $di->lazyGet('externalClass'),
                    'userAccessMapper'   => $mapperFactory->getLazy(UserAccessMapper::class),
                    'userMapper'         => $mapperFactory->getLazy(UserMapper::class),
                    'responseDataSource' => $di->lazyGet('responseDataSource'),
                    'feedbackDataSource' => $di->lazyGet('feedbackDataSource'),
                    'planMapper'         => $mapperFactory->getLazy(\Schoolbox\Assessment\Participant\Plan\Mapper::class)
                ]
            )
        );


        /* DUE WORK SOURCES */
        $di->set(
            'submissionDataSource',
            $di->lazyNew(
                DueWorkSubmissionMapperProxy::class,
                ['mapper' => $mapperFactory->getLazy(SubmissionMapper::class)]
            )
        );
        $di->set(
            'returnDataSource',
            $di->lazyNew(
                DueWorkFeedbackMapperProxy::class,
                ['mapper' => $mapperFactory->getLazy(ReturnedWorkMapper::class)]
            )
        );


        /* QUIZ SOURCES */
        $di->set(
            'attemptDataSource',
            $di->lazyNew(
                QuizAttemptMapperProxy::class,
                ['mapper' => $mapperFactory->getLazy(QuizAttemptMapper::class)]
            )
        );
        $di->set(
            'reviewDataSource',
            $di->lazyNew(
                QuizFeedbackMapperProxy::class,
                ['mapper' => $mapperFactory->getLazy(QuizAttemptMapper::class)]
            )
        );


        /* TASK SOURCES */
        $di->set(
            'completionDataSource',
            $di->lazyNew(
                TaskResponseMapperProxy::class,
                ['mapper' => $mapperFactory->getLazy(TaskCompletionMapper::class)]
            )
        );
        $di->set(
            'ratificationDataSource',
            $di->lazyNew(
                TaskFeedbackMapperProxy::class,
                ['mapper' => $mapperFactory->getLazy(TaskCompletionMapper::class)]
            )
        );


        // LTI sources, without funny names
        $di->set(
            'ltiResponseDataSource',
            $di->lazyNew(
                LtiResponseMapperProxy::class,
                ['mapper' => $mapperFactory->getLazy(LtiScoreMapper::class)]
            )
        );
        $di->set(
            'ltiFeedbackDataSource',
            $di->lazyNew(
                LtiFeedbackMapperProxy::class,
                ['mapper' => $mapperFactory->getLazy(LtiScoreMapper::class)]
            )
        );


        // Register response data source
        $di->set(
            'responseDataSource',
            $di->lazy(
                function () use ($di) {

                    /** @var DueWorkSubmissionMapperProxy $submissionDataSource  */
                    $submissionDataSource = $di->get('submissionDataSource');

                    /** @var TaskResponseMapperProxy $completionDataSource */
                    $completionDataSource = $di->get('completionDataSource');

                    /** @var QuizAttemptMapperProxy $attemptDataSource */
                    $attemptDataSource = $di->get('attemptDataSource');

                    /** @var LtiResponseMapperProxy $ltiResponseDataSource */
                    $ltiResponseDataSource = $di->get('ltiResponseDataSource');

                    return (new AssessmentResponseDataSource())
                        ->addSubSource(new ResponseSubSourceDueWork($submissionDataSource))
                        ->addSubSource(new ResponseSubSourceTask($completionDataSource))
                        ->addSubSource(new ResponseSubSourceQuiz($attemptDataSource))
                        ->addSubSource(new ResponseSubSourceLti($ltiResponseDataSource));
                }
            )
        );

        // Register feedback data source
        $di->set(
            'feedbackDataSource',
            $di->lazy(
                function () use ($di) {

                    /** @var DueWorkFeedbackMapperProxy $returnDataSource */
                    $returnDataSource = $di->get('returnDataSource');

                    /** @var TaskFeedbackMapperProxy $ratificationDataSource */
                    $ratificationDataSource = $di->get('ratificationDataSource');

                    /** @var QuizFeedbackMapperProxy $reviewDataSource */
                    $reviewDataSource = $di->get('reviewDataSource');

                    /** @var LtiFeedbackMapperProxy $ltiFeedbackDataSource */
                    $ltiFeedbackDataSource = $di->get('ltiFeedbackDataSource');

                    return (new AssessmentFeedbackDataSource())
                        ->addSubSource(new FeedbackSubSourceDueWork($returnDataSource))
                        ->addSubSource(new FeedbackSubSourceTask($ratificationDataSource))
                        ->addSubSource(new FeedbackSubSourceQuiz($reviewDataSource))
                        ->addSubSource(new FeedbackSubSourceLti($ltiFeedbackDataSource));
                }
            )
        );

        $di->set(
            'transactor',
            $di->lazyNew(
                LearningAssessmentTransactor::class,
                [
                    'folderMapper'             => $mapperFactory->getLazy(Folder\Mapper::class),
                    'unitImplementationMapper' => $mapperFactory->getLazy(\Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper::class),
                    'unitSpecificationMapper'  => $mapperFactory->getLazy(\Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper::class),
                    'assessmentMapperProxy'    => $di->lazyGet('assessmentDataSource'),
                    'plagscanMapper'           => $mapperFactory->getLazy(\Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper::class),
                    'turnitinGroupMapper'      => $mapperFactory->getLazy(\Schoolbox\Learning\Turnitin\Group\Mapper::class),
                    'submissionBoxMapper'      => $mapperFactory->getLazy(\Schoolbox\Assessment\Component\SubmissionBoxMapper::class),
                    'submissionFileMapper'     => $mapperFactory->getLazy(\Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper::class),
                    'quizMapper'               => $mapperFactory->getLazy(\Schoolbox\Learning\Quiz\Mapper::class),
                    'router'                   => $di->lazyGet('router'),
                    'replicator'               => $di->lazyNew(
                        Replicator::class,
                        [
                            'assessmentMapperProxy' => $di->lazyGet('assessmentDataSource'),
                            'quizMapper'            => $mapperFactory->getLazy(\Schoolbox\Learning\Quiz\Mapper::class),
                            'rubricMapper'          => $di->lazyNew(DataSource::class),
                        ]
                    ),
                ]
            )
        );

        $di->set(
            'proxyServer',
            $di->lazyNew(
                ProxyServer::class,
                ['conf' => $di->lazyGet('config')]
            )
        );

        // TODO this should be used wherever a HTTP client is used
        $di->set(
            'httpClient',
            $di->lazy(
                static function () use ($di) {
                    /** @var ProxyServer $proxyServer */
                    $proxyServer = $di->get('proxyServer');
                    return new GuzzleHttp\Client(['curl' => $proxyServer->buildCurlOptions()]);
                }
            ),
        );

        // Kami connector, as used for communicating with Kami's API
        $di->set('kamiConnector', $di->lazyNew(Kami\Connector::class, [
            'client'         => $di->lazyGet('httpClient'),
            'enabled'        => $config->get('kami_enabled'),
            'apiKey'         => $config->get('kami_api_key'),
            'siteSchoolCode' => $config->get('site_school_code'),
        ]));

        // PDF generator
        $di->set(
            'pdfGenerator',
            $di->lazy(
                static function () use ($di) {
                    /** @var IConfigLoader $config */
                    $config = $di->get('config');
                    /** @var ProxyServer $proxyServer */
                    $proxyServer = $di->get('proxyServer');
                    $httpClient  = new GuzzleHttp\Client(
                        ['curl' => $proxyServer->buildCurlOptions(RequestFactory::DEFAULT_WAIT_TIMEOUT)]
                    );
                    return new GotenbergClient(
                        $config->get(ConfigMapper::NAME_GOTENBERG_HOST),
                        new GuzzleAdapter($httpClient),
                    );
                }
            )
        );

        // injectable module auth rules
        $di->set('rule:isLoggedIn', $di->lazyNew(Rule\IsLoggedIn::class));
        $di->set('rule:isSuperuser', $di->lazyNew(Rule\IsSuperuser::class));
        $di->set('rule:isStaff', $di->lazyNew(Rule\HasRole::class, [ 'role' => Role::STAFF   ]));
        $di->set('rule:isStudent', $di->lazyNew(Rule\HasRole::class, [ 'role' => Role::STUDENT ]));
        $di->set('rule:isParent', $di->lazyNew(Rule\HasRole::class, [ 'role' => Role::PARENT  ]));

        // composite rules
        $di->set('rule:isStaffOrSuper', $di->lazy(function () use ($di) {
            return new Rule\Any($di->get('rule:isSuperuser'), $di->get('rule:isStaff'));
        }));
        $di->set('rule:isStaffOrStudent', $di->lazy(function () use ($di) {
            return new Rule\Any($di->get('rule:isStaff'), $di->get('rule:isStudent'));
        }));


        // Calendar API factory - requires an assessment data source to build some types
        $di->set(
            'calendarApiFactory',
            $di->lazy(
                function () use ($di) {
                    try {
                        /** @var IConnector $databaseExternal */
                        $databaseExternal = $di->get('databaseExternal');
                        /** @phpstan-ignore-next-line this _can_ raise this exception */
                    } catch (AlaressDatabaseException $e) {
                        $databaseExternal = null;
                    }

                    /** @var AssessmentDataSource $assessmentDataSource */
                    $assessmentDataSource  = $di->get('assessmentDataSource');
                    /** @var IConfigLoader $config */
                    $config = $di->get('config');
                    return new Calendar_Api_Factory($assessmentDataSource, new ModuleChecker($config), $config, $databaseExternal);
                }
            )
        );

        $di->set(
            'calendarDashboardApiFactory',
            $di->lazy(
                function () use ($di) {
                    try {
                        /** @var IConnector $databaseExternal */
                        $databaseExternal = $di->get('databaseExternal');
                    } catch (AlaressDatabaseException $e) {
                        $databaseExternal = null;
                    }

                    /** @var AssessmentDataSource $assessmentDataSource */
                    $assessmentDataSource  = $di->get('assessmentDataSource');
                    /** @var IConfigLoader $config */
                    $config = $di->get('config');
                    return new \Schoolbox\Calendar\Component\Dashboard\ApiFactory($assessmentDataSource, new ModuleChecker($config), $config, $databaseExternal);
                }
            )
        );


        // Pastoral Care data source
        // TODO consider moving into a module's dependencies?
        $di->set(
            'pastoralDataSource',
            $di->lazyNew(
                PastoralDataSource::class,
                [
                    'mapper'              => $mapperFactory->getLazy(PastoralMapper::class),
                    'accessLevelCensor'   => $di->lazyNew(AccessLevelCensor::class),
                    'pastoralAuditMapper' => $mapperFactory->getLazy(PastoralAuditMapper::class),
                ]
            )
        );
        $di->set(
            'pastoralRevisionDataSource',
            $di->lazyNew(
                PastoralRevisionDataSource::class,
                [
                    'mapper'            => $mapperFactory->getLazy(PastoralRevisionMapper::class),
                    'accessLevelCensor' => $di->lazyNew(AccessLevelCensor::class),
                ]
            )
        );

        // notification routing cache
        $di->set(
            'subscriptionCache',
            $di->lazyNew(
                WriteCache::class,
                [
                    'redis'         => $di->lazyGet('redis.storage'),
                    'loaderFactory' => $di->lazyNew(
                        \Schoolbox\Realtime\Routing\Subscription\Loader\Factory::class,
                        [
                            'redis' => $di->lazyGet('redis.storage'),
                        ],
                        [
                            'setUserMapper' => $mapperFactory->getLazy(UserMapper::class),
                            'setGenerator'  => $di->lazyNew(
                                \Schoolbox\Notif\Queue\Bindings\Generator::class,
                                [
                                    'xUserHandler'      => $di->lazyGet('externalUser'),
                                    'userMapper'        => $mapperFactory->getLazy(UserMapper::class),
                                    'roleMapper'        => $mapperFactory->getLazy(\Schoolbox\RoleMapper::class),
                                    'userAccessMapper'  => $mapperFactory->getLazy(UserAccessMapper::class),
                                    'categoryMapper'    => $mapperFactory->getLazy(Folder\Mapper::class)
                                ]
                            )
                        ]
                    ),
                    'redisQueue'    => $di->lazyGet('redis.signalQueue.pub'),
                ]
            )
        );

        $di->set(
            'subscriptionCache.readonly',
            $di->lazyNew(
                ReadCache::class,
                [
                    'redis' => $di->lazyGet('redis.storage'),
                ]
            )
        );


        // install sentry
        $sentryClient  = null;
        // `$sentryToken` and `$sentryProject` are never defined in PHPStan
        $sentryToken = \defined('SENTRY_TOKEN') ? SENTRY_TOKEN : '';
        $sentryProject = \defined('SENTRY_PROJECT') ? SENTRY_PROJECT : '';
        /** @phpstan-ignore-next-line */
        if (\strlen($sentryToken) && \strlen($sentryProject)) {
            // add sentry to the DI
            $di->set(
                'sentry',
                $di->lazy(
                    static function () use ($di, $sentryToken, $sentryProject) {
                        try {
                            /** @var Instance $sb */
                            $sb = $di->get('instance');
                            return (new \Alaress\Sentry\Factory())->build(
                                $sb,
                                Instance::APPLICATION_NAME,
                                $sentryToken,
                                $sentryProject
                            );
                        } catch (Di\Exception\ServiceNotFound $e) {
                            // do nothing; sentry is not installed
                        }
                    }
                )
            );
        }


        // Filter factory factory
        $di->set(
            'filters.factoryFactory',
            $di->lazy(
                static function () use ($di, $mapperFactory) {
                    /** @var IConfigLoader $config */
                    $config = $di->get('config');

                    /** @var RosettaStone $rosettaStone */
                    $rosettaStone = $di->get('l10n');

                    return new Filter\CompositeFactoryFactory(
                        new Filter\Filters\Assessment\FactoryFactory($mapperFactory, $config, $rosettaStone),
                        new Filter\Filters\Event\FactoryFactory($mapperFactory, $config),
                        new Filter\Filters\Badge\FactoryFactory($mapperFactory, $config),
                        new Filter\Filters\Award\FactoryFactory($mapperFactory, $config),
                    );
                }
            )
        );
        $di->set(
            'filters.assessmentIdFinder',
            static function () use ($mapperFactory) {
                /** @var Filter\Mapper $filterMapper */
                $filterMapper = $mapperFactory->get(Filter\Mapper::class);
                return Filter\Filters\Assessment\IdFinder::getInstance(
                    $filterMapper,
                    $mapperFactory->get(AssessmentMapper::class)
                );
            }
        );


        // User token generator
        $di->set(
            'user.tokenBuilder',
            $di->lazyNew(
                Jwt::class,
                ['configLoader' => $config]
            )
        );


        // Lock factory
        // TODO inject this everywhere a lock is used
        $di->set(
            'lockFactory',
            $di->lazy(function () use ($di) {
                return new \Alaress\Lock\InstanceLockFactory(
                    new LockFactory(new RedisStore($di->get('redis.cache'))),
                    INSTANCE_ID
                );
            })
        );

        // Storage upload/download sanitizer
        $di->set(
            'storage.sanitizer',
            $di->lazy(function () use ($di) {
                /** @var IConfigLoader $config */
                $config = $di->get('config');
                $baseSvgSanitizer = new BaseSvgSanitizer();
                $baseSvgSanitizer->minify(true);
                return new Sanitizer\Composite(
                    new Sanitizer\Svg($baseSvgSanitizer),
                    new Sanitizer\Html(new ScrubAdapter()),
                    new Sanitizer\Exif($config)
                );
            })
        );

        // LTI-related things
        $di->set(
            'lti.tools',
            $di->lazy(function () use ($mapperFactory) {
                return $mapperFactory->get(ToolMapper::class)
                                     ->findByColumn('lti_version', ToolModel::LTI_VERSION_1_3);
            })
        );
        $di->set(
            'lti.keyChains',
            $di->lazy(function () use ($di) {
                /** @var IConfigLoader $config */
                $config = $di->get('config');
                /** @var ToolModel[] $ltiTools */
                $ltiTools = $di->get('lti.tools');
                return (new KeyChainRepositoryFactory($config))->build($ltiTools);
            })
        );
        $di->set(
            'lti.registrations',
            $di->lazy(function () use ($di) {
                /** @var IConfigLoader $config */
                $config = $di->get('config');
                /** @var Router $router */
                $router = $di->get('router');
                /** @var KeyChainRepositoryInterface $keyChains */
                $keyChains = $di->get('lti.keyChains');
                /** @var ToolModel[] $ltiTools */
                $ltiTools = $di->get('lti.tools');
                $registrationRepositoryFactory = new RegistrationRepositoryFactory(
                    $config,
                    $keyChains,
                    $router->generate('ltiPlatform.getOidcAuthenticationRequest'),
                    $router->generate('ltiPlatform.getOauthAccessTokenRequest'),
                    $router->generate('ltiPlatform.getJsonWebKeySet'),
                );
                return $registrationRepositoryFactory->build($ltiTools);
            })
        );
        $di->set(
            'lti.userCodec',
            $di->lazyNew(
                UserCodec::class,
                [
                    'userMapper' => $mapperFactory->getLazy(UserMapper::class),
                ]
            )
        );
        $di->set(
            'lti.userAuthenticator',
            $di->lazyNew(
                UserAuthenticator::class,
                [
                    'userCodec' => $di->lazyGet('lti.userCodec'),
                ]
            )
        );
        $di->set(
            'lti.accessTokenCache',
            $di->lazy(function () use ($di) {
                /** @var Predis\ClientInterface $redis */
                $redis = $di->get('redis.storage');
                /** @var RegistrationRepositoryInterface $registrationRepository */
                $registrationRepository = $di->get('lti.registrations');
                return new ItemPool(
                    $redis,
                    new Codec(new ClientRepository($registrationRepository)),
                    new KeyGenerator('accessToken', INSTANCE_ID),
                );
            })
        );


        // Filesystems
        $di->set(
            'filesystem.mountManager',
            $di->lazy(
                function () use ($di, $config) {
                    /** @var Sanitizer\ISanitizer $sanitizer  */
                    $sanitizer = $di->get('storage.sanitizer');
                    return new Manager(
                        (new FilesystemFactory())->build($config->get('storage_dir')),
                        new \Alaress_Byte_Format(),
                        $sanitizer
                    );
                }
            )
        );
        $di->set(
            'filesystem.storage',
            $di->lazy(
                function () use ($di) {
                    /** @var Manager $mountManager */
                    $mountManager = $di->get('filesystem.mountManager');
                    return $mountManager->getStorageFilesystem();
                }
            )
        );
        $di->set(
            'filesystem.storageProxy',
            $di->lazy(
                function () use ($di) {
                    /** @var FilesystemInterface $storage */
                    $storage = $di->get('filesystem.storage');
                    return new FilesystemProxy($storage, new HashGenerator());
                }
            )
        );
        $di->set(
            'filesystem.temp',
            $di->lazy(
                function () use ($di) {
                    /** @var Manager $mountManager */
                    $mountManager = $di->get('filesystem.mountManager');
                    return $mountManager->getTempFilesystem();
                }
            )
        );
        $di->set(
            'filesystem.imageProcessorFactory',
            $di->lazy(
                function () use ($di) {
                    /** @var FilesystemInterface $storage */
                    $storage = $di->get('filesystem.storage');
                    return new ProcessorFactory($storage, new HashGenerator());
                }
            ),
        );


        return $di;
    }

    /**
     * Return known classes for current session user.
     *
     * Returns empty array if session not available (eg CLI)
     *
     * @param Di\Container $di
     * @return array
     */
    abstract protected function getKnownUserClassesFromSession(Di\Container $di): array;


    /**
     * Consistently register the 'session' and 'session.rememberer' DI services.
     *
     * @param Di\Container $di
     * @return Di\Container
     * @throws Di\Exception\ContainerLocked
     * @throws Di\Exception\ServiceNotObject
     */
    protected function installSessionService(Di\Container $di): Di\Container
    {
        $di->set(
            'session',
            $di->lazy(
                function () use ($di) {
                    /** @var IConfigLoader $config */
                    $config = $di->get('config');
                    /** @var Remember\Remember $rememberer */
                    $rememberer = $di->get('session.rememberer');
                    $factory = new Factory(
                        $_COOKIE,
                        $config,
                        $rememberer,
                        $_SERVER['REQUEST_TIME'],
                    );
                    return $factory->build();
                }
            )
        );

        $di->set(
            'session.rememberer',
            $di->lazy(
                function () use ($di) {
                    /** @var Predis\ClientInterface $redisStorage */
                    $redisStorage = $di->get('redis.storage');
                    return new Remember\Remember(
                        new Remember\Cookie(
                            Remember\Cookie::DEFAULT_COOKIE_NAME,
                            $_COOKIE,
                            session_get_cookie_params()
                        ),
                        new Remember\Redis(INSTANCE_ID, $redisStorage),
                    );
                }
            )
        );

        return $di;
    }

    /**
     * Inits all routes based on the pre-generated route table.
     *
     * If the pre-generated route table doesn't exist or is corrupt: rebuild it
     * and then init the routes.
     */
    protected function initRoutes(): void
    {
        /** @var ModuleCache\Cache $moduleCache */
        $moduleCache = $this->di->get('moduleCache');
        try {
            $routes = $moduleCache->fetchRouteTable();
        } catch (ModuleCache\FetchException $e) {
            /** @var ModuleCache\Rebuilder $moduleCacheRebuilder */
            $moduleCacheRebuilder = $this->di->get('moduleCache.rebuilder');
            /** @var IConfigLoader $config */
            $config = $this->di->get('config');
            $moduleCacheRebuilder->rebuild($config->get(ConfigMapper::NAME_SITE_SCHOOL_CODE));
            $routes = $moduleCache->fetchRouteTable();
        }

        /** @var Router $router */
        $router = $this->di->get('router');
        $router->setRoutes($routes);
    }

    /**
     * Registers modules based on the pre-generated module cache.
     *
     * If the pre-generated module cache doesn't exist or is corrupt: rebuild it
     * and then init the modules.
     */
    protected function initModules(): void
    {
        /** @var ModuleCache\Cache $moduleCache */
        $moduleCache = $this->di->get('moduleCache');
        try {
            $modules = $moduleCache->fetchModuleRegistrations();
        } catch (ModuleCache\FetchException $e) {
            /** @var ModuleCache\Rebuilder $moduleCacheRebuilder */
            $moduleCacheRebuilder = $this->di->get('moduleCache.rebuilder');
            /** @var IConfigLoader $config */
            $config = $this->di->get('config');
            $moduleCacheRebuilder->rebuild($config->get(ConfigMapper::NAME_SITE_SCHOOL_CODE));
            $modules = $moduleCache->fetchModuleRegistrations();
        }
        Registry::setRegistrations($modules);
    }
}
