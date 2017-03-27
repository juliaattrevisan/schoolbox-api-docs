<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Application;

use Alaress_Date_Locale_Config;
use Alaress\Application\Boot\BootstrapInterface;
use Alaress\Application\Kernel;
use Alaress\Authorizer\Rule;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Factory as DatabaseFactory;
use Alaress\DebugBar\Instance as DebugBar;
use Alaress\Module\Loader as ModuleLoader;
use Alaress\Redis;
use Alaress\Session\Factory;
use Alaress\Twig\Loader\ChainLoader;
use Alaress\Twig\Method as TwigMethod;
use Alaress\Web\Output;
use Alaress\Web\Router\RouterFactory;
use Aura\Di;
use Database;
use DebugBar\Bridge\Twig\TraceableTwigEnvironment;
use DebugBar\Bridge\Twig\TwigCollector;
use PDO;
use Schoolbox\Admin\Navigation\Renderer as AdminNavigationRenderer;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeGroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Assessment\Course\Task\CompletionMapper as TaskCompletionMapper;
use Schoolbox\Collection;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\External\Connector\Factory as ExternalConnectorFactory;
use Schoolbox\Error;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DataModel\Translator as AssessmentDataModelTranslator;
use Schoolbox\Learning\Assessment\DataModel\Translator\DueWork as DueWorkTranslator;
use Schoolbox\Learning\Assessment\DataModel\Translator\Project as ProjectTranslator;
use Schoolbox\Learning\Assessment\DataModel\Translator\Quiz as QuizTranslator;
use Schoolbox\Learning\Assessment\DataModel\Translator\Task as TaskTranslator;
use Schoolbox\Learning\Assessment\DueWork\Feedback\MapperProxy as DueWorkFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy as DueWorkSubmissionMapperProxy;
use Schoolbox\Learning\Assessment\Feedback\DataSource as AssessmentFeedbackDataSource;
use Schoolbox\Learning\Assessment\Feedback\SubSource\DueWork as FeedbackSubSourceDueWork;
use Schoolbox\Learning\Assessment\Feedback\SubSource\Quiz as FeedbackSubSourceQuiz;
use Schoolbox\Learning\Assessment\Feedback\SubSource\Task as FeedbackSubSourceTask;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Quiz\Attempt\MapperProxy as QuizAttemptMapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Feedback\MapperProxy as QuizFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Response\DataSource as AssessmentResponseDataSource;
use Schoolbox\Learning\Assessment\Response\SubSource\DueWork as ResponseSubSourceDueWork;
use Schoolbox\Learning\Assessment\Response\SubSource\Quiz as ResponseSubSourceQuiz;
use Schoolbox\Learning\Assessment\Response\SubSource\Task as ResponseSubSourceTask;
use Schoolbox\Learning\Assessment\Task\Feedback\MapperProxy as TaskFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\Task\Response\MapperProxy as TaskResponseMapperProxy;
use Schoolbox\Learning\Assessment\Transactor as LearningAssessmentTransactor;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Mapper;
use Schoolbox\Learning\Renderer as LearningRenderer;
use Schoolbox\LTI\Provider\Mapper as LTIProviderMapper;
use Schoolbox\MapperFactory;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Notif\SilentNotif;
use Schoolbox\Notif\StorefileLogger as NotifStorefileLogger;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Role;
use Schoolbox\Sync\Grammar\Factory as SyncGrammarFactory;
use Schoolbox\Sync\Log\Mapper as SyncLogMapper;
use Schoolbox\Sync\Provisioner\Provisioner;
use Schoolbox\Sync\Rule\Mapper as SyncRuleMapper;
use Schoolbox\UserMapper;
use Scrub;
use Twig_Extension_Core;
use Twig_Extension_Debug;
use Twig_Extensions_Extension_Text;
use Twig_Loader_Filesystem;
use Twig_SimpleFilter;
use Twig_SimpleFunction;

abstract class AbstractBootstrap implements BootstrapInterface
{
    /** @var Kernel */
    private $kernel;

    /** @var bool  */
    private $debugEnabled = false;

    /**
     * @param Kernel $kernel
     */
    public function __construct($kernel) {
        $this->kernel = $kernel;
    }

    /**
     * Install any internal dependencies into the kernel (routing, etc.)
     *
     * @return void
     */
    public function installDependencies()
    {
        // Do dependency injection stuff
        // How do I get this container elsewhere without using a global?
        // Set up the dependency injector
        $di = new Di\Container(new Di\Factory());

        // Store a connector to the Schoolbox internal database

        $di->set('databaseInternal', function() {

            // The database connection settings are stored in magical global definitions

            $factory = new DatabaseFactory();
            $connectorInternal = $factory->createMysql();
            if (!$connectorInternal->connect(SQL_host, SQL_db, SQL_user, SQL_passwd)) {
                throw new DatabaseException("Unable to connect to Schoolbox database.");
            }

            return $connectorInternal;
        });

        // Store a connector to the SIS database(s)

        $di->set('databaseExternal', $di->lazy(function() use ($di) {
            /** @var IConfigLoader $config */
            $config = $di->get('config');

            $factory = new \Schoolbox\Database\Factory();
            return $factory->getExternalDatabase($config);
        }));


        // Configuration
        $di->set('config', $di->lazyNew(\Schoolbox\Config\Loader::class));

        $di->set('redis', $di->lazy(function () use ($di) {
            // Redis connection configuration
            Redis::getInstance()->connect($di->get('config')->get('redis_url'), null, INSTANCE_ID);

            return Redis::getClientInstance();
        }));

        $di->set('amqp', $di->lazy(function () {
            return Connector::getInstance();
        }));

        // install mapper DI
        // (yes I know this is icky, but it will have to do until mapper construction is set up with DI properly)
        Mapper::di($di);

        $conf = $di->get('config');

        // Set correct timezone
        date_default_timezone_set($timezone = $conf->get('date_timezone'));

        // Set up DB timezones here too
        Database::getInstance()->setTimezone($timezone);
        DeprecatedDatabase::getInstance()->setTimezone($timezone);

        $di->set('config', $di->lazyNew(ConfigLoader::class));
        $di->set('notif', $di->lazy(function () {
            // replace this with $di->lazyGetCall() once we upgrade Aura.Di
            return Notifactory::create();
        }));

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

                    $factory = new Factory(
                        $_COOKIE,
                        $config,
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
                    /** @var IConfigLoader $config */
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
                    $twig->addExtension(new AdminNavigationRenderer());
                    $twig->addExtension(new LearningRenderer());

                    // HTML Purifier escape strategy
                    // Please use the filter below instead.

                    /** @var Twig_Extension_Core $twigCore */
                    $twigCore = $twig->getExtension('core');

                    $twigCore->setEscaper('purify', function ($twig, $string) {
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
                    $twig->addGlobal('NOW', \Alaress_Date_Format::now());
                    $twig->addGlobal('USER', $_SESSION['user']);

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
                    'rulesetFactory' => $di->lazyNew(\Schoolbox\Sync\Ruleset\Factory::class),
                    'grammarFactory' => $di->lazyNew(
                        SyncGrammarFactory::class,
                        [
                            'log'        => $di->lazyGet('ups.logger'),
                            'userMapper' => $di->lazy(function () {
                                $map = new \Schoolbox\Sync\Provisioner\UserMapper;
                                $map->setNotifier(new SilentNotif());
                                return $map;
                            }),
                            'ruleMapper' => $di->lazyNew(SyncRuleMapper::class),
                        ]
                    ),
                    'externalUserSource' => $di->lazy(function() use ($di) {
                        /** @var IConfigLoader $config */
                        $config = $di->get('config');
                        $factory = new ExternalConnectorFactory();
                        return $factory->build($config, ExternalConnectorFactory::EXTERNAL_SYNC);
                    }),
                    'userMapper'     => $di->lazyNew(\Schoolbox\Sync\Provisioner\UserMapper::class),
                    'logger'         => $di->lazyGet('ups.logger'),
                    'logMapper'      => $di->lazyNew(SyncLogMapper::class),
                ]
            )
        );

        // Register External_User service
        $di->set('externalUser', $di->lazyNew(\External_User::class));

        // Register External_Timetable service
        $di->set('externalTimetable', $di->lazyNew(\External_Timetable::class));

        $di->set('externalCommunityPortal', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $config */
            $config = $di->get('config');
            $factory = new ExternalConnectorFactory();
            return $factory->build($config, ExternalConnectorFactory::EXTERNAL_COMMUNITY_PORTAL);
        }));

        // Register external NDS object services

        $di->set('externalNDSUser', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $config */
            $config = $di->get('config');
            $factory = new ExternalConnectorFactory();
            return $factory->build($config, ExternalConnectorFactory::EXTERNAL_USER);
        }));

        $di->set('externalNDSTimetable', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $config */
            $config = $di->get('config');
            $factory = new ExternalConnectorFactory();
            return $factory->build($config, ExternalConnectorFactory::EXTERNAL_TIMETABLE);
        }));
        
        // LTI Provider mapper
        $di->set('providerMapper', $di->lazyNew(LTIProviderMapper::class));

        // Register External_Class service
        $di->set('externalClass', $di->lazyNew(\External_Class::class));

        // Register assessment data source
        $di->set(
            'assessmentTranslator',
            $di->lazy(function () use ($di) {
                $translator = new AssessmentDataModelTranslator(
                    new \Schoolbox\Learning\MarkScheme\Factory()
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

                    /** @var \Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy $submissionDataSource  */
                    $submissionDataSource = $di->get('submissionDataSource');

                    /** @var \Schoolbox\Learning\Assessment\Task\Response\MapperProxy $completionDataSource */
                    $completionDataSource = $di->get('completionDataSource');

                    /** @var \Schoolbox\Learning\Assessment\Quiz\Attempt\MapperProxy $attemptDataSource */
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

                    /** @var \Schoolbox\Learning\Assessment\DueWork\Feedback\MapperProxy $returnDataSource */
                    $returnDataSource = $di->get('returnDataSource');

                    /** @var \Schoolbox\Learning\Assessment\Task\Feedback\MapperProxy $ratificationDataSource */
                    $ratificationDataSource = $di->get('ratificationDataSource');

                    /** @var \Schoolbox\Learning\Assessment\Quiz\Feedback\MapperProxy $reviewDataSource */
                    $reviewDataSource = $di->get('reviewDataSource');

                    return (new AssessmentFeedbackDataSource())
                        ->addSubSource(new FeedbackSubSourceDueWork($returnDataSource))
                        ->addSubSource(new FeedbackSubSourceTask($ratificationDataSource))
                        ->addSubSource(new FeedbackSubSourceQuiz($reviewDataSource));
                }
            )
        );

        $di->params[LearningAssessmentTransactor::class] = [
            'folderMapper'                  => $di->get('mapperFactory')->getLazy(FolderMapper::class),
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
    }

    /**
     * @return bool
     */
    protected function isDebugEnabled()
    {
        return $this->debugEnabled;
    }

    /**
     * @param bool $debugEnabled
     */
    protected function setDebugEnabled($debugEnabled)
    {
        $this->debugEnabled = $debugEnabled;
    }

    /**
     * @return Kernel
     */
    protected function getKernel()
    {
        return $this->kernel;
    }
}
