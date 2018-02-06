<?php

use Aura\Router\Router;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Menu\Side\Renderer as MenuSideRender;
use Schoolbox\Menu\Top\Renderer as MenuTopRenderer;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Tour\Renderer as TourRenderer;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Assessment\Redirect;

class SitePage extends Page
{
    /**
     * The site that this SitePage uses to get templates from
     * @var string
     */
    public $site;


    /**
     * The following template paths render the skin's chrome
     *
     * The core version is always used if this school uses the skin builder.
     *
     * @var array|string[]
     */
    private $chromePaths = [
        'common/*.html',
        'feedback/*.html',
        'include/*.html',
        'policy/*.html',
    ];

    /**
     * The dependency injection container, injected into SitePage!
     * @var Aura\Di\Container
     */
    protected $di;

    /**
     * List of arguments passed in URL
     * @var array
     */
    public $arg = array();

    /** @var string $currentTab */
    private $currentTab;

    /** @var string $pageName */
    private $pageName;

    /** @var array $jscript */
    private $jscript;

    /** @var array $css */
    private $css;

    /** @var bool $ajax */
    private $ajax;

    /** @var bool $lite */
    private $lite;

    /** @var User $user */
    private $user;

    /** @var Config */
    private $config;

    /** @var DeprecatedDatabase $db */
    private $db;

    /**
     * SitePage constructor.
     * @param bool $login
     *
     */
    public function __construct($login = true)
    {
        parent::__construct();

        $this->config = \Config::getInstance();
        $this->db = \DeprecatedDatabase::getInstance();

        if(isset($_SESSION['user'])) {
            $this->user = $_SESSION['user'];
        }

        if ($login) {
            $this->initSession();
        }

        $this->setSite();
    }

    /**
     * @param  Aura\Di\ContainerInterface $di
     * @return $this
     */
    public function setDi($di)
    {
        $this->di = $di;
        return $this;
    }

    /**
     * @return Aura\Di\ContainerInterface
     * @throws RuntimeException if this SitePage has no DI container.
     */
    public function getDi()
    {
        if (!($this->di instanceof Aura\Di\ContainerInterface)) {
            throw new RuntimeException('This SitePage does not have a dependency injector');
        }
        return $this->di;
    }

    /**
     * Gets a Twig renderer out of this sitepage's dependency injector,
     * if there is one.
     *
     * @return Twig_Environment
     * @throws RuntimeException if Twig renderer wasn't found in this Sitepage object's dependency injector.
     */
    public function getTwig()
    {
        $twig = $this->getDi()->get('templateEngine');
        if ($twig instanceof Twig_Environment) {
            return $twig;
        }
        throw new RuntimeException('Twig was not found in dependency injector');
    }

    /**
     * Detect connection type
     * Returns the connection type (http:// or https://)
     *
     * @return string
     */
    public static function conType()
    {
        $type = "http://";
        if (getenv("SERVER_PORT") == 443) {
            $type = "https://";
        }

        return $type;
    }

    /**
     * Get websocket port
     *
     * @return int
     */
    public function getWebsocketPort()
    {
        if ($this->conType() == 'https://') {
            return $this->config->get('websocket_port_ssl') ?: 8081;
        }

        return $this->config->get('websocket_port') ?: 8080;
    }

    /**
     * @param $pageFile
     * @param bool $execute
     * @return bool
     */
    public function loadContent($pageFile, $execute = true)
    {
        $this->pageName = $pageFile;

        /* sanitise data, force all 404s through pageNotFound() */
        if (!preg_match("/^[a-zA-Z0-9\/\-\_\.]+/", $pageFile) || $pageFile == 'common/404') {
            $this->pageNotFound();
        }

        /* If you try to access the login screen but you are already logged in, just go home */
        if ($pageFile == '/login/' || $pageFile == '/login') {
            $pageFile = '/home/';
        }

        $this->arg = array();
        $path = explode('/', trim($pageFile, '/'));

        $total = count($path);

        /* iterate backwards through the path */
        for ($i = 0; $i < $total; $i++) {
            $filename = rtrim(implode("/", $path), '/');

            if (is_file(WEB_root . '/' . $filename . '.php') && $execute) {
                $this->nameTransaction('/' . $filename . '.php');
                include(WEB_root . '/' . $filename . '.php');
                return true;
            } elseif (is_file(WEB_root . '/' . $filename . '/index.php') && $execute) {
                $this->nameTransaction('/' . $filename . '/index.php');
                include(WEB_root . '/' . $filename . '/index.php');
                return true;
            } elseif (is_file($this->templateRoot . '/' . $this->getTemplate($filename . '.html'))) {
                $this->setFile(TEMPLATE_content, $filename . '.html');
                return true;
            } elseif (is_file($this->templateRoot . '/' . $filename . '/index.html')) {
                $this->setFile(TEMPLATE_content, $filename . '/index.html');
                return true;
            }

            /* move last element to arguments */
            array_unshift($this->arg, array_pop($path));
        }

        $this->pageNotFound();
        return false;
    }


    /**
     * Sets the name of this page load for New Relic reporting.
     *
     * Usually, the name will be the file included via $this->loadContent(), so
     * we can see which file was requested.
     *
     * @param string $name
     */
    protected function nameTransaction($name)
    {
        if (extension_loaded('newrelic')) {
            newrelic_name_transaction($name);
        }
    }

    /**
     * @return bool
     */
    public function setSite()
    {
        if ($this->config->get('site_detect') == 'domain') {
            //Get site templates via domain name
            if (preg_match('/([\w]+)\./', $_SERVER['HTTP_HOST'], $match)) {
                $this->site = $match[1];
                return true;
            }
        }

        $this->site = $this->config->get('site_location');
        return true;
    }

    /**
     * @return bool
     */
    public function getAjax()
    {
        return $this->ajax;
    }

    /**
     * @return bool
     */
    public function getLite()
    {
        return $this->lite;
    }

    /**
     * @param bool $state
     */
    public function setAjax($state = true)
    {
        $this->ajax = $state;
    }

    /**
     * @param $state
     */
    public function setLite($state)
    {
        $this->lite = $state;
    }

    /**
     * Detects AJAX state based on value of request header X-Requested-With - if
     * it's XMLHttpRequest, the request was made via AJAX.
     *
     * (obviously this isn't totally reliable, but jQuery and other libraries
     *  set this header)
     *
     * @return bool
     */
    public static function detectAjax()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        } else {
            return false;
        }
    }

    /**
     * Outputs a JSON response, with appropriate header.
     *
     * @param mixed $output Variable contents to output
     * @param bool $keepProcessing True if processing continues after output.
     *                              Exits after output if false.
     * @param int $responseCode If set, output this response code.
     */
    public function outputJson($output, $keepProcessing = false, $responseCode = null)
    {
        header('Content-Type: application/json');
        if (isset($responseCode)) {
            http_response_code($responseCode);
        }
        $jsonOutput = json_encode($output);
        if (!$keepProcessing) {
            echo $jsonOutput;
        } else {
            $this->keepProcessingInBackground($jsonOutput);
        }
    }

    /**
     * @param bool $lite
     * @return bool
     */
    public function outputPage($lite = false)
    {
        if ($this->ajax) {
            return true;
        }

        if (!$lite && !$this->lite) {
            $this->buildPage();
            $this->saveHistory();
        }

        $debug_bar_head = '';
        $debug_bar_body = '';

        if (Alaress\DebugBar\Instance::isEnabled()) {
            $renderer = Alaress\DebugBar\Instance::getInstance()->getJavascriptRenderer();
            $renderer->setBaseUrl('/debugbar/');
            $debug_bar_head = $renderer->renderHead();
            $debug_bar_body = $renderer->render();
        }

        if (!$this->user instanceof User) {
            $this->user = new User();
        }
        $dateFormat = $this->config->get('date_local');
        $dateTimeFormat = $this->config->get('date_local').' '.$this->config->get('date_time');
        $templateVar = array(
            'TEMPLATE_YEAR' => date('Y'),
            'SITE_NAME' => $this->config->get('school_name'),
            'SITE_TITLE' => $this->config->get('title'),
            'SITE_GREETING' => TextFormatter::greeting(), // TODO: This should be an object
            'SITE_LOGINNAME' => $this->user->formatName(),
            'SITE_USERNAME' => $this->user->getUsername(),
            'SITE_LOCATION' => $this->site,
            'SITE_ANALYTICS' => $this->config->get('site_analytics'),
            'USER_ID' => $this->user->getId(),
            'EXTERNAL_ID' => $this->user->getExternalId(),
            'USER_ROLE' => $this->user->getRole()->getName(),
            'SITE_VERSION' => $this->config->get('site_version'),
            'SCHOOL_ADDRESS' => $this->config->get('school_address'),
            'DEBUGBAR' => $debug_bar_body,
            'DEBUGBAR_HEAD' => $debug_bar_head,
            'WEBSOCKET_HOST' => self::parseRequestUrl(PHP_URL_HOST),
            'WEBSOCKET_PATH' => $this->config->get('websocket_path') ? $this->config->get('websocket_path') : '/',
            'WEBSOCKET_PORT' => $this->getWebsocketPort(),
            'WEBSOCKET_UNREAD_COUNT' => $_SESSION['msg-count'] ?: 0,
            'WEBSOCKET_UNREAD_LABEL' => $_SESSION['msg-count'] ?: '',
            'WEBSOCKET_STACK_LIMIT' => \Schoolbox\Realtime\Notification\Decorator::STACK_LIMIT,
            'PAGE_SECURE' => empty($_SERVER['HTTPS']) ? 'false' : 'true',
            'DATEPICKER_FORMAT' => json_encode(Alaress_Date_Format_Converter::toDatepicker($dateFormat)),
            'MOMENTJS_FORMAT' => json_encode(Alaress_Date_Format_Converter::toMomentJs($dateFormat)),
            'MOMENTJS_FORMAT_DATETIME' => json_encode(Alaress_Date_Format_Converter::toMomentJs($dateTimeFormat)),
            'LOCAL_DATE_FORMAT' => $dateFormat,
            'LOCAL_DATETIME_FORMAT' => $dateTimeFormat,
            'SYNERGETIC_SSO' => isset($_SESSION["communityLogin"]) ? 'true' : 'false',
            'FEEDBACK_TEXT' => Scrub::text($this->config->get('feedback_text')),
            'FEEDBACK_URL' => Scrub::text($this->config->get('feedback_url')),
        );

        $this->setVar($templateVar);

        $title = $this->getVar('TITLE');

        if (empty($title)) {
            $this->setTitle();
        }
        $this->disableXssProtectionAfterForm();

        $this->template->pParse('finaloutput', TEMPLATE_body);

        return true;
    }

    /**
     * @param bool $customHead
     */
    public function buildPage($customHead = true)
    {
        $this->setFile(TEMPLATE_body, 'include/body.html');
        $this->setFile('headerTpl', 'include/header.html');
        $this->setFile('footerTpl', 'include/footer.html');
        $this->setFile('bannerTpl', 'include/banner.html');
        $this->setFile('instantTpl', 'include/instant.html');

        $this->renderScriptIncludes();

        if ($this->user instanceof User) {
            $this->renderInstantMessages();
        } else {
            // Go away phantom block
            $this->setVar('INSTANTMESSAGE_BLOCK', '');
        }

        $config = new \Schoolbox\Config\Loader();

        /**
         * Alaress Google Analytics
         */
        $this->setBlock('headerTpl', 'google_analytics', 'google_analytics_block');
        $this->setBlock('google_analytics', 'ga_user_events', 'ga_user_events_block');
        $this->setBlock('ga_user_events', 'ga_year_event', 'ga_year_event_block');
        $this->setBlock('ga_user_events', 'ga_impersonated', 'ga_impersonated_block');

        $optIn = $config->get('site_usage_tracking');

        $this->setVar([
            'SITE_SCHOOL_CODE' => $config->get('site_school_code'),
            'SITE_TYPE' => $config->get('site_type'),
            'SITE_VERSION' => $config->get('site_version'),
        ]);

        if ($this->user instanceof User) {
            $this->setVar([
                'ROLE_NAME' => $this->user->getRole()->getName(),
                'ROLE_TYPE' => $this->user->getRole()->getType(),
            ]);

            // Show year level event block if user is a student
            if ($this->user->getRole()->getType() == Schoolbox\Role::STUDENT) {
                $this->setVar('USER_YEAR', $this->user->getYear());
                $this->parse('ga_year_event_block', 'ga_year_event');
            } else {
                $this->setVar('ga_year_event_block', '');
            }

            // If the user has come from a previous session and is a superuser: we're going to log this
            // as impersonated. All others will be considered a legitimate switch and logged normally.
            $impersonated = false;
            if (isset($_SESSION['previous']['user'])) {
                /** @var User $previousUser */
                $previousUser = $_SESSION['previous']['user'];
                if ($previousUser->isSuperuser()) {
                    $impersonated = true;
                }
            }

            if ($impersonated) {
                $this->parse('ga_impersonated_block', 'ga_impersonated');
            } else {
                $this->setVar('ga_impersonated_block', '');
            }

            // Enable the feature tour only if the user is not impersonated: see SA-5927
            if (! $impersonated) {
                $this->setBlock('headerTpl', 'tour_script', 'tour_script_block');

                $toursEnabled = $config->get('tours_enabled');
                if ($toursEnabled) {
                    $request    = parse_url($_SERVER['REQUEST_URI']);
                    $encodedUrl = Redirect::encode($request['path']);
                    $this->setVar('TOUR_ROUTE', $this->routeLookup('tour.getTours', ['url' => $encodedUrl]));
                    $this->parse('tour_script_block', 'tour_script', true);
                } else {
                    $this->setVar('tour_script_block');
                }
            }

            $this->parse('ga_user_events_block', 'ga_user_events');

        } else {
            $this->setVar('ga_user_events_block', '');
        }





        if ($optIn) {
            $this->parse('google_analytics_block', 'google_analytics');
        } else {
            $this->setVar('google_analytics_block', '');
        }

        /**
         * Support and Feedback custom link
         */
        $this->setBlock('footerTpl', 'feedback_link', 'feedback_link_block');

        $feedbackText = $config->get('feedback_text');
        $feedbackURL = $config->get('feedback_url');

        if (strlen(trim($feedbackText)) && strlen(trim($feedbackURL))) {
            $this->parse('feedback_link_block', 'feedback_link');
        } else {
            $this->setVar('feedback_link_block', '');
        }


        $this->parse('MAIN_HEADER_REGION', 'headerTpl');
        $this->parse('MAIN_FOOTER_REGION', 'footerTpl');

        $this->setBlock(TEMPLATE_body, 'banner_logged_in', 'banner_logged_in_block');

        if ($_SESSION['user'] instanceof User) {

            if (!$this->user instanceof User) {
                $this->user = $_SESSION['user'];
            }

            // quasi-localisation
            $this->localise();

            /* Output superuser link to administration */
            $this->setBlock('banner_logged_in', 'admin_only_menu', 'admin_only_menu_block');
            if ($this->user->isSuperuser()) {
                $this->parse('admin_only_menu_block', 'admin_only_menu');
            } else {
                $this->setVar('admin_only_menu_block', '');
            }

            $canSwitch = false;

            /* Output switch user options */
            $this->setBlock('banner_logged_in', 'switch_user', 'switch_user_block');
            $this->setBlock('banner_logged_in', 'can_switch', 'can_switch_block');

            $this->setVar([
                'switch_user_block' => '',
                'can_switch_block' => ''
            ]);
            $linkedUserList = array();

            $previousUser = $_SESSION['previous']['user'];
            foreach ($this->user->getLinkedUser() as $linkedUser) {
                // Don't link to this user if I was that user
                if ($previousUser instanceof User) {
                    if ($linkedUser->getId() == $previousUser->getId()) {
                        continue;
                    }
                }
                // Skip if user account is disable
                if (!$linkedUser->getEnabled()) {
                    continue;
                }

                $linkedUserList[] = $linkedUser;
            }

            /** @var User[] $linkedUserList */
            $linkedUserList = UserMapper::getInstance()->findByColumn('id', array_map(function (User $user) { return $user->getId(); }, $linkedUserList))->getAll();

            foreach ($linkedUserList as $linkedUser) {
                $this->setVar(array(
                        'MENU_SWITCH_USERID' => $linkedUser->getId(),
                        'MENU_SWITCH_EXTERNAL_ID' => $linkedUser->getExternalId(),
                        'MENU_SWITCH_USER_FULLNAME' => $linkedUser->getFullName(),
                        'MENU_SWITCH_USER_ROLE' => $linkedUser->getRole()->getName(),
                    )
                );
                $this->parse('switch_user_block', 'switch_user', true);
                $canSwitch = true;
            }

            if (count($linkedUserList) > 0) {
                $this->user->setLinkedUser($linkedUserList);
            }

            $this->setBlock('banner_logged_in', 'recover_user', 'recover_user_block');
            $this->setVar('recover_user_block', '');

            if ($previousUser instanceof User) {
                $this->setVar(array(
                        'MENU_SWITCH_USERID' => $previousUser->getId(),
                        'MENU_SWITCH_EXTERNAL_ID' => $previousUser->getExternalId(),
                        'MENU_SWITCH_USER_FULLNAME' => $previousUser->getFullName(),
                        'MENU_SWITCH_USER_ROLE' => $previousUser->getRole()->getName(),
                    )
                );
                $this->parse('recover_user_block', 'recover_user', true);
                $canSwitch = true;
            }

            if ($canSwitch) {
                $this->parse('can_switch_block', 'can_switch');
            }

            // Render side menu

            try {
                $menuSideRenderer = new MenuSideRender();
                $menuSideContents = $menuSideRenderer->menuSideRenderer($this->getTwig(), $this->user);
                $this->setVar('MENU_SIDE', $menuSideContents);
            } catch (Twig_Error_Loader $e) {
                // This can happen if rendering an error message where the error happens before the menu module has loaded
                $this->setVar('MENU_SIDE', '');
            }

            // Render top menu

            try {
                $menuTopRenderer = new MenuTopRenderer();
                $menuTopContents = $menuTopRenderer->menuTopRenderer($this->getTwig(), $this->currentTab, $this->user);
                $this->setVar('MENU_TOP', $menuTopContents);
            } catch (Twig_Error_Loader $e) {
                // This can happen if rendering an error message where the error happens before the menu module has loaded
                $this->setVar('MENU_TOP', '');
            }

            $this->parse('banner_logged_in_block', 'banner_logged_in');
        } else {
            $this->setVar('MENU_SIDE', '');
            $this->setVar('MENU_TOP', '');
            $this->setVar('banner_logged_in_block', '');
        }

        $this->parse('MAIN_BANNER_REGION', 'bannerTpl');
        $this->parse('MAIN_CONTENT_REGION', TEMPLATE_content);

        if ($this->getVar('custom_head') && $customHead) {
            $this->parse('CUSTOM_HEADER_REGION', 'custom_head');
        } else {
            $this->setVar('CUSTOM_HEADER_REGION', '');
        }
    }

    /**
     * @param $tab
     */
    public function setTab($tab)
    {
        $this->currentTab = $tab;
    }

    /**
     *
     */
    public function initSession()
    {
        session_start();

        // Validate the session ID - it has no right to be empty after it's started
        $sessionHandler = new Session();
        if (!$sessionHandler->validateSessionId(session_id())) {
            // Have a valid ID instead
            session_regenerate_id(true);
            $sessionHandler->destroy();
            $this->goLoginPage();
        }

        if (!$_SESSION['user'] instanceof User || //If session does not exist ||
            (($_SESSION['lasttime'] + $this->config->get('session_length')) < time()) || //If session has expired
            ($this->config->get('track_ip') && $_SESSION['ip'] != Session::getIP())  // If IP is not the same
        ) {
            //Catch users with the remember me cookie
            if ($this->config->get('session_remember') && isset($_COOKIE['remember']) && $_COOKIE['remember']) {
                $redis = \Alaress\Redis::getClientInstance();
                $username = $redis->executeCommand($redis->createCommand('get', array($_COOKIE['remember'])));

                if (strlen($username) === 0 || !$sessionHandler->create($username)) {
                    $sessionHandler->destroy();
                    $this->goLoginPage();
                } else {
                    // For safety's sake regenerate their session ID
                    session_regenerate_id(true);
                }
            } //Catch already logged in users via kerberos or other means
            elseif ($this->config->get('kerb_enable') && $_SERVER['REMOTE_USER']) {
                list($username) = explode('@', $_SERVER['REMOTE_USER']);
                if (!$sessionHandler->create($username)) {
                    $sessionHandler->destroy();
                    $this->goLoginPage();
                }
            } else {
                $sessionHandler->destroy();
                $this->goLoginPage();
            }
        }

        // If we've fallen through to this point, the user must be logged in.
        // Is this a URL in the form of /?page=urlencode($url)?
        // If so, redirect to $url.
        $url = parse_url($_SERVER['REQUEST_URI']);
        if ($url['path'] === '/login' || $url['path'] === '/login/') {
            parse_str($url['query'], $query);
            if (count($query) === 1 && isset($query['page']) && preg_match('|^/|', $query['page'])) {
                $this->localRedir($query['page']);
            }
        }

        //reset session timer
        $_SESSION['lasttime'] = time();

        // Resend session cookie
        $cookieParams  = session_get_cookie_params();
        $sessionLength = \Config::get('session_length');
        $sessionExpiry = ($sessionLength > 0)
            ? $_SERVER['REQUEST_TIME'] + $sessionLength
            : 0;
        setcookie(
            session_name(),
            session_id(),
            $sessionExpiry,
            $cookieParams['path'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }

    /**
     * @param string $override
     */
    public function saveHistory($override = '')
    {
        /* Function saves user page history in session so we can go back to where they came from */
        if (!is_array($_SESSION['history'])) {
            $_SESSION['history'] = array();
        }

        $requestUri = $override ? $override : $_SERVER['REQUEST_URI'];

        /* Dont handle duplicates (page refreshes) */
        if ($_SESSION['history'][0] != $requestUri) {
            array_unshift($_SESSION['history'], $requestUri);

            if (count($_SESSION['history']) > 3) {
                array_pop($_SESSION['history']);
            }
        }
    }

    /**
     * @param $page
     */
    public static function backToTheFuture($page)
    {
        /* In Dans honour, time is ours */
        if (is_array($_SESSION['history'])) {
            if (count($_SESSION['history']) >= $page) {
                SitePage::localRedir($_SESSION['history'][$page - 1]);
            } else {
                SitePage::localRedir($_SESSION['history'][(count($_SESSION['history']) - 1)]);
            }
        } else /* Lost in space and time, just go home */ {
            SitePage::localRedir('/');
        }

    }

    /**
     * Outputs an error message.
     *
     * @param mixed $ec
     *                        Integer: retrieves error text from code from database
     *                        null:    outputs whatever's in $message as error
     * @param array $args Replaces '%MESSAGE%' in $ec
     * @param int $code HTTP response code, defaults to 400 (Bad Request)
     */
    public function error($ec, $args = [], $code = 400)
    {
        // Backward-compatibility with $args being a message
        if (is_string($args)) {
            $args = ['message' => $args];
        }
        /* Enforce structure
         */
        if (!is_array($args)) {
            $args = [];
        }

        $defaultArgs = [
            'code' => '',
            'header' => '',
            'info' => '',
            'summary' => '',
            'url' => '',
            'date' => '',
            'meta' => '',
            'outputStackTrace' => '',
            'stackTrace' => '',
            'message' => '',
        ];
        $args = array_merge($defaultArgs, $args);

        /* If an error code is set, extract textual information from
         * the db via ErrorMapper. Otherwise just use a custom message
         */
        $errorMapper = \Schoolbox\Error\Mapper::getInstance();

        if (isset($ec)) {
            try {
                $error = $errorMapper->findById($ec);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $error = $errorMapper->createModel();
                $error->setMessage('%MESSAGE%');
            }
        } else {
            $error = $errorMapper->createModel();
            $error->setMessage('%MESSAGE%');
        }

        /** @var RosettaStone $l10n */
        $l10n = $this->getDi()->get('l10n');

        /* Set up any replacements
         */
        $error->setMessage(str_replace("%MESSAGE%", $args['message'], $l10n->translateSentence($error->getMessage())));

        http_response_code($code);

        // if we're in ajax mode, spit out in ajax-friendly (ie JSON) format
        if ($this->ajax) {
            header('Content-Type: application/json');
            die(json_encode($error->getMessage()));
        } else {
            $errorContents = $this->getTwig()->render('common/error/general.twig', [
                'code' => $ec,
                'header' => isset($args['message']) ? $args['message'] : '',
                'info' => isset($args['info']) ? $args['info'] : '',
                'summary' => $error,
                'url' => $args['url'],
                'date' => \Alaress_Date_Format::now(),
                'meta' => $args['error'],
                'outputStackTrace' => $args['outputStackTrace'],
                'stackTrace' => $args['stackTrace'],
                'exceptionClass' => is_object($args['stackTrace']) ? get_class($args['stackTrace']) : 'Error',
            ]);

            $this->renderError('Error Encountered', $errorContents);
        }
    }

    /**
     * Render an Error layout with $errorContents in it
     *
     * @param string $title
     * @param string $errorContents
     */
    private function renderError($title, $errorContents)
    {
        $this->setVar(TEMPLATE_content, '');
        $this->setFile(TEMPLATE_content, 'common/error/container.html');
        $this->setTitle($title);

        $this->setVar([
            'OUTPUT' => $errorContents
        ]);

        $this->buildPage(false);
        $this->outputPage(true);
        exit();
    }

    /**
     *
     */
    public function renderInstantMessages()
    {
        /* Docking the tray
         */
        if ($this->user->getHideNotificationsTray()) {

            $this->setVar([
                'HIDE_NOTIFICATIONS_TRAY' => ''
            ]);
        } else {
            $this->setVar([
                'HIDE_NOTIFICATIONS_TRAY' => 'docking'
            ]);
        }

        $this->setBlock(TEMPLATE_body, 'instantmessage_toggle', 'instantmessage_toggle_block');

        if ($this->config->get('messaging_enabled')) {
            $this->parse('instantmessage_toggle_block', 'instantmessage_toggle');
        } else {
            $this->setVar('instantmessage_toggle_block', '');
        }

        if ($this->config->get('messaging_enabled')) {
            $this->parse('INSTANTMESSAGE_BLOCK', 'instantTpl');
            $this->setVar('NOTIFICATION_CLASS', '');
        } else {
            $this->setVar('INSTANTMESSAGE_BLOCK', '');
            $this->setVar('NOTIFICATION_CLASS', 'disableNotifications');
        }
    }

    /**
     *
     */
    public function renderScriptIncludes()
    {
        $this->setBlock('headerTpl', 'header_javascript', 'header_javascript_block');
        $this->setBlock('headerTpl', 'header_css', 'header_css_block');

        if (is_array($this->jscript)) {
            foreach ($this->jscript as $scriptOutput) {
                $this->setVar($scriptOutput);
                $this->parse('header_javascript_block', 'header_javascript', true);
            }
        } else {
            $this->setVar('header_javascript_block', '');
        }

        if (is_array($this->css)) {
            foreach ($this->css as $cssOutput) {
                $this->setVar($cssOutput);
                $this->parse('header_css_block', 'header_css', true);
            }
        } else {
            $this->setVar('header_css_block', '');
        }

    }

    /**
     * @param $file
     */
    public function setJavascript($file)
    {
        $this->jscript[] = array('HEADER_SCRIPT_LOCATION' => $file);
    }

    /**
     * @param $file
     * @param string $type
     */
    public function setCSS($file, $type = 'screen')
    {
        $this->css[] = array('HEADER_CSS_TYPE' => $type, 'HEADER_CSS_LOCATION' => $file);
    }

    /**
     * @param $handle
     * @param $file
     * @return bool
     */
    public function setFile($handle, $file)
    {
        if (!$this->template->setFile($handle, $this->getTemplate($file))) {
            return false;
        }

        if ($handle == TEMPLATE_content) {
            $this->setBlock($handle, "custom_head", "custom_head_block");
            $this->setVar('custom_head_block', '');
        }

        return true;
    }

    /**
     * @param string $title
     */
    public function setTitle($title = '')
    {
        $this->setVar('TITLE', $this->config->get('title') . (!empty($title) ? ' - ' . Scrub::text($title) : ''));
    }

    /**
     * @param $template
     * @return bool|string
     */
    public function getTemplate($template)
    {
        // Chrome/skin templates are always core if skin builder is on,
        // except if the version in the skin is newer than 15.5 release date (this is determined if instant.html is included)
        foreach ($this->chromePaths as $chromePath) {
            if (fnmatch($chromePath, $template) &&
                is_file($this->templateRoot . '/' . $this->site . '/include/instant.html')
            ) {
                return 'core/' . $template;
            }
        }

        // common, feedback, include, policy
        if (is_file($this->templateRoot . '/' . $this->site . '/' . $template)) {
            return $this->site . '/' . $template;
        } else {
            if (is_file($this->templateRoot . '/core/' . $template)) {
                // change this back to "core" for the rest of the releases.
                return 'core/' . $template;
            } else {
                return false;
            }
        }
    }

    /**
     * @param $varname
     * @return mixed
     */
    public function getVar($varname)
    {
        return $this->template->getVar($varname);
    }

    /**
     * Redirect to a specific url
     * conType() is accessed statically here because localRedir()
     * is often accessed statically itself.
     *
     * Usage - SitePage::localRedir($url)
     *
     * @static
     * @param  string $file
     * @param  bool $keepProcessing True if processing continues in background
     * @param  int $responseCode If set, output this response code.
     * @return void
     */
    public static function localRedir($file, $keepProcessing = false, $responseCode = null)
    {
        header('Location: ' . SitePage::conType() . $_SERVER['HTTP_HOST'] . $file, null, $responseCode);
        self::disableXssProtectionAfterForm();
        // don't quit
        if ($keepProcessing) {
            self::keepProcessingInBackground();
        } // quit
        else {
            exit;
        }
    }

    /**
     * Keeps the script running in the background, and switches off the ability
     * for the user to terminate the script. After this, no more output is sent.
     *
     * @param string $output Final output to send, if any
     */
    public static function keepProcessingInBackground($output = '')
    {
        ob_end_clean();
        header("Connection: close\r\n");
        ignore_user_abort();
        ob_start();
        echo $output;
        $size = ob_get_length();
        header("Content-Length: $size");
        // header("Content-Encoding: none\r\n"); // might be required to prevent gzip encoding header
        ob_end_flush();
        flush();
        session_write_close();
    }

    /**
     * Redirects to a broken image with a given size.
     *
     * @param int $width Width of image to show
     * @param int $height Height of image to show
     * @param int $code The HTTP error number
     */
    public static function redirectToBrokenImageOfSize($width, $height, $code = 500)
    {
        static::redirectToBrokenImage(
            $code,
            array('width' => $width, 'height' => $height,)
        );
    }

    /**
     * Redirects to a broken image with a given transform.
     *
     * @param string $transform_type A string corresponding to an Alaress_Image_Transformation_Abstract instance
     * @param string $transform A key associated with a list of transforms to execute upon an image
     * @param int $code The HTTP error number
     */
    public static function redirectToBrokenImageWithTransform($transform_type, $transform, $code = 500)
    {
        static::redirectToBrokenImage(
            $code,
            array('transform_type' => $transform_type, 'transform' => $transform,)
        );
    }

    /**
     * Redirects to a broken image.
     *
     * @param int $code The HTTP error number
     * @param array $options Options for the size and transformation of the
     *                       broken image.
     */
    public static function redirectToBrokenImage($code = 500, $options = array())
    {
        $query = array(
            'code' => true,
            'width' => true,
            'height' => true,
            'transform' => true,
            'transform_type' => true,
        );
        $options['code'] = $code;
        $options = array_intersect_key($options, $query);
        static::localRedir("/images/fail.php?" . http_build_query($options));
    }


    /**
     *
     * @param string $error An error message to display on the login page.
     * @param string $page If set, this page is set as the path to redirect to.
     */
    public function goLoginPage($error = '', $page = '')
    {
        // AJAX request? Don't ever output a page.
        // Output something with a 401 status code.
        if ($this->detectAjax()) {
            $this->outputJson(['error' => 'Unauthenticated'], false, 401);
            exit;
        }

        $this->setSite();
        $this->setFile(TEMPLATE_body, 'common/login.html');
        $this->setBlock(TEMPLATE_body, 'login_terms', 'login_terms_block');
        $this->setBlock(TEMPLATE_body, 'remember_me', 'remember_me_block');
        $this->setVar('TITLE', $this->config->get('title') . " - Login");
        if ($page && preg_match('#^[\w_=\-:\/.\&\?]+$#i', $page)) {
            $this->setVar('HIDDEN', htmlspecialchars($page, ENT_QUOTES));
        } elseif (preg_match('#^[\w_=\-:\/.\&\?]+$#i', $_GET['page'])) {
            $this->setVar('HIDDEN', htmlspecialchars($_GET['page'], ENT_QUOTES));
        } else {
            $this->setVar('HIDDEN', '');
        }

        $loginTerms = $this->config->get('login_terms_and_conditions');
        if ($loginTerms) {
            $this->setVar([
                'LOGIN_TERMS_AND_CONDITIONS' => $loginTerms,
                'LOGIN_TERMS_AND_CONDITIONS_ERROR' => $this->config->get('login_terms_and_conditions_error'),
            ]);
            $this->parse('login_terms_block', 'login_terms');
        } else {
            $this->setVar('login_terms_block', '');
        }

        if ($this->config->get('session_remember')) {
            $this->parse('remember_me_block', 'remember_me');
        } else {
            $this->setVar('remember_me_block', '');
        }

        /* Custom text for Parents' Forgotten Password link
         */
        $this->setBlock(TEMPLATE_body, 'forgotten_password', 'forgotten_password_block');

        $config = new \Schoolbox\Config\Loader();

        $forgottenPasswordText = $config->get('forgotten_password_text');
        $forgottenPasswordUrl = $config->get('forgotten_password_url');

        if (strlen($forgottenPasswordText) && strlen($forgottenPasswordUrl)) {
            $this->setVar(array(
                'LOGIN_FORGOTTEN_PASSWORD_URL' => $forgottenPasswordUrl,
                'LOGIN_FORGOTTEN_PASSWORD_TEXT' => $forgottenPasswordText
            ));

            $this->parse('forgotten_password_block', 'forgotten_password');
        } else {
            $this->setVar(array(
                'forgotten_password_block' => ''
            ));
        }

        /**
         * Support and Feedback custom link
         */
        $this->setBlock(TEMPLATE_body, 'feedback_link', 'feedback_link_block');

        $feedbackText = $config->get('feedback_text');
        $feedbackURL = $config->get('feedback_url');

        if (strlen(trim($feedbackText)) && strlen(trim($feedbackURL))) {
            $this->parse('feedback_link_block', 'feedback_link');
        } else {
            $this->setVar('feedback_link_block', '');
        }

        /**
         * Alaress Google Analytics
         */
        $this->setBlock(TEMPLATE_body, 'google_analytics', 'google_analytics_block');
        $optIn = $config->get('site_usage_tracking');

        $this->setVar([
            'SITE_SCHOOL_CODE' => $config->get('site_school_code'),
            'SITE_TYPE' => $config->get('site_type'),
            'SITE_VERSION' => $config->get('site_version'),
        ]);

        if ($optIn) {
            $this->parse('google_analytics_block', 'google_analytics');
        } else {
            $this->setVar('google_analytics_block', '');
        }

        // Shove GET params (samlAuth and page at least) into the form
        $getParams = array_intersect_key($_GET, array('page' => true, 'samlAuth' => true));
        $this->setVar('GET_PARAMS', htmlspecialchars(http_build_query($getParams), ENT_QUOTES));
        if ($error) {
            $this->setVar('ERROR', $error);
        } else {
            $parts = parse_url($_SERVER['REQUEST_URI']);
            // Redirect to base path, with current URI in page param, if not
            // currently on the base path.
            if (!$page && !($parts['path'] == '/login/' || $parts['path'] == '/login')) {
                if ($parts['path'] != '/') {
                    $this->localRedir('/login/?page=' . urlencode($_SERVER['REQUEST_URI']));
                } else {
                    $this->localRedir('/login/');
                }
            } else {
                // If there's a lasttime in the session and we're here, the
                // session must have timed out
                if (isset($_SESSION['lasttime'])) {
                    $error =
                        'Your session has expired. ' .
                        'Please log in below to continue browsing the portal.';
                }
                $this->setVar('ERROR', $error);
            }
        }

        // choose a background picture

        $backgrounds = explode(",", $this->config->get('skin_login_bg'));

        if (count($backgrounds) && $backgrounds[0]) {
            $this->setVar('LOGIN_BG', '/storage/image.php?hash=' . $backgrounds[array_rand($backgrounds)] . '&size=maxsize');
        } else {
            $this->setVar('LOGIN_BG', '/images/core/login-bg.jpg');
        }

        // is the login page sidebar-style?
        // TODO config this, for now just always on
        if (true) {
            $this->setVar('SIDE', 'side');
        } else {
            $this->setVar('SIDE', '');
        }

        // is SAML enabled?
        // don't show the login page, redirect to the SAML login page.
        // (unless ?samlLogin=0 is set)
        if ($this->config->get('saml_enabled') == '1' && (strlen($this->config->get('saml_sso_url')) || strlen($this->config->get('saml_idp_metadata_url'))) && $_GET['samlAuth'] !== '0') {
            $redirectUrl = '/saml/index.php';
            if (isset($_GET['page'])) {
                $redirectUrl .= '?page=' . urlencode($_GET['page']);
            }
            $this->localRedir($redirectUrl);
        } else {
            $this->outputPage(true);
        }
        exit;
    }

    /**
     * Render a 404 Page Not Found
     */
    public function pageNotFound()
    {
        http_response_code(404);

        $errorContents = $this->getTwig()->render('common/error/404.twig', []);

        $this->renderError('Page Not Found', $errorContents);
    }

    /**
     * @param $handle
     * @param $data
     * @param array $selected
     * @param string $template
     * @param array $mapping
     */
    public function checkList($handle, $data, $selected = array(), $template = TEMPLATE_content, $mapping = array())
    {
        $this->setBlock($template, $handle, $handle . '_block');
        $selected = is_array($selected) ? $selected : array();
        if (is_array($data) && count($data) > 0 || $data instanceof Traversable) {
            foreach ($data as $row) {
                if (is_array($row) || $row instanceof Traversable) {
                    foreach ($row as $key => $value) {
                        $mapping[$key] ? $key = $mapping[$key] : '';
                        $this->setVar($key, Scrub::text($value));
                        if (in_array($value, $selected)) {
                            $this->setVar($key . '_SELECTED', 'checked="checked"');
                        } else {
                            $this->setVar($key . '_SELECTED', '');
                        }
                    }
                } elseif (is_object($row)) {
                    //
                    // This is an object of some sort.
                    // SitePage is going to have to learn how to traverse models
                    // before it dies, because I'm not going to give every model
                    // array access.
                    foreach ($mapping as $property => $template_var) {
                        $get_method = 'get' . ucfirst($property);
                        $value = $row->$get_method();
                        $this->setVar($template_var, Scrub::text($value));
                        if (in_array($value, $selected)) {
                            $this->setVar($template_var . '_SELECTED', 'checked="checked"');
                        } else {
                            $this->setVar($template_var . '_SELECTED', '');
                        }
                    }
                }

                $this->parse($handle . '_block', $handle, true);

            }
        } else {
            $this->setVar($handle . '_block', '');
        }
    }

    /**
     * @param string $handle
     * @param array|Traversable $data
     * @param array $selected
     * @param string $template
     * @param array $mapping
     */
    public function dropDown($handle, $data, $selected = array(), $template = TEMPLATE_content, $mapping = array())
    {
        $this->setBlock($template, $handle, $handle . '_block');
        $selected = is_array($selected) ? $selected : array($selected);
        if ((is_array($data) && count($data) > 0) || $data instanceof Traversable) {
            foreach ($data as $row) {
                if (is_array($row) || $row instanceof Traversable) {
                    foreach ($row as $key => $value) {
                        $mapping[$key] ? $key = $mapping[$key] : '';
                        if (is_scalar($value) || is_null($value)) {
                            $this->setVar($key, Scrub::text($value));
                            if (in_array($value, $selected)) {
                                $this->setVar($key . '_SELECTED', 'selected="selected"');
                            } else {
                                $this->setVar($key . '_SELECTED', '');
                            }
                        }
                    }
                } elseif (is_object($row)) {
                    //
                    // This is an object of some sort.
                    // SitePage is going to have to learn how to traverse models
                    // before it dies, because I'm not going to give every model
                    // array access.
                    foreach ($mapping as $property => $template_var) {
                        $get_method = 'get' . ucfirst($property);
                        $value = $row->$get_method();
                        if (is_scalar($value) || is_null($value)) {
                            $this->setVar($template_var, Scrub::text($value));
                            if (in_array($value, $selected)) {
                                $this->setVar($template_var . '_SELECTED', 'selected="selected"');
                            } else {
                                $this->setVar($template_var . '_SELECTED', '');
                            }
                        }
                    }
                }

                $this->parse($handle . '_block', $handle, true);
            }
        } else {
            $this->setVar($handle . '_block', '');
        }
    }

    /**
     * @param $perm
     * @return bool
     */
    public function canRead($perm)
    {
        return $perm >= UserAccess::ACCESS_READ ? true : false;
    }

    /**
     * @param $perm
     * @return bool
     */
    public function canWrite($perm)
    {
        return $perm >= UserAccess::ACCESS_WRITE ? true : false;
    }

    /**
     * Disables XSS protections for the request immediately proceeding a form
     * submission. This gets around issue SB-1706, where a Youtube video in a
     * frame won't display after it has been submitted (but is fine after page
     * is refreshed).
     *
     * * If this is a form, XSS protection is removed for the NEXT page load.
     * * If the last page was a form, XSS protection is removed
     * * If the referrer contained "create" or "modify", protection is removed
     *
     * Want more information?
     *
     * @see  http://stackoverflow.com/questions/1547884/refused-to-execute-a-javascript-script-source-code-of-script-found-within-reque
     *
     * @todo consider using Content-Security-Policy header instead of this
     *
     * @return bool True if protection was disabled, false if not.
     */
    protected static function disableXssProtectionAfterForm()
    {
        // Is this a form? If so, disable for NEXT page load
        if (stripos($_SERVER['REQUEST_URI'], 'form.php') !== false) {
            $_SESSION['disableXssProtectionForNextPage'] = true;
            return true;
        }

        // Was the last page a form? If so, disable
        if (!empty($_SESSION['disableXssProtectionForNextPage'])) {
            unset($_SESSION['disableXssProtectionForNextPage']);
            header("X-XSS-Protection: 0\r\n");
            return true;
        }

        // Check referrer for if the last page was a create or modify page.
        unset($_SESSION['disableXssProtectionForNextPage']);
        if(isset($_SERVER['HTTP_REFERER'])) {
            $referer = parse_url($_SERVER['HTTP_REFERER']);
            if ($referer['host'] === $_SERVER['SERVER_NAME']) {
                foreach (array('create', 'modify') as $path) {
                    if (stripos($referer['path'], $path) !== false) {
                        header("X-XSS-Protection: 0\r\n");
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Attempt to (safely) parse $_SERVER for a URL component
     *
     * @param string $component
     * @return mixed
     */
    protected static function parseRequestUrl($component = null)
    {
        foreach (array('HTTP_HOST', 'SCRIPT_URI') as $option) {
            if (isset($_SERVER[$option])) {
                if ($result = parse_url($_SERVER[$option], $component)) {
                    return $result;
                }
            }
        }
        return 'localhost';
    }

    /**
     * Extends execution time limit.
     *
     * This is intended for page scripts that include uploads that are somehow
     * processed - often, their processing stage takes more than the default
     * max execution time.
     *
     * Of course the existence of this method (whether in SitePage or elsewhere!)
     * is an anti-pattern - any processing of an uploaded file should be done in
     * an asynchronous process, and if we did that then execution time limits
     * would be unnecessary.
     *
     * @param int $seconds Amount to extend by, defaults to 5 minutes.
     */
    public static function extendExecutionTime($seconds = 300)
    {
        $currentLimit = intval(ini_get('max_execution_time'));
        if ($currentLimit !== 0) {
            // Don't touch if there is no max execution time
            set_time_limit($currentLimit + $seconds);
        }
    }

    /**
     * Jam localisation vars into the template. Uncomment / modify the list as required
     */
    public function localise()
    {
        /** @var RosettaStone $l10n */
        $l10n = $this->getDi()->get('l10n');

        foreach ($l10n->getAllTerms() as $term) {

            $upperTerm = strtoupper($term);

            $this->setVar([
                "TPL_LOCAL_$upperTerm"             => $l10n->translate($term, false, false),
                "TPL_LOCAL_{$upperTerm}_PC"        => $l10n->translate($term, false, true),
                "TPL_LOCAL_{$upperTerm}_PLURAL"    => $l10n->translate($term, true, false),
                "TPL_LOCAL_{$upperTerm}_PLURAL_PC" => $l10n->translate($term, true, true),
                "TPL_LOCAL_{$upperTerm}_INDEF"     => $l10n->indefiniteArticle($term, false),
                "TPL_LOCAL_{$upperTerm}_INDEF_PC"  => $l10n->indefiniteArticle($term, true),
            ]);
        }
    }

    /**
     * Returns the localised version of this term. Convenience method.
     *
     * @param string $term
     * @param bool $plural
     * @param bool $capital Ca
     *
     * @return string
     */
    public function getLocalisedTerm($term, $plural = false, $capital = false)
    {
        /** @var RosettaStone $l10n */
        $l10n = $this->getDi()->get('l10n');

        return $l10n->translate($term, $plural, $capital);
    }


    /**
     * Convenience passthru
     *
     * @param string $name
     * @param array  $params
     * @return string
     */
    public function routeLookup($name, $params = [])
    {
        /** @var Router $router */
        $router = $this->getDi()->get('router');

        return $router->generate($name, $params);
    }
}
