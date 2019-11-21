<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Application;

use Aura\Di;
use Config\Modules\ModuleConfigInterface;
use Predis;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Filter;
use Schoolbox\Pastoral\AccessLevelCensor;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;
use Throwable;
use RuntimeException;
use Alaress\Authorizer\Rule;
use Alaress\Database\Exception as AlaressDatabaseException;
use Alaress\Database\Factory as AlaressDatabaseFactory;
use Alaress\Module\Loader as ModuleLoader;
use Alaress\Redis;
use Alaress\Session\Session;
use Alaress\Twig\Filter as TwigFilter;
use Alaress\Twig\Loader\ChainLoader;
use Alaress\Twig\Method as TwigMethod;
use Alaress\Web\Request\IsJson;
use Alaress\Web\Request\IsXhr;
use Aura\Router\Router;
use Calendar\Api\ModuleChecker;
use Calendar_Api_Factory;
use Alaress\Session\Factory;
use Alaress\Web\Output;
use Alaress\Web\Router\RouterFactory;
use Alaress_Date_Locale_Config;
use External_ClassInterface;
use External_UserInterface;
use GuzzleHttp;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Schoolbox\Bookmark\Mapper as BookmarkMapper;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Connector\CompositeSync;
use Schoolbox\External\Connector\CustomSync;
use Schoolbox\External\Connector\ICommunityPortal;
use Schoolbox\Instance;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStoneFactory;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Error;
use Schoolbox\Mapper;
use Schoolbox\MapperFactory;
use Schoolbox\Menu\Side\Mapper as SideMenuMapper;
use Schoolbox\Menu\Side\Section\Mapper as SideMenuSectionMapper;
use Schoolbox\Menu\Top\Mapper as TopMenuMapper;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Pastoral\DataSource as PastoralDataSource;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Revision\DataSource as PastoralRevisionDataSource;
use Schoolbox\Pastoral\Revision\Mapper as PastoralRevisionMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Routing\Cache\Redis\WriteCache;
use Schoolbox\Realtime\Schedule\Trigger\Test\MockPropagator;
use Schoolbox\Realtime\Subscription\Redis\UnreadRetriever;
use Schoolbox\Tour\Mapper as TourMapper;
use Schoolbox\Tour\Step\Mapper as StepMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SitePage;
use Twig_Extension_Debug;
use Twig_Extensions_Extension_Text;
use Twig_Loader_Filesystem;
use Twig_SimpleFilter;
use Twig_SimpleFunction;
use Alaress_Date_Format;
use Alaress\Database\Connector\IConnector;
use External_Class;
use External_Timetable;
use External_User;
use Schoolbox\Admin\Navigation\Renderer as AdminNavigationRenderer;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Assessment\Course\Task\CompletionMapper as TaskCompletionMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Database\Factory as SchoolboxDatabaseFactory;
use Schoolbox\External\Connector\Factory as ExternalConnectorFactory;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel\Translator as AssessmentDataModelTranslator;
use Schoolbox\Learning\Assessment\DataModel\Translator\DueWork as DueWorkTranslator;
use Schoolbox\Learning\Assessment\DataModel\Translator\Project as ProjectTranslator;
use Schoolbox\Learning\Assessment\DataModel\Translator\Quiz as QuizTranslator;
use Schoolbox\Learning\Assessment\DataModel\Translator\Task as TaskTranslator;
use Schoolbox\Learning\Assessment\DueWork\Feedback\MapperProxy as DueWorkFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Response\MapperProxy as DueWorkSubmissionMapperProxy;
use Schoolbox\Learning\Assessment\Feedback\DataSource as AssessmentFeedbackDataSource;
use Schoolbox\Learning\Assessment\Feedback\SubSource\DueWork as FeedbackSubSourceDueWork;
use Schoolbox\Learning\Assessment\Feedback\SubSource\Quiz as FeedbackSubSourceQuiz;
use Schoolbox\Learning\Assessment\Feedback\SubSource\Task as FeedbackSubSourceTask;
use Schoolbox\Learning\Assessment\Quiz\Attempt\MapperProxy as QuizAttemptMapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Feedback\MapperProxy as QuizFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\Response\DataSource as AssessmentResponseDataSource;
use Schoolbox\Learning\Assessment\Response\SubSource\DueWork as ResponseSubSourceDueWork;
use Schoolbox\Learning\Assessment\Response\SubSource\Quiz as ResponseSubSourceQuiz;
use Schoolbox\Learning\Assessment\Response\SubSource\Task as ResponseSubSourceTask;
use Schoolbox\Learning\Assessment\Task\Feedback\MapperProxy as TaskFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\Task\Response\MapperProxy as TaskResponseMapperProxy;
use Schoolbox\Learning\Assessment\Transactor as LearningAssessmentTransactor;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\Renderer as LearningRenderer;
use Schoolbox\LTI\Provider\Mapper as LTIProviderMapper;
use Schoolbox\Notif\StorefileLogger as NotifStorefileLogger;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Sync\Grammar\Factory as SyncGrammarFactory;
use Schoolbox\Sync\Log\Mapper as SyncLogMapper;
use Schoolbox\Sync\Provisioner\Provisioner;
use Schoolbox\Sync\Provisioner\UserMapper as SyncUserMapper;
use Schoolbox\Sync\Rule\Mapper as SyncRuleMapper;
use Schoolbox\Sync\Ruleset\Factory as SyncRulesetFactory;
use Twig_Environment;
use Schoolbox\Learning\InlineGrader\Kami;

/**
 * Class Kernel
 * Also known as a FrontController or Mediator.
 *
 * @package Alaress\Application
 */
abstract class Kernel
{
	/** @var Di\Container */
	protected $di;

	/** @var callable */
	protected $shutdown;

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
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\External\Exception
     */
    public function __construct()
    {
        // run the launch sequence
        $this->initialiseExternalConnections();
        $this->registerErrorHandling();
        $this->registerExceptionHandling();
        $this->registerShutdownHandling();
        $this->installDependencies();
        $this->initialiseDebugging();
        $this->checkServerApiEnvironment();
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
	 * @param Di\Container $di
	 * @throws RuntimeException
	 */
	protected function setDependencyInjector(Di\Container $di): void
    {
		// only allow once
		if($this->di === null) {
			$this->di = $di;
		}
		else {
			throw new RuntimeException("Dependency injector has already been set, cannot set again");
		}
	}


	/**
	 * @return Di\Container
	 */
	public function getDependencyInjector() {
		return $this->di;
	}


    /**
     * Convenience method to retrieve service from DI
     *
     * @param string $service
     * @throws RuntimeException if the DI has not been initialised
     * @throws Di\Exception\ServiceNotFound
     * @return mixed
     */
    public function get($service)
    {
        $di = $this->getDependencyInjector();

        if ($di instanceof Di\Container) {
            return $di->get($service);
        }

        throw new RuntimeException("Dependency injector has not been set, cannot retrieve service");
    }


	/**
	 * @param callable $callable
	 */
	public function registerCustomShutdown($callable) {
		if(is_callable($callable)) {
			$this->shutdown = $callable;
		}
	}

	/**
	 * @param callable $callable
	 */
	public function registerExceptionCallback($callable) {
		if(is_callable($callable)) {
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
     * @return void
     * @throws Di\Exception
     * @throws Di\Exception\ContainerLocked
     * @throws Di\Exception\ServiceNotFound
     * @throws Di\Exception\ServiceNotObject
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function installDependencies(): void
    {
        // Set up the dependency injector
        $di = new Di\Container(new Di\Factory());

        // Config loader
        $di->set('config', $di->lazyNew(ConfigLoader::class));

        Redis::getInstance()->connect($di->get('config')->get('redis_url'), null, INSTANCE_ID);
        $di->set('redis', Redis::getClientInstance());

        $di->set('amqp', $di->lazy(function () {
            return Connector::getInstance();
        }));

        $di->set('instance', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $conf */
            $conf = $di->get('config');
            return new Instance(
                INSTANCE_ID,
                $conf->get('site_location'),
                $conf->get('site_hostname'),
                $conf->get('site_domain'),
                $conf->get('site_version'),
                $conf->get('site_type'),
                $conf->get('site_school_code')
            );
        }));

        // install mapper DI
        // (yes I know this is icky, but it will have to do until mapper construction is set up with DI properly)
        Mapper::di($di);

        // Set correct timezone
        date_default_timezone_set($timezone = $di->get('config')->get('date_timezone'));

        // Notification factory
        $di->set('notif',
            $di->lazy(
                function() {
                    // replace this with $di->lazyGetCall() once we upgrade Aura.Di
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
            function() use ($di) {
                /** @var SchoolboxDatabaseFactory $factory */
                $factory = $di->get('databaseFactory');
                return $factory->getInternalDatabase();
            }
        );

        // External SIS database connection(s)
        $di->set(
            'databaseExternal',
            $di->lazy(
                function() use ($di) {
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

        // Session
        $di->set(
            'session',
            $di->lazy(
                function () use ($di) {
                    /** @var IConfigLoader $config */
                    $config = $di->get('config');

                    /** @var Predis\ClientInterface $redis */
                    $redis = $di->get('redis');
                    $factory = new Factory(
                        $_COOKIE,
                        $config,
                        $redis,
                        $_SERVER['REQUEST_TIME']
                    );
                    return $factory->build();
                }
            )
        );

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

        $di->set(
            'l10n',
            $di->lazy(
                function () use ($di) {
                    $stoneFactory = new RosettaStoneFactory();
                    return $stoneFactory->build($di->get('mapperFactory')->get(L10nMapper::class));
                }
            )
        );

        // Enable mappers to be injectable, but instantiated only when required
        $mapperFactory = new MapperFactory($di);
        $di->set('mapperFactory', $mapperFactory);

        // Preload of route parameters, and its module loader
        $di->params['Alaress\\Web\\Router\\PathMatcher']['moduleLoader'] = $di->lazyNew(
            ModuleLoader::class,
            ['injector' => $di, 'moduleRegex' => '/^(?P<module>[^\\.]+)\\./']
        );
        $di->set('preloader', $di->lazyNew('Alaress\\Web\\Router\\PathMatcher'));

        // Authorization
        $di->set('authorizer', $di->lazyNew('Alaress\\Authorizer\\Authorizer'));

        // Web request/response factory
        $di->params['Aura\\Web\\WebFactory'] = array('globals' => $GLOBALS);
        $di->set('webFactory', $di->lazyNew('Aura\\Web\\WebFactory'));

        // inject request and response into controller
        // (I may need to mangle requests in pre-processing though)
        $di->setters['Alaress\\Web\\Controller\\Base'] = array(
            'setRequest'    => $di->lazy(
                function () use ($di) {
                    return $di->get('webFactory')->newRequest();
                }
            ),
            'setResponse'   => $di->lazy(
                function () use ($di) {
                    return $di->get('webFactory')->newResponse();
                }
            ),
            'setSession'    => $di->lazyGet('session'),
            'setRouter'     => $di->lazyGet('router'),
            'setAuthorizer' => $di->lazyGet('authorizer'),
            'setDi'         => $di,
            'setExceptionGenerator' => $di->get('mapperFactory')->getLazy(Error\Mapper::class),
            'setSentry'     => $di->lazy(
            // Use lazy function to insulate from service registration sequencing
                function () use ($di) {
                    return $di->has('sentry') ? $di->get('sentry') : null;
                }
            ),
            'setIsJsonSpec' => $di->lazyNew(IsJson::class),
            'setIsXhrSpec'  => $di->lazyNew(IsXhr::class)
        );

        // inject web params (yep, even Sitepage!)
        $di->set('layoutEngine', $di->lazy(function() use ($di) {
            return new SitePage(false, $di);
        }));

        $di->set('sitePage',     $di->lazy(function() use ($di) {
            return new SitePage(true, $di);
        }));


        $di->set(
            'commsNewsArticleResolver',
            $di->lazy(
                function () use ($di) {
                    /**
                     * @var IConnector                            $db
                     * @var External_UserInterface                $externalUser
                     * @var External_ClassInterface               $externalClass
                     * @var News\Audience\Segment\StrategyFactory $strategyFactory
                     * @var MapperFactory                         $mapperFactory
                     * @var UserAccessMapper                      $userAccessMapper
                     * @var Folder\Mapper                         $folderMapper
                     * @var UserMapper                            $userMapper
                     */
                    $db               = $di->get('databaseInternal');
                    $externalUser     = $di->get('externalUser');
                    $externalClass    = $di->get('externalClass');
                    $strategyFactory  = $di->get('commsNewsAudienceStrategyFactory');
                    $mapperFactory    = $di->get('mapperFactory');
                    $userAccessMapper = $mapperFactory->get(UserAccessMapper::class);
                    $folderMapper     = $mapperFactory->get(Folder\Mapper::class);
                    $userMapper       = $mapperFactory->get(UserMapper::class);
                    $articleResolver  = new News\Audience\ArticleResolver(
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
                        $strategyFactory->makeAllSimpleStrategies()
                    );

                    return $articleResolver;
                }
            )
        );

        $di->set(
            'commsNewsUserResolver',
            $di->lazy(
                function() use ($di) {
                    /**
                     * @var IConnector              $db
                     * @var External_UserInterface  $externalUser
                     * @var External_ClassInterface $externalClass
                     */
                    $db              = $di->get('databaseInternal');
                    $externalUser    = $di->get('externalUser');
                    $externalClass   = $di->get('externalClass');
                    $mapperFactory   = $di->get('mapperFactory');
                    $folderMapper    = $mapperFactory->get(Folder\Mapper::class);
                    $userResolver = new News\Audience\UserResolver(
                        StrategyFactory::getValidNullEntities(),
                        $db,
                        $externalClass,
                        new News\Audience\RelationshipFilterer(
                            $db,
                            $di->get('mapperFactory')->get(UserMapper::class),
                            $externalUser,
                            $di->get('mapperFactory')->get(Folder\Mapper::class),
                            $externalClass,
                            $di->get('mapperFactory')->get(UserAccessMapper::class)
                        ),
                        $di->get('mapperFactory')->get(News\Audience\Mapper::class),
                        $folderMapper
                    );

                    return $userResolver;
                }
            )
        );

        $di->set(
            'commsNewsAudienceStrategyFactory',
            $di->lazy(
                function () use ($di) {
                    /**
                     * @var MapperFactory          $mapperFactory
                     * @var UserAccessMapper       $userAccessMapper
                     * @var Folder\Mapper          $folderMapper
                     * @var RoleAccessMapper       $roleAccessMapper
                     * @var Access                 $access
                     * @var IConfigLoader          $config
                     * @var External_UserInterface $externalUser
                     * @var IConnector             $db
                     * @var Session                $session
                     * @var User                   $user
                     */
                    $mapperFactory    = $di->get('mapperFactory');
                    $userAccessMapper = $mapperFactory->get(UserAccessMapper::class);
                    $folderMapper     = $mapperFactory->get(Folder\Mapper::class);
                    $roleAccessMapper = $mapperFactory->get(RoleAccessMapper::class);
                    $access           = $mapperFactory->get(Access::class);
                    $config           = $di->get('config');
                    $externalUser     = $di->get('externalUser');
                    $db               = $di->get('databaseInternal');
                    $session          = $di->get('session');
                    $user             = $session->get('user');

                    // Get known user-to-class mapping from the session and inject into factory
                    $knownUserClasses = [];

                    // Self case
                    $subjects = $session->get('subjects');
                    if ($subjects) {
                        $knownUserClasses[$user->getId()] = $subjects;
                    }
                    // Parent case
                    foreach ($session->get('children', []) as $childData) {
                        $knownUserClasses[$childData['id']] = $childData['subjects'];
                    }
                    return new News\Audience\Segment\StrategyFactory(
                        $userAccessMapper,
                        $config->get(ConfigMapper::_STAFF_CAN_POST_NEWS),
                        $config->get(ConfigMapper::_GLOBAL_MODERATION),
                        $config->get(ConfigMapper::_AUDIENCE_TO_FOLDER_TEACHERS),
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
        $di->setters[Output\Html::class]['setTemplateEngine'] = $di->lazyGet('templateEngine');
        $di->setters[Output\Stream::class]['setTemplateEngine'] = $di->lazyGet('templateEngine');
        $di->set('webOutput:base', $di->lazyNew(Output\Base::class));
        $di->set('webOutput:html', $di->lazy(function() use ($di, $mapperFactory) {
            /** @var IConfigLoader $config */
            /** @var Session $session */
            /** @var Router $router */
            /** @var Predis\ClientInterface $redis */
            /** @var TourMapper $tourMapper */
            /** @var StepMapper $tourStepMapper */
            /** @var Twig_Environment $templateEngine */
            /** @var TopMenuMapper $topMenuMapper */
            /** @var SideMenuSectionMapper $sideMenuSectionMapper */
            /** @var SideMenuMapper $sideMenuMapper */
            /** @var BookmarkMapper $bookMarkMapper */
            $config  = $di->get('config');
            $session = $di->get('session');
            $tourMapper = $mapperFactory->get(TourMapper::class);
            $tourStepMapper = $mapperFactory->get(StepMapper::class);
            $tourList = new \Schoolbox\Tour\Lists($tourMapper, $tourStepMapper);
            $redis   = $di->get('redis');
            $output  = new Output\ThemedHtml();
            $templateEngine = $di->get('templateEngine');
            $topMenuMapper = $mapperFactory->get(TopMenuMapper::class);
            $sideMenuSectionMapper = $mapperFactory->get(SideMenuSectionMapper::class);
            $sideMenuMapper = $mapperFactory->get(SideMenuMapper::class);
            $bookMarkMapper = $mapperFactory->get(BookmarkMapper::class);
            $output
                ->setTemplateEngine($templateEngine)
                ->addDataGenerator(
                    'ws',
                    new Output\DataGenerator\Websocket(
                        $config,
                        $session->getAuthUser(),
                        new UnreadRetriever($redis),
                        $_SERVER,
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
        $di->set('webOutput:nochrome', $di->lazyNew(Output\Html::class));
        $di->set('webOutput:json', $di->lazyNew(Output\Json::class));
        $di->set('webOutput:file', $di->lazyNew(Output\File::class));
        $di->set('webOutput:stream', $di->lazyNew(Output\Stream::class));
        $di->set('webOutput:noContent', $di->lazyNew(Output\NoContent::class));
        $di->set('webOutput:xml', $di->lazyNew(Output\Xml::class));

        // Template engine and functions
        // Template loader: sets up link to templates without a namespace.
        $di->set(
            'templateEngine:loader',
            $di->lazy(
                function () use ($di) {
                    $config      = $di->get('config');
                    $chainLoader = new ChainLoader(new ModuleLoader($di, '[^@(?P<module>[^/]+)/]'), new \Twig\Loader\ChainLoader());
                    $baseLoader  = new Twig_Loader_Filesystem(array());
                    // Paths: prefer templates/{site_location} over templates/core
                    $basePath     = $config->get('siteRoot').'/templates/';
                    $overridePath = $basePath.$config->get('site_location');
                    $corePath     = $basePath.'core';
                    if (is_dir($overridePath)) {
                        $baseLoader->addPath($overridePath);
                    }
                    $baseLoader->addPath($corePath);
                    // Core path: always uses templates/core, even if templates/{site_location} exists
                    // Used as a base for default path templates to inherit from.
                    $baseLoader->addPath($corePath, 'core');
                    $chainLoader->addLoader($baseLoader);
                    return $chainLoader;
                }
            )
        );

        $di->params[TwigMethod\RequireModule::class]['di'] = $di;
        $di->set('templateEngine:requireModule', $di->lazyNew(TwigMethod\RequireModule::class));

        $di->setters[TwigMethod\RouteLookup::class]['setRouter'] = $di->lazyGet('router');
        $di->set('templateEngine:routeLookup', $di->lazyNew(TwigMethod\RouteLookup::class));

        $di->setters[TwigMethod\IsRouteAuthorized::class]['setRouter']     = $di->lazyGet('router');
        $di->setters[TwigMethod\IsRouteAuthorized::class]['setAuthorizer'] = $di->lazyGet('authorizer');
        $di->setters[TwigMethod\IsRouteAuthorized::class]['setSession']    = $di->lazyGet('session');
        $di->set('templateEngine:isRouteAuthorized', $di->lazyNew(TwigMethod\IsRouteAuthorized::class));

        $di->params[TwigMethod\RouteLookupIfAuthorized::class]['routeLookup'] = $di->lazyGet('templateEngine:routeLookup');
        $di->params[TwigMethod\RouteLookupIfAuthorized::class]['isRouteAuthorized'] = $di->lazyGet('templateEngine:isRouteAuthorized');
        $di->set('templateEngine:routeLookupIfAuthorized', $di->lazyNew(TwigMethod\RouteLookupIfAuthorized::class));

        $di->set(
            'templateEngine',
            $di->lazy(
                function () use ($di) {
                    /** @var IConfigLoader $config */
                    $config = $di->get('config');

                    $options = [
                        'debug' => $config->get('debug_templates'),
                        'strict_variables' => $config->get('strict_templates'),
                    ];

                    // do not cache when in debug mode
                    if (!$config->get('debug_mode')) {
                        $options['cache'] = $config->get('stream_dir') . '/templates/';
                    } else {
                        if (class_exists('\Alaress\Web\Template\DebugTwig')) {
                            $twigClass = '\Alaress\Web\Template\DebugTwig';
                        } else {
                            $twigClass = 'Twig_Template';
                        }
                        $options['base_template_class'] = $twigClass;
                    }

                    $twig = new Twig_Environment($di->get('templateEngine:loader'), $options);

//                    if($this->debugEnabled) {
//                        $twig = new TraceableTwigEnvironment($twig);
//                        DebugBar::getInstance()->addCollector(new TwigCollector($twig));
//                    }

                    // HTML Purifier filter - known to output safe HTML

                    $twig->addFilter(
                        new Twig_SimpleFilter(
                            'purify',
                            array('Scrub', 'html'),
                            array('is_safe' => array('html'))
                        )
                    );

                    // HTML2Text filter
                    $twig->addFilter(
                        new Twig_SimpleFilter(
                            'html2text',
                            array('Scrub', 'html2text')
                        )
                    );

                    // Cut sentence filter
                    $twig->addFilter(
                        new Twig_SimpleFilter(
                            'cutSentence',
                            array('TextFormatter', 'cutSentence')
                        )
                    );

                    // Bytes to string filter
                    $twig->addFilter(
                        new Twig_SimpleFilter(
                            'bytes',
                            array('Alaress_Byte_Format', 'bytesToString')
                        )
                    );

                    $twig->addFilter(
                        new Twig_SimpleFilter(
                            'column',
                            [ Collection::class, 'array_column' ]
                        )
                    );

                    // MomentJsFormat filter
                    $twig->addFilter(
                        new Twig_SimpleFilter(
                            'momentJsFormat',
                            [ \Alaress_Date_Format_Converter::class, 'toMomentJs' ]
                        )
                    );

                    // Case insenstive replace function
                    $twig->addFilter(
                        new Twig_SimpleFilter(
                            'ireplace',
                            function($string, array $tokens = []) {
                                return str_ireplace(array_keys($tokens), array_values($tokens), $string);
                            }
                        )
                    );

                    // Relative date function
                    $twig->addFilter(new Twig_SimpleFilter('relativeDate', new TwigFilter\RelativeDate()));

                    // Global access to config object and localisation
                    // TODO capitalize globals
                    $twig->addGlobal('config', $config);
                    $twig->addGlobal('locale',  $di->get('l10n'));
                    $dateFormats = new Alaress_Date_Locale_Config();
                    $dateFormats->setConfig($config);
                    $twig->addGlobal('dateFormats', $dateFormats);
                    $twig->addGlobal('NOW', Alaress_Date_Format::now());
                    $twig->addGlobal('USER', $_SESSION['user']);

                    // Global access to returnUrl vars
                    $encodedUrl = Redirect::encode($_GET["page"]);
                    $twig->addGlobal(
                        'RETURN_URL',
                        [
                            'name'     => Redirect::RETURN_URL,
                            'current'  => $encodedUrl,
                            'previous' => $_REQUEST[Redirect::RETURN_URL] ?? $encodedUrl,
                        ]
                    );

                    // Define modules that are required by a template
                    $twig->addFunction(
                        new Twig_SimpleFunction('requireModule', $di->get('templateEngine:requireModule'))
                    );

                    // Add the route lookup and authorization methods
                    $twig->addFunction(
                        new Twig_SimpleFunction(
                            'routeLookup',
                            array($di->get('templateEngine:routeLookup'), 'execute')
                        )
                    );
                    $twig->addFunction(
                        new Twig_SimpleFunction(
                            'isRouteAuthorized',
                            array($di->get('templateEngine:isRouteAuthorized'), 'execute')
                        )
                    );

                    $twig->addFunction(
                        new Twig_SimpleFunction(
                            'getGuid',
                            function() {
                                mt_srand((double)microtime() * 10000);
                                return strtoupper(md5(uniqid((string)rand(), true)));
                            }
                        )
                    );

                    $twig->addFunction(new Twig_SimpleFunction('routeLookupIfAuthorized', $di->get('templateEngine:routeLookupIfAuthorized')));

                    $twig->addFunction(
                        new Twig_SimpleFunction(
                            'generateHiddenInputs',
                            new TwigMethod\GenerateHiddenInputs(),
                            ['is_safe' => ['html']]
                        )
                    );

                    // Add extensions after everything else.
                    // According to https://twig.symfony.com/doc/1.x/deprecated.html#globals
                    // globals must be loaded before extensions.
                    $twig->addExtension(new Twig_Extension_Debug());
                    $twig->addExtension(new Twig_Extensions_Extension_Text());
                    $twig->addExtension(new AdminNavigationRenderer());
                    $twig->addExtension(new LearningRenderer());

                    return $twig;
                }
            )
        );

        // Paginator internal object
        $di->setters['Alaress\\Paginator\\UrlPaginator']['setPaginator'] = $di->lazyNew('Alaress\\Paginator\\Paginator');

        // Build a user provisioner
        // TODO unify this definition and the one in Daemon\Bootstrap
        $di->set('ups.logger',
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
                    'externalUserSource' => $di->lazy(function() use ($di) {
                        /** @var IConfigLoader $config */
                        $config = $di->get('config');

                        /** @var array|IConnector $databaseExternal */
                        $databaseExternal = $di->get('databaseExternal');

                        $customSync = new CustomSync($config, $databaseExternal);

                        $defaultSync = (new ExternalConnectorFactory())->build($config, $databaseExternal, ExternalConnectorFactory::EXTERNAL_SYNC);

                        return (new CompositeSync())
                            ->addSubSync($customSync)
                            ->addSubSync($defaultSync);
                    }),
                    'userMapper'       => $di->lazyNew(SyncUserMapper::class),
                    'logger'           => $di->lazyGet('ups.logger'),
                    'logMapper'        => $di->lazyNew(SyncLogMapper::class),
                    'userAccessMapper' => $di->lazyNew(UserAccessMapper::class),
                ]
            )
        );

        // Register External_User service
        $di->set('externalUser', $di->lazyNew(External_User::class));

        // Register External_Timetable service
        $di->set('externalTimetable', $di->lazyNew(External_Timetable::class));

        $di->set('externalCommunityPortal', $di->lazy(function() use ($di) {
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
            $di->lazy(function () use ($di) {
                $translator = new AssessmentDataModelTranslator(
                    new MarkSchemeFactory(),
                    $di->get('mapperFactory')->get(AssessmentMapper::class),
                    AssessmentDataModelTranslator::MAX_PROJECT_LEVELS_DEFAULT
                );
                $translator
                    ->addSubTranslator(new TaskTranslator())
                    ->addSubTranslator(new DueWorkTranslator())
                    ->addSubTranslator(new QuizTranslator())
                    ->addSubTranslator(new ProjectTranslator($translator))
                ;
                return $translator;
            })
        );

        // Register assessment data source
        $di->params[AssessmentDataSource::class] = [
            'baseMapper'            => $mapperFactory->getLazy(AssessmentMapper::class),
            'translator'            => $di->lazyGet('assessmentTranslator'),
            'participantDataSource' => $di->lazyGet('participantDataSource'),
        ];
        $di->set('assessmentDataSource', $di->lazyNew(AssessmentDataSource::class));

        // Register participant data source (with responses and feedback)
        $di->params[ParticipantDataSource::class] = [
            'externalDb'         => $di->lazyGet('externalClass'),
            'userAccessMapper'   => $mapperFactory->getLazy(UserAccessMapper::class),
            'userMapper'         => $mapperFactory->getLazy(UserMapper::class),
            'responseDataSource' => $di->lazyGet('responseDataSource'),
            'feedbackDataSource' => $di->lazyGet('feedbackDataSource'),
            'planMapper'         => $mapperFactory->getLazy(\Schoolbox\Assessment\Participant\Plan\Mapper::class)
        ];
        $di->set('participantDataSource', $di->lazyNew(ParticipantDataSource::class));


        /* DUE WORK SOURCES */
        $di->set('submissionDataSource', $di->lazyNew(DueWorkSubmissionMapperProxy::class));
        $di->params[DueWorkSubmissionMapperProxy::class]['mapper'] = $mapperFactory->getLazy(SubmissionMapper::class);

        $di->set('returnDataSource', $di->lazyNew(DueWorkFeedbackMapperProxy::class));
        $di->params[DueWorkFeedbackMapperProxy::class]['mapper'] = $mapperFactory->getLazy(ReturnedWorkMapper::class);


        /* QUIZ SOURCES */
        $di->set('attemptDataSource', $di->lazyNew(QuizAttemptMapperProxy::class));
        $di->params[QuizAttemptMapperProxy::class]['mapper'] = $mapperFactory->getLazy(QuizAttemptMapper::class);

        $di->set('reviewDataSource', $di->lazyNew(QuizFeedbackMapperProxy::class));
        $di->params[QuizFeedbackMapperProxy::class]['mapper'] = $mapperFactory->getLazy(QuizAttemptMapper::class);


        /* TASK SOURCES */
        $di->set('completionDataSource', $di->lazyNew(TaskResponseMapperProxy::class));
        $di->params[TaskResponseMapperProxy::class]['mapper'] = $mapperFactory->getLazy(TaskCompletionMapper::class);

        $di->set('ratificationDataSource', $di->lazyNew(TaskFeedbackMapperProxy::class));
        $di->params[TaskFeedbackMapperProxy::class]['mapper'] = $mapperFactory->getLazy(TaskCompletionMapper::class);


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

                    return (new AssessmentResponseDataSource())
                        ->addSubSource(new ResponseSubSourceDueWork($submissionDataSource))
                        ->addSubSource(new ResponseSubSourceTask($completionDataSource))
                        ->addSubSource(new ResponseSubSourceQuiz($attemptDataSource));
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

                    return (new AssessmentFeedbackDataSource())
                        ->addSubSource(new FeedbackSubSourceDueWork($returnDataSource))
                        ->addSubSource(new FeedbackSubSourceTask($ratificationDataSource))
                        ->addSubSource(new FeedbackSubSourceQuiz($reviewDataSource));
                }
            )
        );

        $di->params[LearningAssessmentTransactor::class] = [
            'folderMapper'                  => $di->get('mapperFactory')->getLazy(Folder\Mapper::class),
            'unitImplementationMapperProxy' => $di->lazyGet('unitImplementationDataSource'),
            'unitSpecificationMapperProxy'  => $di->lazyGet('unitSpecificationDataSource'),
            'assessmentMapperProxy'         => $di->lazyGet('assessmentDataSource'),
            'router'                        => $di->lazyGet('router'),
        ];

        $di->set('transactor', $di->lazyNew(LearningAssessmentTransactor::class));

        // Register default grade group
        $di->set(
            'defaultGradeGroup',
            $di->lazy(
                static function () use ($di, $mapperFactory) {
                    /** @var IConfigLoader $config */
                    $config              = $di->get('config');
                    $defaultGradeGroupId = $config->get('default_grade_group');
                    if ($defaultGradeGroupId) {
                        // If configured, return the configured grade group, error out if not found
                        return $mapperFactory->get(GradeGroup\Mapper::class)->findById($defaultGradeGroupId);
                    }
                    // Not set, return empty model to satisfy method signatures (graceful degradation)
                    return new GradeGroup\Model();
                }
            )
        );

        $di->set('kamiConnector', $di->lazyNew(Kami\Connector::class, [
            'client'         => $di->lazyNew(GuzzleHttp\Client::class),
            'apiKey'         => $di->get('config')->get('kami_api_key'),
            'siteSchoolCode' => $di->get('config')->get('site_school_code'),
        ]));

        // injectable module auth rules
        $di->set('rule:isLoggedIn',  $di->lazyNew(Rule\IsLoggedIn::class));
        $di->set('rule:isSuperuser', $di->lazyNew(Rule\IsSuperuser::class));
        $di->set('rule:isStaff',     $di->lazyNew(Rule\HasRole::class, [ 'role' => Role::STAFF   ]));
        $di->set('rule:isStudent',   $di->lazyNew(Rule\HasRole::class, [ 'role' => Role::STUDENT ]));
        $di->set('rule:isParent',    $di->lazyNew(Rule\HasRole::class, [ 'role' => Role::PARENT  ]));

        // composite rules
        $di->set('rule:isStaffOrSuper',   $di->lazy(function () use ($di) { return new Rule\Any($di->get('rule:isSuperuser'), $di->get('rule:isStaff')); }));
        $di->set('rule:isStaffOrStudent', $di->lazy(function () use ($di) { return new Rule\Any($di->get('rule:isStaff'),     $di->get('rule:isStudent')); }));


        // Calendar API factory - requires an assessment data source to build some types
        $di->set(
            'calendarApiFactory',
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
                    return new Calendar_Api_Factory($assessmentDataSource, new ModuleChecker($config), $config, $databaseExternal);
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

        // notificaton routing cache

        $di->params[\Schoolbox\Notif\Queue\Bindings\Generator::class] = [
            'xUserHandler'      => $di->lazyGet('externalUser'),
            'userMapper'        => $mapperFactory->getLazy(UserMapper::class),
            'roleMapper'        => $mapperFactory->getLazy(\Schoolbox\RoleMapper::class),
            'userAccessMapper'  => $mapperFactory->getLazy(UserAccessMapper::class),
            'categoryMapper'    => $mapperFactory->getLazy(Folder\Mapper::class)
        ];

        $di->params[\Schoolbox\Realtime\Routing\Subscription\Loader\Factory::class] = [
            'redis' => $di->lazyGet('redis'),
            'log'   => $di->lazyValue('logger')
        ];

        $di->setters[\Schoolbox\Realtime\Routing\Subscription\Loader\Factory::class] = [
            'setUserMapper' => $mapperFactory->getLazy(UserMapper::class),
            'setGenerator'  => $di->lazyNew(\Schoolbox\Notif\Queue\Bindings\Generator::class)
        ];

        $di->set('subscriptionCache', $di->lazyNew(WriteCache::class, [
            'redis' => $di->lazyGet('redis'),
            'log'   => $di->lazyValue('logger'),
            'loaderFactory' => $di->lazyNew(\Schoolbox\Realtime\Routing\Subscription\Loader\Factory::class)
        ]));



        // install sentry
        $sentryToken = \defined('SENTRY_TOKEN') ? SENTRY_TOKEN : '';
        $sentryProject = \defined('SENTRY_PROJECT') ? SENTRY_PROJECT : '';
        $sentryClient = null;
        if (\strlen($sentryToken) && \strlen($sentryProject)) {
            try {
                /** @var \Schoolbox\Instance $sb */
                $sb = $di->get('instance');
                // fall back to site_location if site_school_code not set
                $options = [
                    'environment' => $sb->getType(),
                    'tags' => [
                        'php_version' => PHP_VERSION,
                        'site_version' => $sb->getVersion(),
                        'site_school_code' => $sb->getSchoolCode() ?: $sb->getLocation()
                    ]
                ];
                $sentryClient = new \Raven_Client("https://{$sentryToken}@sentry.io/{$sentryProject}", $options);
                $sentryClient->install();
            } catch (\Raven_Exception $e) {
                // do nothing; sentry is not installed
            } catch (Di\Exception\ServiceNotFound $e) {
                // do nothing; sentry is not installed
            }

            // add sentry to the DI
            $di->set('sentry', $sentryClient);
        }


        // Filter factory factory
        $di->set(
            'filters.factoryFactory',
            $di->lazy(
                static function () use ($di, $mapperFactory) {
                    return new Filter\CompositeFactoryFactory(
                        new Filter\Filters\Assessment\FactoryFactory(
                            $mapperFactory,
                            $di->get('l10n')
                        ),
                        new Filter\Filters\Event\FactoryFactory($mapperFactory)
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


        // load dependencies into the kernel itself
        $this->setDependencyInjector($di);
    }


    /**
     * Globs over the Modules directory and initialises them so their routes can be used
     *
     * @throws Di\Exception\ServiceNotFound
     */
    protected function initModules(): void
    {
        // Initialise module configuration
        require_once SITE_root . '/conf/modules/ModuleConfigInterface.php';
        require_once SITE_root . '/conf/ModuleLoader.php';

        $customModuleLocation = sprintf(
            '/conf/modules/Custom/%s/',
            ucfirst($this->getDependencyInjector()->get('config')->get('site_location'))
        );
        $modulePaths = array_merge(
            glob(SITE_root . '/conf/modules/*.php'),
            glob(SITE_root . $customModuleLocation . '*.php')
        );

        foreach ($modulePaths as $routePath) {
            require_once $routePath;

            // Get a route name out of this path
            $path = preg_replace('/^'.preg_quote(SITE_root.'/conf/modules/', '/').'/', '', $routePath);
            $path = preg_replace('/\\.php$/', '', $path);
            $path = preg_replace('/\\//', '\\', $path);

            $class = 'Config\\Modules\\' . $path;

            if(class_exists($class, false)) {
                /** @var ModuleConfigInterface $obj */
                $obj = new $class();
                $obj->configure($this);
            }
        }
    }
}
