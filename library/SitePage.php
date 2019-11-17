<?php

use Alaress\Web\Output;
use Aura\Router\Router;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\User;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

class SitePage extends Page
{
    /**
     * The site that this SitePage uses to get templates from
     * @var string
     */
    public $site;

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

    /** @var bool $ajax */
    private $ajax;

    /** @var bool $lite */
    private $lite;

    /** @var User $user */
    private $user;

    /** @var ConfigLoader */
    private $config;

    /**
     * Constructor
     *
     * @param bool                             $login
     * @param \Aura\Di\ContainerInterface|null $di
     */
    public function __construct($login = true, ?Aura\Di\ContainerInterface $di = null)
    {
        parent::__construct();

        // TODO SA-8354: ALWAYS ensure DI exists: that is, use the SitePage from DI or not at all
        if ($di) {
            $this->di = $di;
            $this->config = $di->get('config');
        } else {
            $this->config = ConfigLoader::getInstance();
        }

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



        $this->arg = array();
        $path = explode('/', trim($pageFile, '/'));

        $total = count($path);

        /* iterate backwards through the path */
        for ($i = 0; $i < $total; $i++) {
            $filename = rtrim(implode("/", $path), '/');

            if (is_file(WEB_root . '/' . $filename . '.php') && $execute) {
                include(WEB_root . '/' . $filename . '.php');
                return true;
            } elseif (is_file(WEB_root . '/' . $filename . '/index.php') && $execute) {
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
     * @return bool
     */
    public function setSite()
    {
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
            $this->saveHistory();
            /** @var Output\ThemedHtml $renderer */
            $renderer = $this->getDi()->get('webOutput:html');
            $renderer
                ->setTab($this->currentTab)
                ->setTitle($this->getVar('TITLE'));
        } else {
            /** @var Output\Html $renderer */
            $renderer = $this->getDi()->get('webOutput:nochrome');
        }
        $renderer->setTemplate('include/sitepageContent.twig');


        // Put the PHPLib together
        $this->setFile(TEMPLATE_body, 'include/chromeContent.html');
        $this->parse('MAIN_CONTENT_REGION', TEMPLATE_content);
        if ($this->getVar('custom_head')) {
            $this->parse('CUSTOM_HEADER_REGION', 'custom_head');
        } else {
            $this->setVar('CUSTOM_HEADER_REGION', '');
        }


        // Put the PHPLib vars back together
        // Some of these may be needed, some of them nay not
        if (!$this->user instanceof User) {
            $this->user = new User();
        }
        $templateVar = [
            'USER_ID'      => $this->user->getId(),
            'USER_ROLE'    => $this->user->getRole()->getName(),
            'SITE_NAME'    => $this->config->get('school_name'),
            'SITE_VERSION' => $this->config->get('site_version'),
        ];
        $this->setVar($templateVar);
        $this->localise();


        // Output the PHPLib using the renderer
        $renderer->setData([
            'content' => $this->template->parse('finaloutput', TEMPLATE_body)
        ]);

        // Do this leftover thing
        self::disableXssProtectionAfterForm();

        // Finagle a response
        $renderer->output($this->getDi()->get('webFactory')->newResponse());



        return true;
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
        if (PHP_SESSION_NONE === session_status()) {
            session_start();
        }

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
                $sessionHandler->disableLoginLog();

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
        $sessionLength = $this->config->get('session_length');
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
     * @param mixed  $ec      int:  retrieves error text from code from database
     *                        null: outputs whatever's in $message as error
     * @param string $message Replaces '%MESSAGE%' in $ec
     * @param int    $code    HTTP response code, defaults to 400 (Bad Request)
     * @throws SchoolboxException
     */
    public function error($ec, $message = '', $code = 400)
    {
        throw ErrorMapper::getInstance()->generateException($ec, null, $message, $code);
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
        $this->setVar('TITLE', $title);
    }

    /**
     * @param $template
     * @return bool|string
     */
    public function getTemplate($template)
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
     * Detects the presence of a .php extension in the request URI's path, and
     * redirects to the same page without the extension if it finds it.
     */
    public static function redirectToPageWithoutPhpExtension(): void
    {
        $parts = parse_url($_SERVER['REQUEST_URI']);

        // strip out .php from the path
        if (preg_match('[\\.php$]i', $parts['path'])) {
            $newUrl = substr($parts['path'], 0, -4);
            if (isset($parts['query'])) {
                $newUrl .= '?' . $parts['query'];
            }
            self::localRedir($newUrl);
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
    public function goLoginPage()
    {
        $parts = parse_url($_SERVER['REQUEST_URI']);
        if (!($parts['path'] == '/login/' || $parts['path'] == '/login')) {
            if ($parts['path'] != '/') {
                $this->localRedir('/login/?page=' . rawurlencode($_SERVER['REQUEST_URI']));
            } else {
                $this->localRedir('/login/');
            }
        }
        exit;
    }

    /**
     * Render a 404 Page Not Found
     */
    public function pageNotFound()
    {
        throw new NotFound();
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
        if ((is_array($data) && count($data) > 0) || $data instanceof Traversable) {
            foreach ($data as $row) {
                if (is_iterable($row)) {
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
                if (is_iterable($row)) {
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
        try {
            $router = $this->getDi()->get('router');
            return $router->generate($name, $params);
        } catch (\Aura\Di\Exception\ServiceNotFound $e) {
            return '';
        } catch (\Aura\Router\Exception\RouteNotFound $e) {
            return '';
        }
    }
}
