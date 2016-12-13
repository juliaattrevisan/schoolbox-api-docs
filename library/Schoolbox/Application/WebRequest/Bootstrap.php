<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Application\WebRequest;


use Alaress\Application\Boot\BootstrapInterface;
use Alaress\Authorizer\Rule;
use Alaress\DebugBar\DataCollector\XhprofCollector;
use Alaress\DebugBar\Instance as DebugBar;
use Alaress\Module\Loader as ModuleLoader;
use Alaress\Twig\Loader\ChainLoader;
use Alaress\Twig\Method as TwigMethod;
use DebugBar\Bridge\Twig\TraceableTwigEnvironment;
use DebugBar\Bridge\Twig\TwigCollector;
use Alaress\Redis;
use Alaress\Session\Factory;
use Alaress\Session\Session;
use Alaress\Web\Output;
use Alaress\Web\Router\RouterFactory;
use Alaress_Date_Locale_Config;
use Aura\Di;
use Autoloader;
use Config;
use Database;
use DeprecatedDatabase;
use PDO;
use Schoolbox\Application\WebRequest;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeGroupMapper;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\L10n\RosettaStone;

// Learning related uses
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;


use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Course\DataModel\Implementation\Translator as CourseImplTranslator;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplMapper;
use Schoolbox\Learning\Course\ImplementationMapperProxy as CourseImplMapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Translator as UnitImplTranslator;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplMapper;
use Schoolbox\Learning\Course\Unit\ImplementationMapperProxy as UnitImplMapperProxy;

use Schoolbox\Learning\Course\DataModel\Specification\Translator as CourseSpecTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecMapper;
use Schoolbox\Learning\Course\SpecificationMapperProxy as CourseSpecMapperProxy;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Translator as UnitSpecTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecMapper;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecMapperProxy;

use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;

use Schoolbox\Error;
use Schoolbox\MapperFactory;
use Schoolbox\Notif\SilentNotif;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Scrub;
use Twig_Extension_Debug;
use Twig_Extensions_Extension_Text;
use Twig_Loader_Filesystem;
use Twig_SimpleFilter;
use Twig_SimpleFunction;

class Bootstrap implements BootstrapInterface {

    /** @var bool */
    protected $debugEnabled = false;

    /** @var Kernel */
    protected $kernel;

    /**
     * @param Kernel $kernel
     */
    public function __construct($kernel) {
        $this->kernel = $kernel;
    }


    /**
     * Load the core configuration file(s)
     *
     * @return void
     */
    public function loadConfiguration() {
        require_once __DIR__ . '/../../../../www/conf/conf.php';
        require_once SITE_root . '/www/include/stdfuncs.php';
        require_once SITE_root . '/library/constants.php';
    }


    /**
     * Set the include path
     *
     * @return void
     */
    public function setIncludePath() {
        set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());
    }


    /**
     * Register any autoloading required
     *
     * @return void
     */
    public function registerAutoLoading() {
        require_once 'library/Autoloader.php';
        Autoloader::initialise();
    }


    /**
     * Initialise debugging
     *
     * @return void
     */
    public function initialiseDebugging() {
        // Set up the debug bar, and add listeners for various parts of the debug bar
        // (database calls, XHProf)
        // Should go in a DI container instead of a static class.
        if($this->debugEnabled = DebugBar::isEnabled()) {
            DebugBar::init();
        }

        // Install into database
        if($this->debugEnabled) {
            $debug_bar = DebugBar::getInstance();

            // Throw away existing connection and reconnect with persistence off
            $database = Database::getInstance();
            $database->connect(SQL_dsn, SQL_user, SQL_passwd, ['debug' => true,]);
            $debug_bar->addCollector($database->getCollector());
        }

        // XHProf on the debug bar.
        if($this->debugEnabled && Config::get('debug_xhprof') && extension_loaded('xhprof')) {
            DebugBar::getInstance()->addCollector(new XhprofCollector());
        }
    }


    /**
     * Register any error handling required
     *
     * @return void
     */
    public function registerErrorHandling() {
        // noop. WebRequest does not use custom error handling
        set_error_handler(function ($code, $message, $file, $line) {
            $this->shutdownSafely([
                'type' => $code,
                'message' => $message,
                'file' => $file,
                'line' => $line
            ], debug_backtrace());
            return true;
        }, E_USER_ERROR | E_ERROR | E_CORE_ERROR | E_RECOVERABLE_ERROR);
    }


    /**
     * Register any out-the-top exception handling required
     *
     * @return void
     */
    public function registerExceptionHandling() {
        set_exception_handler([$this, 'shutdownSafely']);
    }


    /**
     * Register any shutdown handling required
     *
     * @return void
     */
    public function registerShutdownHandling() {
        ShutdownHandler::instance()->registerFunction([$this, 'shutdownSafely']);
    }


    /**
     * Initialise connections to databases, etc.
     *
     * @return void
     */
    public function initialiseExternalConnections() {
        $database = Database::getInstance();
        // Catch-22:
        // I don't know if debug is enabled until I connect to the database.
        // The only solution is to allow the config query to run and then throw
        // away my connection, and create a fresh one with debug enabled.
        $database->connect(SQL_dsn, SQL_user, SQL_passwd);

        // Redis connection configuration
        Redis::getInstance()->connect(ConfigMapper::getInstance()->get('redis_url'), null, INSTANCE_ID);
    }


    /**
     * Install any internal dependencies into the kernel (routing, etc.)
     *
     * @return void
     */
    public function installDependencies() {
        // Set correct timezone
        date_default_timezone_set($timezone = Config::get('date_timezone'));

        // Set up DB timezones here too
        Database::getInstance()->setTimezone($timezone);
        DeprecatedDatabase::getInstance()->setTimezone($timezone);

        // Set up the dependency injector

        // Do dependency injection stuff
        // How do I get this container elsewhere without using a global?
        $di = new Di\Container(new Di\Factory());

        // Database
        $di->set('database', $di->lazy(function() {
            return Database::getInstance();
        }));

		// Configuration
		$di->set('config', $di->lazyNew('Schoolbox\\Config\\Loader'));

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
                    $factory = new Factory(
                        $_COOKIE,
                        $di->get('config'),
                        $_SERVER['REQUEST_TIME']
                    );
                    $session = $factory->build();
                    return $session;
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
                function () {
                    $db = Database::getInstance();
                    $st = $db->query("SELECT term, singular_form, plural_form, capitalise_first FROM localisation");

                    $l10n = [];

                    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                        $l10n[$r['term']] = [
                            'singular'   => $r['singular_form'],
                            'plural'     => $r['plural_form'],
                            'capitalise' => (bool)$r['capitalise_first']
                        ];
                    }

                    return new RosettaStone($l10n);
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
            'setExceptionGenerator' => $di->get('mapperFactory')->getLazy(Error\Mapper::class)
        );

        // inject web params (yep, even Sitepage!)
//		$di->setters['SitePage']['setDi'] = $di;
        $di->set('layoutEngine', $di->lazy(function() use ($di) {
            $sitepage = new \SitePage(false);
            $sitepage->setDi($di);
            return $sitepage;
        }));;
        $di->set('sitePage',     $di->lazy(function() use ($di) {
            $sitepage = new \SitePage();
            $sitepage->setDi($di);
            return $sitepage;
        }));

        // Web output class - currently uses Sitepage
        $di->setters[Output\Html::class]['setTemplateEngine'] = $di->lazyGet('templateEngine');
        $di->setters[Output\Stream::class]['setTemplateEngine'] = $di->lazyGet('templateEngine');
        $di->setters[Output\ThemedHtml::class]['setSitepage'] = $di->lazyGet('layoutEngine');
        $di->set('webOutput:base', $di->lazyNew(Output\Base::class));
        $di->set('webOutput:html', $di->lazyNew(Output\ThemedHtml::class));
        $di->set('webOutput:nochrome', $di->lazyNew(Output\Html::class));
        $di->set('webOutput:json', $di->lazyNew(Output\Json::class));
        $di->set('webOutput:file', $di->lazyNew(Output\File::class));
        $di->set('webOutput:stream', $di->lazyNew(Output\Stream::class));

        // Mapper notifications:
        // They rely upon the session being injected into them, so do that.
        $di->setters['Schoolbox\\Mapper']['setSessionData'] = $di->lazy(function() use($di) {
            /** @var \Alaress\Session\Session $session */
            $session = $di->get('session');
            $session->start();
            return isset($_SESSION) ? $_SESSION : array();
        });

        // Template engine and functions
        // Template loader: sets up link to templates without a namespace.
        $di->set(
            'templateEngine:loader',
            $di->lazy(
                function () use ($di) {
                    $config      = $di->get('config');
                    $chainLoader = new ChainLoader(new ModuleLoader($di, '[^@(?P<module>[^/]+)/]'));
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

        $di->setters['Alaress\\Twig\\Method\\RouteLookup']['setRouter'] = $di->lazyGet('router');
        $di->set('templateEngine:routeLookup', $di->lazyNew('Alaress\\Twig\\Method\\RouteLookup'));

        $di->setters['Alaress\\Twig\\Method\\IsRouteAuthorized']['setRouter']     = $di->lazyGet('router');
        $di->setters['Alaress\\Twig\\Method\\IsRouteAuthorized']['setAuthorizer'] = $di->lazyGet('authorizer');
        $di->setters['Alaress\\Twig\\Method\\IsRouteAuthorized']['setSession']    = $di->lazyGet('session');
        $di->set('templateEngine:isRouteAuthorized', $di->lazyNew('Alaress\\Twig\\Method\\IsRouteAuthorized'));

        $di->params[TwigMethod\RouteLookupIfAuthorized::class]['routeLookup'] = $di->lazyGet('templateEngine:routeLookup');
        $di->params[TwigMethod\RouteLookupIfAuthorized::class]['isRouteAuthorized'] = $di->lazyGet('templateEngine:isRouteAuthorized');
        $di->set('templateEngine:routeLookupIfAuthorized', $di->lazyNew(TwigMethod\RouteLookupIfAuthorized::class));

        $di->set(
            'templateEngine',
            $di->lazy(
                function () use ($di) {
                    $config = $di->get('config');

                    $options = [
                        'debug' => $config->get('debug_templates'),
                    ];

                    // do not cache when in debug mode
                    if (!$config->get('debug_mode')) {
                        $options['cache'] = $config->get('stream_dir') . '/templates/';
                    }

                    $twig   = new \Twig_Environment($di->get('templateEngine:loader'), $options);

                    if($this->debugEnabled) {
                        $twig = new TraceableTwigEnvironment($twig);
                        DebugBar::getInstance()->addCollector(new TwigCollector($twig));
                    }

                    $twig->addExtension(new Twig_Extension_Debug());
                    $twig->addExtension(new Twig_Extensions_Extension_Text());
                    $twig->addExtension(new \Schoolbox\Admin\Navigation\Renderer());
                    $twig->addExtension(new \Schoolbox\Learning\Renderer());

                    // HTML Purifier escape strategy
                    // Please use the filter below instead.
                    $twig->getExtension('core')
                        ->setEscaper('purify', function ($twig, $string) {
                            return Scrub::html($string);
                        });

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

                    // Global access to config object and localisation
                    $twig->addGlobal('config', $config);
                    $twig->addGlobal('locale',  $di->get('l10n'));
                    $dateFormats = new Alaress_Date_Locale_Config();
                    $dateFormats->setConfig($config);
                    $twig->addGlobal('dateFormats', $dateFormats);

                    // Global access to returnUrl vars
                    $encodedUrl = Redirect::encode($_GET["page"]);
                    $twig->addGlobal(
                        'RETURN_URL',
                        [
                            'name'     => Redirect::RETURN_URL,
                            'current'  => $encodedUrl,
                            'previous' => (isset($_REQUEST[Redirect::RETURN_URL])
                                ? $_REQUEST[Redirect::RETURN_URL]
                                : $encodedUrl),
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

                    $twig->addFunction(new Twig_SimpleFunction('routeLookupIfAuthorized', $di->get('templateEngine:routeLookupIfAuthorized')));

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
                \Schoolbox\Notif\StorefileLogger::class,
                [
                    'receiver' => $di->lazyNew(\Schoolbox\Resource\Storage\Mapper::class),
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
                \Schoolbox\Sync\Provisioner\Provisioner::class,
                [
                    'rulesetFactory' => $di->lazyNew(\Schoolbox\Sync\Ruleset\Factory::class),
                    'grammarFactory' => $di->lazyNew(
                        \Schoolbox\Sync\Grammar\Factory::class,
                        [
                            'log'        => $di->lazyGet('ups.logger'),
                            'userMapper' => $di->lazy(function () {
                                $map = new \Schoolbox\Sync\Provisioner\UserMapper;
                                $map->setNotifier(new SilentNotif());
                                return $map;
                            }),
                            'ruleMapper' => $di->lazyNew(\Schoolbox\Sync\Rule\Mapper::class),
                        ]
                    ),
                    'externalUserSource' => $di->lazy(function() use ($di) {
                        $source = new \Schoolbox\External\Connector\SyncFactory();
                        return $source->build();
                    }),
                    'userMapper'     => $di->lazyNew(\Schoolbox\Sync\Provisioner\UserMapper::class),
                    'logger'         => $di->lazyGet('ups.logger'),
                    'logMapper'      => $di->lazyNew(\Schoolbox\Sync\Log\Mapper::class),
                ]
            )
        );

        // Register External_User service
        $di->set('externalUser', $di->lazyNew(\External_User::class));

        // Register External_Timetable service
        $di->set('externalTimetable', $di->lazyNew(\External_Timetable::class));

        // Register External_Class service
        $di->set('externalClass', $di->lazyNew(\External_Class::class));

        // Register course/unit implementation data sources
        $di->set(
            'unitImplementationDataSource',
            $di->lazyNew(
                UnitImplMapperProxy::class,
                [
                    'mapper'     => $mapperFactory->getLazy(UnitImplMapper::class),
                    'translator' => $di->lazyNew(UnitImplTranslator::class),
                ]
            )
        );
        $di->set(
            'courseImplementationDataSource',
            $di->lazyNew(
                CourseImplMapperProxy::class,
                [
                    'mapper'         => $mapperFactory->getLazy(CourseImplMapper::class),
                    'translator'     => $di->lazyNew(CourseImplTranslator::class),
                    'unitMapper'     => $mapperFactory->getLazy(UnitImplMapper::class),
                    'unitTranslator' => $di->lazyNew(UnitImplTranslator::class),
                ]
            )
        );

        // Register course/unit specification data sources
        $di->set(
            'unitSpecificationDataSource',
            $di->lazyNew(
                UnitSpecMapperProxy::class,
                [
                    'mapper'     => $mapperFactory->getLazy(UnitSpecMapper::class),
                    'translator' => $di->lazyNew(UnitSpecTranslator::class),
                ]
            )
        );
        $di->set(
            'courseSpecificationDataSource',
            $di->lazyNew(
                CourseSpecMapperProxy::class,
                [
                    'mapper'         => $mapperFactory->getLazy(CourseSpecMapper::class),
                    'translator'     => $di->lazyNew(CourseSpecTranslator::class),
                    'unitMapper'     => $mapperFactory->getLazy(UnitSpecMapper::class),
                    'unitTranslator' => $di->lazyNew(UnitSpecTranslator::class),
                ]
            )
        );

        // Register assessment data source
        $di->set(
            'assessmentTranslator',
            $di->lazy(function () use ($di) {
                $translator = new \Schoolbox\Learning\Assessment\DataModel\Translator(
                    new \Schoolbox\Learning\MarkScheme\Factory(),
                    $di->newInstance(UnitSpecTranslator::class),
                    $di->newInstance(UnitImplTranslator::class)
                );
                $translator
                    ->addSubTranslator(new \Schoolbox\Learning\Assessment\DataModel\Translator\Task())
                    ->addSubTranslator(new \Schoolbox\Learning\Assessment\DataModel\Translator\DueWork())
                    ->addSubTranslator(new \Schoolbox\Learning\Assessment\DataModel\Translator\Quiz())
                    ->addSubTranslator(new \Schoolbox\Learning\Assessment\DataModel\Translator\Project($translator))
                ;
                return $translator;
            })
        );

        // Register assessment data source
        $di->params[AssessmentMapperProxy::class] = [
            'baseMapper'            => $mapperFactory->getLazy(AssessmentMapper::class),
            'translator'            => $di->lazyGet('assessmentTranslator'),
            'participantDataSource' => $di->lazyGet('participantDataSource'),
        ];
        $di->set('assessmentDataSource', $di->lazyNew(AssessmentMapperProxy::class));

        // Register participant data source (with responses and feedback)
        $di->params[ParticipantDataSource::class] = [
            'externalDb'         => $di->lazyGet('externalClass'),
            'userAccessMapper'   => $mapperFactory->getLazy(UserAccessMapper::class),
            'userMapper'         => $mapperFactory->getLazy(UserMapper::class),
            'responseDataSource' => $di->lazyGet('responseDataSource'),
            'feedbackDataSource' => $di->lazyGet('feedbackDataSource'),
        ];
        $di->set('participantDataSource', $di->lazyNew(ParticipantDataSource::class));

        // Register response data source
        $di->set(
            'responseDataSource',
            $di->lazy(
                function () use ($di, $mapperFactory) {
                    return (new \Schoolbox\Learning\Assessment\Response\DataSource())
                        ->addSubSource(
                            new \Schoolbox\Learning\Assessment\Response\SubSource\DueWork(
                                new \Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy(
                                    $mapperFactory->get(\Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper::class)
                                )
                            )
                        )
                        ->addSubSource(
                            new \Schoolbox\Learning\Assessment\Response\SubSource\Task(
                                new \Schoolbox\Learning\Assessment\Task\Completion\MapperProxy(
                                    $mapperFactory->get(\Schoolbox\Assessment\Course\Task\CompletionMapper::class)
                                )
                            )
                        )
                        ->addSubSource(
                            new \Schoolbox\Learning\Assessment\Response\SubSource\Quiz(
                                new \Schoolbox\Learning\Assessment\Quiz\Attempt\MapperProxy(
                                    $mapperFactory->get(QuizAttemptMapper::class)
                                )
                            )
                        );
                }
            )
        );

        // Register feedback data source
        $di->set(
            'feedbackDataSource',
            $di->lazy(
                function () use ($di, $mapperFactory) {
                    return (new \Schoolbox\Learning\Assessment\Feedback\DataSource())
                        ->addSubSource(
                            new \Schoolbox\Learning\Assessment\Feedback\SubSource\DueWork(
                                new \Schoolbox\Learning\Assessment\DueWork\Feedback\MapperProxy(
                                    $mapperFactory->get(\Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper::class)
                                )
                            )
                        )
                        ->addSubSource(
                            new \Schoolbox\Learning\Assessment\Feedback\SubSource\Task(
                                new \Schoolbox\Learning\Assessment\Task\Feedback\MapperProxy(
                                    $mapperFactory->get(\Schoolbox\Assessment\Course\Task\CompletionMapper::class)
                                )
                            )
                        )
                        ->addSubSource(
                            new \Schoolbox\Learning\Assessment\Feedback\SubSource\Quiz(
                                new \Schoolbox\Learning\Assessment\Quiz\Feedback\MapperProxy(
                                    $mapperFactory->get(QuizAttemptMapper::class)
                                )
                            )
                        );
                }
            )
        );

        // Register default grade group
        $di->set(
            'defaultGradeGroup',
            $di->lazy(
                function () use ($di, $mapperFactory) {
                    // A default group surely must be set up?
                    return $mapperFactory->get(GradeGroupMapper::class)->findById(
                        $di->get('config')->get('default_grade_group')
                    );
                }
            )
        );

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
            $di->lazyNew(
                \Calendar_Api_Factory::class,
                ['assessmentDataSource' => $di->lazyGet('assessmentDataSource'),]
            )
        );


        // load dependencies into the kernel itself
        $this->kernel->setDependencyInjector($di);
        $this->kernel->setDebugMode($this->debugEnabled);


        // initialise session
        $di->get('session')->start();

        // Initialise module configuration
        require_once SITE_root . '/conf/modules/ModuleConfigInterface.php';
        $customModuleLocation = sprintf(
            '/conf/modules/Custom/%s/',
            ucfirst($this->kernel->getDependencyInjector()->get('config')->get('site_location'))
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
                /** @var Config\Modules\ModuleConfigInterface $obj */
                $obj = new $class();
                $obj->configure($this->kernel);
            }

        }

    }

    /**
     * Shutdown handler for either exceptions or fatal errors
     *
     * @param \Exception|array $e
     * @param array $stack
     */
    public function shutdownSafely($e = null, $stack = null)
    {
        $outputStackTrace = Config::get('debug_show_stack_trace');

        if ($e instanceof \Exception) {
            $error = array(
                'file'    => $e->getFile(),
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'type'    => $e->getCode(),
            );

            $this->kernel->callExceptionCallbacks($e);

        } elseif (is_array($e)) {
            $error = $e;
        } else {
            $error = error_get_last();
        }

        // it's almost a guarantee that some kind of minor error will have occurred at some point
        if ($error !== null) {

            // use the document_root to get a Schoolbox-relative file in which the error occurred
            $cause = substr($error['file'], strlen($_SERVER['DOCUMENT_ROOT']) - 4);

            // if it was a fatal error or exception that triggered our sudden but inevitable demise...
            if (
                (intval($error['type']) & (E_ERROR | E_CORE_ERROR | E_USER_ERROR | E_COMPILE_ERROR))
                || $e instanceof \Exception
            ) {
                // RedisException extends RuntimeException: occurs when Redis
                // session management isn't working for whatever reason.
                $code    = 400;
                $info    = "If the problem persists, please contact your System Administrator including the following information:";

                if ($e instanceof \PDOException){
                    $code = 500;
                    $message = 'Schoolbox is currently experiencing internal problems with its database. This issue has been logged.';
                } else if ($e instanceof \RuntimeException) {
                    $code    = 500;
                    $message = 'Schoolbox is currently down due to an internal service being inactive.';
                } else {
                    $message = 'Schoolbox has encountered a fatal error.';

                    // decide what to tell the user based on where this error came from
                    switch ($cause) {
                        // certain queries in the reports can return too much data to fit under the memory limit
                        case '/library/Report/Login.php':
                        case '/library/Report/Activity.php':
                            $info = "The report you tried to run has returned too much data. Please press 'Back' and refine your report filter parameters.";
                            break;
                        // if it's not somewhere we expect to find an error, just be generic about it
                        default:
                            break;
                    }
                }

                // Report to New Relic
                if (extension_loaded('newrelic')) {
                    if ($e instanceof \Exception ) {
                        newrelic_notice_error(null, $e);
                    }
                }

                if(isset($_SERVER['HTTP_HOST'])) {
                    $url = 'http'.(getenv("SERVER_PORT") == 443 ? 's://' : '://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                }
                else {
                    $url = '';
                }

                // explain that something went cataclysmically wrong.

                // Use Sitepage to output an error message in the school's design.
                // If there's no SitePage in DI yet: just output a styleless error.
                $di = $this->kernel->getDependencyInjector();

                if (!$e instanceof \Exception) {
                    $e = $error;
                    $e['code'] = $code;

                    if (is_array($stack)) {
                        array_shift($stack);
                        $e['trace'] = $stack;
                    }
                }

                /* Use structured information for rendering
                 */
                $args = [
                    'message' => $message,
                    'info' => $info,
                    'url' => $url,
                    'error' => $error,
                    'date' => date('Y-m-d H:i:s'),
                    'outputStackTrace' => $outputStackTrace,
                    'stackTrace' => $e
                ];

                /* Pass $data straight in for twig rendering
                 */
                if ($di instanceof Di\Container) {
                    /** @var \SitePage $page */
                    $page = $di->get('layoutEngine');
                    $page->error(null, $args, $code);
                }
                /* Just dump it out in raw form
                 * TODO: Make synonymous with twig output
                 */
                else {
                    $output =
                        "<p><b>{$args['message']} {$args['info']}</b></p>" .
                        '<ul>' .
                        "<li>Datetime: {$args['date']}</li>" .
                        "<li>URL: {$args['url']}" .
                        '</li>';

                    if ($outputStackTrace) {
                        $output .= "<li>Error: {$args['error']['message']} in {$args['error']['file']} on line {$args['error']['line']}.</li>";
                    } else {
                        $output .= "<li>Error: {$args['error']['message']}. (Further details have been logged.)</li>";
                    }

                    $output .= '</ul>';

                    echo $output;
                }
            }
        }
    }
}
