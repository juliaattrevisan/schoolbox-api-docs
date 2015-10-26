<?php

use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;

class SitePage extends Page
{

    private $ajax;

    private $lite;

    /**
     * The dependency injection container, injected into SitePage!
     *
     * @var Aura\Di\Container
     */
    protected $di;

    /**
     * List of arguments passed in URL
     *
     * @var array
     */
    public $arg = array();


    public function SitePage($login = true)
    {
        parent::Page();

        $this->db = DeprecatedDatabase::getInstance();

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
     * @throws RuntimeException if Twig renderer wasn't found in this Sitepage
     *                          object's dependency injector.
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
        $contype = "http://";
        if (getenv("SERVER_PORT") == 443) {
            $contype = "https://";
        }

        return $contype;
    }


    /**
     * Get websocket port
     *
     * @return int
     */
    public function getWebsocketPort()
    {
        if ($this->conType() == 'https://') {
            return Cfg::get('websocket_port_ssl') ? : 8081;
        }

        return Cfg::get('websocket_port') ? : 8080;
    }


    function loadContent($pageFile, $execute = true)
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
        $path      = explode('/', trim($pageFile, '/'));

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


    function setSite()
    {
        // If config.skin_builder is on - set $site to core, thus bypassing all custom templates.
        if (Config::get('skin_builder')) {
            $this->site       = 'demo';
            $this->responsive = true;
            return true;
        }
        // detect responsiveness: if there's no override for body.html, that school has gone responsive
        $this->responsive = !file_exists(SITE_root . '/templates/' . Cfg::get('site_location') . '/include/body.html');

        if (Cfg::get('site_detect') == 'domain') {
            //Get site templates via domain name
            if (preg_match('/([\w]+)\./', $_SERVER['HTTP_HOST'], $match)) {
                $this->site = $match[1];
                return true;
            }
        } elseif (Cfg::get('site_detect') == 'campus') {
            //Get site template via users campus
            // MGS-specific code
            if (Cfg::get('site_location') == 'mgs') {
                // all staff are shown default MGS skin
                // exception: teachers (gid 1), who get their campus skin.
                $group = new Group();
                if ($_SESSION['gid'] != 1 && $group->isStaff()) {
                    $this->site = 'mgs-staff';
                } elseif ($_SESSION['campus'][0] == 3) {
                    $this->site = 'grimwade';
                } elseif ($_SESSION['campus'][0] == 2) {
                    $this->site = 'wadhurst';
                } else {
                    $this->site = 'mgs-senior';
                }

                return true;
            } else {
                $this->site = Cfg::get('site_location');
            }
        }

        $this->site = Cfg::get('site_location');
        return true;
    }


    public function getAjax()
    {
        return $this->ajax;
    }


    public function getLite()
    {
        return $this->lite;
    }


    function setAjax($state = true)
    {
        $this->ajax = $state;
    }

    function setLite($state)
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
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
    }


    /**
     * Outputs a JSON response, with appropriate header.
     *
     * @param mixed $output         Variable contents to output
     * @param bool  $keepProcessing True if processing continues after output.
     *                              Exits after output if false.
     * @param int   $responseCode   If set, output this response code.
     */
    public function outputJson($output, $keepProcessing = false, $responseCode = null)
    {
        header('Content-Type: application/json');
        if (isset($responseCode)) {
            header(':', true, $responseCode);
        }
        $jsonOutput = json_encode($output);
        if (!$keepProcessing) {
            echo $jsonOutput;
        } else {
            $this->keepProcessingInBackground($jsonOutput);
        }
    }


    function outputPage($lite = false)
    {
        if ($this->ajax) {
            return true;
        }

        if (!$lite && !$this->lite) {
            $this->buildPage();
            $this->saveHistory();
        }

        $userHandler = new User();

        $debug_bar_head = '';
        $debug_bar_body = '';

        if (Alaress\DebugBar\Instance::isEnabled()) {
            $renderer = Alaress\DebugBar\Instance::getInstance()->getJavascriptRenderer();
            $renderer->setBaseUrl('/debugbar/');
            $debug_bar_head = $renderer->renderHead();
            $debug_bar_body = $renderer->render();
        }

        $dateFormat  = Config::get('date_local');
        $templateVar = array(
            'TEMPLATE_YEAR'          => date('Y'),
            'SITE_NAME'              => Cfg::get('school_name'),
            'SITE_TITLE'             => Cfg::get('title'),
            'SITE_GREETING'          => TextFormatter::greeting(), // TODO: This should be an object
            'SITE_LOGINNAME'         => $userHandler->formatName($_SESSION),
            'SITE_USERNAME'          => $_SESSION['username'],
            'SITE_LOCATION'          => $this->site,
            'USER_ID'                => $_SESSION['id'],
            'EXTERNAL_ID'            => $_SESSION['sid'],
            'SITE_VERSION'           => Cfg::get('site_version'),
            'SCHOOL_ADDRESS'         => Cfg::get('school_address'),
            'DEBUGBAR'               => $debug_bar_body,
            'DEBUGBAR_HEAD'          => $debug_bar_head,
            'WEBSOCKET_HOST'         => self::parseRequestUrl(PHP_URL_HOST),
            'WEBSOCKET_PATH'         => Cfg::get('websocket_path') ? Cfg::get('websocket_path') : '/',
            'WEBSOCKET_PORT'         => $this->getWebsocketPort(),
            'WEBSOCKET_UNREAD_COUNT' => $_SESSION['msg-count'] ? : 0,
            'WEBSOCKET_UNREAD_LABEL' => $_SESSION['msg-count'] ? : '',
            'WEBSOCKET_STACK_LIMIT'  => \Schoolbox\Realtime\Notification\Decorator::STACK_LIMIT,
            'PAGE_SECURE'            => empty($_SERVER['HTTPS']) ? 'false' : 'true',
            'DATEPICKER_FORMAT'      => json_encode(Alaress_Date_Format_Converter::toDatepicker($dateFormat)),
            'MOMENTJS_FORMAT'        => json_encode(Alaress_Date_Format_Converter::toMomentJs($dateFormat)),
        );

        $this->setVar($templateVar);

        $title = $this->getVar('TITLE');

        if (empty($title)) {
            $this->setTitle();
        }
        $this->disableXssProtectionAfterForm();
        $this->template->pparse('finaloutput', TEMPLATE_body);
    }


    function buildPage($customHead = true)
    {

        $this->groupHandler = new Group;

        $this->setFile(TEMPLATE_body, 'include/body.html');
        $this->setFile('headerTpl', 'include/header.html');
        $this->setFile('footerTpl', 'include/footer.html');
        $this->setFile('bannerTpl', 'include/banner.html');
        $this->setFile('navTpl', 'include/nav.html');
        $this->setFile('tabTpl', 'include/tab.html');
        $this->setFile('instantTpl', 'include/instant.html');

        $this->renderScriptIncludes();
        $this->renderSideNav();
        $this->renderTabBar();
        $this->renderGroupList();
        $this->renderSubjectList();
        $this->renderLinkList();
        $this->renderStudents();
        $this->renderInstantMessages();

        $this->parse('MAIN_HEADER_REGION', 'headerTpl');
        $this->parse('MAIN_FOOTER_REGION', 'footerTpl');

        $this->setBlock($this->responsive ? TEMPLATE_body : 'bannerTpl', 'banner_logged_in', 'banner_logged_in_block');

        if ($_SESSION['id']) {

            /* Output superuser link to administration */
            $this->setBlock('banner_logged_in', 'admin_only_menu', 'admin_only_menu_block');
            if($_SESSION['user']->isSuperuser()) {
                $this->parse('admin_only_menu_block', 'admin_only_menu');
            }
            else {
                $this->setVar('admin_only_menu_block','');
            }

            $canSwitch = false;

            /* Output switch user options */
            $this->setBlock('banner_logged_in', 'switch_user', 'switch_user_block');

            if ($this->responsive) {
                $this->setBlock('banner_logged_in', 'can_switch', 'can_switch_block');
            }

            $this->setVar([
                'switch_user_block' => '',
                'can_switch_block'  => ''
            ]);
            $linkedUserList = array();

            $previousUser = $_SESSION['previous']['user'];
            foreach($_SESSION['user']->getLinkedUser() as $linkedUser) {
                // Don't link to this user if I was that user
                if ($previousUser instanceof \Schoolbox\User) {
                    if ($linkedUser->getId() == $previousUser->getId()) {
                        continue;
                    }
                }

                /* Fully hydrate, note this stores back in session so it only happens once */
                if($linkedUser->getRole()->getName() == null) {
                    $linkedUser = \Schoolbox\UserMapper::getInstance()->findById($linkedUser->getId());
                    $linkedUserList[] = $linkedUser;
                }

                $this->setVar(array(
                                    'MENU_SWITCH_USERID'        => $linkedUser->getId(),
                                    'MENU_SWITCH_EXTERNAL_ID'   => $linkedUser->getExternalId(),
                                    'MENU_SWITCH_USER_FULLNAME' => $linkedUser->getFullName(),
                                    'MENU_SWITCH_USER_ROLE'     => $linkedUser->getRole()->getName(),
                                    )
                );
                $this->parse('switch_user_block', 'switch_user', true);
                $canSwitch = true;
            }
            if(count($linkedUserList) > 0) {
                $_SESSION['user']->setLinkedUser($linkedUserList);
            }

            $this->setBlock('banner_logged_in', 'recover_user', 'recover_user_block');
            $this->setVar('recover_user_block', '');

            if($previousUser instanceof \Schoolbox\User) {
                $this->setVar(array(
                                    'MENU_SWITCH_USERID'        => $previousUser->getId(),
                                    'MENU_SWITCH_EXTERNAL_ID'   => $previousUser->getExternalId(),
                                    'MENU_SWITCH_USER_FULLNAME' => $previousUser->getFullName(),
                                    'MENU_SWITCH_USER_ROLE'     => $previousUser->getRole()->getName(),
                                    )
                );
                $this->parse('recover_user_block', 'recover_user', true);
                $canSwitch = true;
            }

            if ($canSwitch && $this->responsive) {
                $this->parse('can_switch_block', 'can_switch');
            }

            $this->parse('MAIN_NAV_REGION', 'navTpl');
            $this->parse('MAIN_TAB_REGION', 'tabTpl');
            $this->parse('banner_logged_in_block', 'banner_logged_in');
        } else {
            $this->setVar('MAIN_NAV_REGION', '');
            $this->setVar('MAIN_TAB_REGION', '');
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


    function setTab($tab)
    {
        $this->currentTab = $tab;
    }


    function initSession()
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

        if (!isset($_SESSION['id']) || //If session does not exist
            ( ($_SESSION['lasttime'] + \Config::get('session_length')) < time() ) || //If session has expired
            ( \Config::get('track_ip') && $_SESSION['ip'] != Session::getIP())  // If IP is not the same
        ) {
            //Catch users with the remember me cookie
            if (\Config::get('session_remember') && isset($_COOKIE['remember']) && $_COOKIE['remember']) {
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
            elseif (\Config::get('kerb_enable') && $_SERVER['REMOTE_USER']) {
                list($username, $domain) = explode('@', $_SERVER['REMOTE_USER']);
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
    }

    function saveHistory($override = '')
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


    function backToTheFuture($page)
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
     * @param mixed  $ec
     *                        Integer: retrieves error text from code from database
     *                        null:    outputs whatever's in $message as error
     * @param string $message Replaces '%MESSAGE%' in $ec
     * @param int    $code    HTTP response code, defaults to 400 (Bad Request)
     */
    function error($ec, $message = "", $code = 400)
    {
        if (isset($ec)) {
            $db    = DeprecatedDatabase::getInstance();
            $query = "SELECT message FROM error WHERE id = '$ec'";
            $error = $db->getRow($query);
        }
        if (empty($error)) {
            $error = array('message' => '%MESSAGE%',);
        }

        $output = str_replace("%MESSAGE%", $message, $error['message']);

        header(':', true, $code);

        // if we're in ajax mode, spit out in ajax-friendly (ie JSON) format
        if ($this->ajax) {
            header('Content-Type: application/json');
            die(json_encode($output));
        }

        $this->setVar(TEMPLATE_content, '');

        $this->setFile(TEMPLATE_content, 'common/error.html');
        $this->setTitle('Error Encountered');
        $this->setVar("CODE", $ec);
        $this->setVar("OUTPUT", $output);
        $this->buildPage(false);
        $this->outputPage(true);
        exit;

    }


    function renderSideNav()
    {
        $mapper = new \Schoolbox\SideMenu\Mapper();
        $tree   = $mapper->getCollectionAsTree($mapper->findByUser($_SESSION['user']));
        $renderer = new \Schoolbox\SideMenu\Renderer(new Scrub());
        $this->setVar("MCSSMENU", $renderer->renderTree($tree));
    }


    function renderTabBar()
    {

        //this section hanldes the display of the timetable tab
        $this->setBlock('tabTpl', 'timetable_tab', 'tt_tab');
        //if the have a timetable or their children have a timetable
        if ($_SESSION['timetable'] || is_array($_SESSION['children'][0]['subjects'])) {
            $this->parse('tt_tab', 'timetable_tab');
        } else {
            $this->setVar('tt_tab', '');
        }

        /* output submission box links */
        $this->setBlock('tabTpl', 'duework_tab', 'dw_tab');
        $this->setBlock('tabTpl', 'submission_tab', 'sm_tab');
        if ($this->groupHandler->isStaff()) {
            $this->parse('sm_tab', 'submission_tab');
            $this->setVar('dw_tab', '');
        } elseif ($this->groupHandler->isStudent()) {
            $this->parse('dw_tab', 'duework_tab');
            $this->setVar('sm_tab', '');
        } else {
            $this->setVar('sm_tab', '');
            $this->setVar('dw_tab', '');
        }

        $this->setBlock('tabTpl', 'parent_tab', 'p_tab');
        if ($this->groupHandler->isParent()) {
            $this->parse('p_tab', 'parent_tab');
        } else {
            $this->setVar('p_tab', '');
        }

        //this section handles the display of the webmail tab
        // if configuration states mail is available and user has access to local mail account display it
        $this->setBlock('tabTpl', 'webmail_tab', 'wm_tab');
        $this->setVar('wm_tab', '');

        if ($_SESSION['webmail']) {
            $location = ''; //Webmail location

            switch ($this->site) {
                case 'shore':
                    if ($this->groupHandler->isStaff()) {
                        $location = 'https://mail.shore.nsw.edu.au/exchange/';
                    } elseif ($this->groupHandler->isStudent()) {
                        if (strpos(Session::getIP(), '192.168.') === false) {
                            $location = 'https://mail.student.shore.nsw.edu.au/exchange/';
                        } else {
                            $location = 'http://cassandra.student.shore.nsw.edu.au/exchange/';
                        }
                    }
                    break;

                case 'moriah':
                    if ($this->groupHandler->isStaff()) {
                        $location = 'https://mail.moriah.nsw.edu.au/owa';
                    } elseif ($this->groupHandler->isStudent()) {
                        $location = 'http://www.google.com/a/student.moriah.nsw.edu.au/';
                    }
                    break;

                case 'oakburn':
                    if ($this->groupHandler->isStaff()) {
                        $location = 'https://mail.soc.tas.edu.au/owa/';
                    } elseif ($this->groupHandler->isStudent()) {
                        $location = 'https://www.outlook.com/my.soc.tas.edu.au';
                    }
                    break;

                case 'tigs':
                    if ($this->groupHandler->isStaff()) {
                        $location = 'https://webmail.tigs.nsw.edu.au/owa';
                    } elseif ($this->groupHandler->isStudent()) {
                        $location = 'https://mail.google.com/a/tigs.com.au';
                    }
                    break;

                case 'peninsula':
                    if ($this->groupHandler->isStaff()) {
                        $location = Cfg::get('mail_webmail');
                    } elseif ($this->groupHandler->isStudent()) {
                        $location = 'https://outlook.com/owa/tps.vic.edu.au';
                    }
                    break;

                default:
                    $location = Cfg::get('mail_webmail');
                    break;
            }

            /* Only set webmail tab if we have a location set and user has webmail enabled */
            if ($location) {
                $this->setVar('WEBMAIL_LOCATION', 'href="' . $location . '" rel="external"');
                $this->parse('wm_tab', 'webmail_tab');
            }
        }

        //custom code for each site is performed here
        if ($this->site == 'wenona') {
            require_once WEB_root . '/include/wenona.php';
            //provide encoded login details for intranet
            $this->setVar(
                "ENC_LOGIN",
                urlencode(
                    base64_encode(
                        xorEncrypt(
                            INTRANET_encryption_key,
                            $_SESSION['username'] . ' ' . $_SESSION['password'] . ' ' . date('Y m d H i')
                        )
                    )
                )
            );

            $this->setBlock('tabTpl', 'myfiles_tab', 'mf_tab');
            if ($this->groupHandler->isStaff() || $this->groupHandler->isStudent()) {
                $this->parse('mf_tab', 'myfiles_tab');
            } else {
                $this->setVar('mf_tab', '');
            }
        }
        if ($this->site == 'shore') {
            $this->setBlock('tabTpl', 'myfiles_tab', 'mf_tab');
            if ($this->groupHandler->isStaff()) {
                $this->setVar('MYFILES_LOCATION', 'https://myfiles.shore.nsw.edu.au/');
                $this->parse('mf_tab', 'myfiles_tab');
            } elseif ($this->groupHandler->isStudent()) {
                $this->setVar('MYFILES_LOCATION', 'https://myfiles.student.shore.nsw.edu.au/');
                $this->parse('mf_tab', 'myfiles_tab');
            } else {
                $this->setVar('MYFILES_LOCATION', '#');
                $this->setVar('mf_tab', '');
            }

            /* Remove these links if parent logged in:
               * Webmail, Resources, Network
               */
            if ($this->groupHandler->isParent()) {
                $this->setVar('wm_tab', '');

                $this->setBlock('tabTpl', 'resource_tab', 'res_tab');
                $this->setVar('res_tab', '');

                $this->setBlock('bannerTpl', 'user_name', 'usr_name');
                $this->setVar('usr_name', '');

            }

        } else {
            $this->setBlock('tabTpl', 'myfiles_tab', 'mf_tab');
            if ($this->groupHandler->isStaff() || $this->groupHandler->isStudent()) {
                $this->parse('mf_tab', 'myfiles_tab');
            } else {
                $this->setVar('mf_tab', '');
            }
        }

        /* Output webdav client tab if enabled */
        $this->setBlock('tabTpl', 'network_tab', 'network_tab_block');
        if (Cfg::get('webdav_enable') && !$this->groupHandler->isParent() && !$this->groupHandler->isGuest()) {
            $this->parse('network_tab_block', 'network_tab');
        } else {
            $this->setVar('network_tab_block', '');
        }

        /* Check if eportfolio tab should be enabled
                      */
        $this->setBlock('tabTpl', 'eportfolio_tab', 'ep_tab');

        if ($_SESSION['eportfolio_enabled']) {
            $this->parse('ep_tab', 'eportfolio_tab');
            $this->setVar('EPORTFOLIO_USER_ID', $_SESSION['id']);
        } else {
            $this->setVar('ep_tab', '');
        }

        // choose tab to highlight
        $output = array(
            'TAB_MAIN_ON'                                  => '',
            'TAB_NEWS_ON'                                  => '',
            'TAB_WEBMAIL_ON'                               => '',
            'TAB_CALENDAR_ON'                              => '',
            'TAB_TIMETABLE_ON'                             => '',
            'TAB_RESOURCES_ON'                             => '',
            'TAB_MYFILES_ON'                               => '',
            'TAB_NETWORK_ON'                               => '',
            'TAB_PORTFOLIO_ON'                             => '',
            'TAB_EPORTFOLIO_ON'                            => '',
            'TAB_SUBMISSION_ON'                            => '',
            'TAB_' . strtoupper($this->currentTab) . '_ON' => ' navthis',
        );

        $this->setVar($output);
    }


    function renderGroupList()
    {
        //this section handle mygroups section in the template
        $this->setBlock("navTpl", "mgroup", "mgroup_section");
        $this->setBlock("mgroup", "mgroup_block", "my_groups");

        // get overrides for this user
        $overrides = RerouteOverrideMapper::getInstance()->getMatchingRoutes(
            'service.'.HOMEPAGE_SERVICE,
            0,
            $_SESSION['id'],
            true
        )->map(function($v) {
            if (preg_match('`service.\d+.(\d+)`', $v->getRouteKey(), $matches)) {
                return (int)$matches[1];
            }
            return null;
        }, false)->getAll();

        $overrides = $overrides ?: array(0);

        $query = "
            SELECT cat.name, ua.sid, ua.type
            FROM userAccess AS ua
            INNER JOIN category AS cat ON ua.sid=cat.id AND cat.deleted_at IS NULL
            INNER JOIN homepage_config AS hc ON cat.id=hc.cid
            WHERE ua.uid = %d
            AND service = %d
            AND ua.sid NOT IN (%s)
            AND ua.type > 0
            ORDER BY cat.name
        ";
        $this->db->query(sprintf($query, $_SESSION['id'], HOMEPAGE_SERVICE, implode(', ', $overrides)));
        if ($this->db->countRows() > 0) {
            while ($group = $this->db->getNextArray()) {
                $this->setVar("MGNAME", Scrub::text($group['name']));
                $this->setVar("MGID", $group['sid']);
                $this->parse("my_groups", "mgroup_block", true);
            }
            $this->parse("mgroup_section", "mgroup");
        } else {
            $this->setVar("mgroup_section", "");
        }
    }


    /**
     * Render "My Subjects" list in the side navigation
     * (not to be confused with "My Groups")
     */
    public function renderSubjectList()
    {
        $renderer = new \Schoolbox\SubjectList\SideNav($this, $_SESSION['subjects']);
        $renderer->render();
    }


    function renderLinkList()
    {
        $this->setBlock("navTpl", "mlink_block", "my_links");
        $query = "SELECT url, description FROM bookmarks WHERE owner = '" . $_SESSION['id'] . "'";
        $this->db->query($query);
        if ($this->db->countRows() > 0) {
            while ($link = $this->db->getNextArray()) {
                $url = new Url($link['url']);

                if ($url->isLocalURL()) {
                    $this->setVar(
                        "MLINK", "<a href=\"" . $url->getRelativeURL() . "\"><span>" . Scrub::text($link['description']) . "</span></a>"
                    );
                } else {
                    $this->setVar(
                        "MLINK", "<a href=\"" . $url->getBase64EncodedRedirect() . "\" target=\"_blank\"><span>" . Scrub::text($link['description']) . "</span></a>"
                    );
                }

                $this->parse("my_links", "mlink_block", true);
            }
        } else {
            $this->setVar("my_links", "");
        }
    }


    public function renderStudents()
    {
        /* My students section
                       */
        $this->setBlock('navTpl', 'mstudent', 'mstudent_section');
        $this->setBlock('mstudent', 'mstudent_block', 'my_students');

        if (is_array($_SESSION['children'])) {
            $userHandler = new User();

            foreach ($_SESSION['children'] as $child) {
                $query = "SELECT title, firstname, lastname, year FROM user WHERE id = {$child['id']}";
                $user  = $this->db->getRow($query);
                $this->setVar("MSTUDENT_ID", $child['id']);
                $this->setVar("MSTUDENT_NAME", $userHandler->formatName($user, '{firstname}'));
                $this->setVar("MSTUDENT_YEAR", $user['year']);

                $this->parse("my_students", "mstudent_block", true);
            }

            $this->parse("mstudent_section", "mstudent");
        } else {
            $this->setVar("mstudent_section", "");
        }
    }


    function renderInstantMessages()
    {
        if ($this->responsive) {
            $this->setBlock(TEMPLATE_body, 'instantmessage_toggle', 'instantmessage_toggle_block');

            if (Cfg::get('messaging_enabled')) {
                $this->parse('instantmessage_toggle_block', 'instantmessage_toggle');
            } else {
                $this->setVar('instantmessage_toggle_block', '');
            }
        }

        if (Cfg::get('messaging_enabled')) {
            $this->parse('INSTANTMESSAGE_BLOCK', 'instantTpl');
        } else {
            $this->setVar('INSTANTMESSAGE_BLOCK', '');
        }
    }


    function renderScriptIncludes()
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


    function setJavascript($file)
    {
        $this->jscript[] = array('HEADER_SCRIPT_LOCATION' => $file);
    }


    function setCSS($file, $type = 'screen')
    {
        $this->css[] = array('HEADER_CSS_TYPE' => $type, 'HEADER_CSS_LOCATION' => $file);
    }


    function setFile($handle, $file)
    {
        if (!$this->template->setFile($handle, $this->getTemplate($file))) {
            return false;
        }

        if ($handle == TEMPLATE_content) {
            $this->templateFile = $file;
            $this->setBlock($handle, "custom_head", "custom_head_block");
            $this->setVar('custom_head_block', '');
        }

        return true;
    }


    function setTitle($title = '')
    {
        $this->setVar('TITLE', Cfg::get('title') . (!empty($title) ? ' - ' . Scrub::text($title) : ''));
    }


    function getTemplate($template)
    {
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


    function getVar($varname)
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
     * @param  bool   $keepProcessing True if processing continues in background
     * @param  int    $responseCode   If set, output this response code.
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
//        // header("Content-Encoding: none\r\n"); // might be required to prevent gzip encoding header
        ob_end_flush();
        flush();
        session_write_close();
    }


    /**
     * Redirects to a broken image with a given size.
     *
     * @param int $width  Width of image to show
     * @param int $height Height of image to show
     * @param int $code   The HTTP error number
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
     * @param string $transform      A key associated with a list of transforms to execute upon an image
     * @param int    $code           The HTTP error number
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
     * @param int   $code    The HTTP error number
     * @param array $options Options for the size and transformation of the
     *                       broken image.
     */
    public static function redirectToBrokenImage($code = 500, $options = array())
    {
        $query           = array(
            'code'           => true,
            'width'          => true,
            'height'         => true,
            'transform'      => true,
            'transform_type' => true,
        );
        $options['code'] = $code;
        $options         = array_intersect_key($options, $query);
        static::localRedir("/images/fail.php?" . http_build_query($options));
    }


    /**
     *
     * @param string $error An error message to display on the login page.
     * @param string $page  If set, this page is set as the path to redirect to.
     */
    function goLoginPage($error = '', $page = '')
    {
        $this->setSite();
        $this->setFile(TEMPLATE_body, 'common/login.html');
        $this->setBlock(TEMPLATE_body, 'remember_me', 'remember_me_block');
        $this->setVar('TITLE', Cfg::get('title') . " - Login");
        if ($page && preg_match('#^[\w_=\-:\/.\&\?]+$#i', $page)) {
            $this->setVar('HIDDEN', htmlspecialchars($page, ENT_QUOTES));
        } elseif (preg_match('#^[\w_=\-:\/.\&\?]+$#i', $_GET['page'])) {
            $this->setVar('HIDDEN', htmlspecialchars($_GET['page'], ENT_QUOTES));
        } else {
            $this->setVar('HIDDEN', '');
        }

        if (\Config::get('session_remember')) {
            $this->parse('remember_me_block', 'remember_me');
        } else {
            $this->setVar('remember_me_block', '');
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
        $options = array(Config::get('skin_login_bg'));

        if (count($options) && $options[0] ) {
            $bg = $options[array_rand($options)];
            $this->setVar('LOGIN_BG', '/images/logo.php?logo=skin_login_bg&size=normal');
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
        if (Config::get('saml_enabled') == '1' && strlen(Config::get('saml_sso_url')) && $_GET['samlAuth'] !== '0') {
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


    function pageNotFound()
    {
        header("HTTP/1.0 404 Not Found");
        $this->setVar(TEMPLATE_content, '');
        $this->setFile(TEMPLATE_content, 'common/404.html');
        $this->setTitle('Page Not Found');

        $this->buildPage(false);
        $this->outputPage(true);
        exit;
    }


    function checkList($handle, $data, $selected = array(), $template = TEMPLATE_content, $mapping = array())
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
                        $value      = $row->$get_method();
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


    function dropDown($handle, $data, $selected = array(), $template = TEMPLATE_content, $mapping = array())
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
                        $value      = $row->$get_method();
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


    function canRead($perm)
    {
        return $perm >= 1 ? true : false;
    }


    function canWrite($perm)
    {
        return $perm >= 2 ? true : false;
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
        $referer = parse_url($_SERVER['HTTP_REFERER']);
        if ($referer['host'] === $_SERVER['SERVER_NAME']) {
            foreach (array('create', 'modify') as $path) {
                if (stripos($referer['path'], $path) !== false) {
                    header("X-XSS-Protection: 0\r\n");
                    return true;
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
}
