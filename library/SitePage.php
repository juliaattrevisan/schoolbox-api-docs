<?php

use Alaress\Authorizer\Failure\Unauthenticated;
use Alaress\Sentry\Tracing\Tracer;
use Alaress\Sentry\Transaction\Manager;
use Alaress\Web\Response\Factory as ResponseFactory;
use Alaress\Web\Response\Outputter;
use Aura\Router\Generator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Auth\Remember;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\User;
use Sentry\Tracing\TransactionSource;

class SitePage
{
    /**
     * The site that this SitePage uses to get templates from.
     *
     * @var string
     */
    public string $site = '';

    /**
     * The dependency injection container, injected into SitePage!
     */
    protected ContainerInterface $di;

    /**
     * List of arguments passed in URL.
     *
     * @var array<string>
     */
    public array $arg = [];

    /**
     * The name of the currently selected tab in the top nav, if any.
     */
    private string $currentTab = '';

    /**
     * Is this page output as the response to an AJAX (i.e. async) request?
     */
    private bool $ajax = false;

    /**
     * Is this page output in "lite" mode (i.e. with no site chrome)?
     */
    private bool $lite = false;

    /**
     * The current logged-in user, if any.
     */
    private ?User $user = null;

    /**
     * This Schoolbox instance's config loader.
     */
    private IConfigLoader $config;

    /**
     * The root folder for templates which SitePage uses.
     */
    protected string $templateRoot;

    /**
     * The PHPLib templating engine SitePage uses.
     */
    public Template $template;

    /**
     * Constructor
     *
     * @param ContainerInterface $di
     */
    public function __construct(ContainerInterface $di)
    {
        $this->di           = $di;
        $this->config       = $di->get('config');
        $this->templateRoot = SITE_root . '/templates';
        $this->template     = new Template($this->templateRoot);

        if (isset($_SESSION['user'])) {
            $this->user = $_SESSION['user'];
        }

        $this->setSite();
    }

    /**
     * @return ContainerInterface
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * Gets a Twig renderer out of this sitepage's dependency injector,
     * if there is one.
     *
     * @return Twig\Environment
     * @throws RuntimeException if Twig renderer wasn't found in this Sitepage object's dependency injector.
     */
    public function getTwig()
    {
        $twig = $this->getDi()->get('templateEngine');
        if ($twig instanceof Twig\Environment) {
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
        // If HTTPS is terminated upstream, then site is still on HTTPS
        if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return "https://";
        }
        $type = "http://";
        if (getenv("SERVER_PORT") == 443) {
            $type = "https://";
        }

        return $type;
    }


    /**
     * @param string $pageFile
     * @param bool $execute
     * @return bool
     */
    public function loadContent($pageFile, $execute = true)
    {
        /* sanitise data, force all 404s through pageNotFound() */
        if (!preg_match("/^[a-zA-Z0-9\/\-\_\.]+/", $pageFile) || ($_GET['page'] ?? '') === 'common/404') {
            $this->pageNotFound();
        }

        $this->arg = array();
        $path = explode('/', trim($pageFile, '/'));

        $total = count($path);

        /** @var Manager $sentryTransactionManager */
        $sentryTransactionManager = $this->di->get('sentry.transactionManager');

        /* iterate backwards through the path */
        for ($i = 0; $i < $total; $i++) {
            $filename = rtrim(implode("/", $path), '/');

            // Start Sentry transaction here, now there's a path to include
            if (is_file(WEB_root . '/' . $filename . '.php') && $execute) {
                Tracer::getDefaultTrace()->setTransactionContextForSitePage(WEB_root . '/' . $filename . '.php');
                include WEB_root . '/' . $filename . '.php';
                return true;
            } elseif (is_file(WEB_root . '/' . $filename . '/index.php') && $execute) {
                Tracer::getDefaultTrace()->setTransactionContextForSitePage(WEB_root . '/' . $filename . '/index.php');
                include WEB_root . '/' . $filename . '/index.php';
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
        $this->site = $this->config->get('site_school_code');
        return true;
    }

    /**
     * @param bool $state
     */
    public function setAjax($state = true)
    {
        $this->ajax = $state;
    }

    /**
     * @param bool $state
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
     * Detects if a request has requested JSON output by setting the "Accept"
     * header to "application/json".
     *
     * @return bool
     */
    public static function detectJson()
    {
        return (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
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

        // Put the PHPLib together
        $this->setFile(TEMPLATE_body, 'include/chromeContent.html');
        $this->parse('MAIN_CONTENT_REGION', TEMPLATE_content);
        if ($this->getVar('custom_head')) {
            $this->parse('CUSTOM_HEADER_REGION', 'custom_head');
        } else {
            $this->setVar('CUSTOM_HEADER_REGION', '');
        }

        // Inject all global vars
        $this->injectGlobalVars();


        // Output the PHPLib using the renderer
        /** @var ResponseFactory $responseFactory */
        $responseFactory = $this->di->get('httpResponseFactory');
        $template = 'include/sitepageContent.twig';
        $data = [
            'content' => $this->template->parse('finaloutput', TEMPLATE_body)
        ];
        $user = $this->user ?? new User();
        if (!$lite && !$this->lite) {
            self::saveHistory();
            $response = $responseFactory->buildHtml(
                $template,
                $data,
                $user,
                $this->getVar('TITLE'),
                $this->currentTab,
            );
        } else {
            $response = $responseFactory->buildUnthemedHtml($template, $data, $user);
        }

        // Do this leftover thing
        self::disableXssProtectionAfterForm();

        // Finagle a response
        /** @var Outputter $outputter */
        $outputter = $this->di->get('httpResponseOutputter');
        $outputter->output($response);

        return true;
    }

    /**
     * @param string $tab
     */
    public function setTab($tab)
    {
        $this->currentTab = $tab;
    }

    /**
     * Initialises the session for the currently logged-in user.
     *
     * This method raises a redirect to the login page if any of the below are
     * true:
     * * the session hasn't been started
     * * the session ID is not valid
     *
     * Otherwise, if any of the below are true, this method checks the remember
     * me cookie for a user, and redirects to the login page if none is found:
     * * the session doesn't contain a logged-in user
     * * the user's login has expired
     * * the session's IP address doesn't match the current IP address
     *   (only if IP tracking for sessions is enabled)
     *
     * @deprecated this is definitely not a responsibility of SitePage
     */
    public function initSession()
    {
        // No session active? Go to the login page
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->goLoginPage();
        }

        // Validate the session ID - it has no right to be empty after it's started
        $sessionHandler = new Session();
        if (!$sessionHandler->validateSessionId(session_id())) {
            // Have a valid ID instead
            session_regenerate_id(true);
            $sessionHandler->destroy();
            $this->goLoginPage();
        }

        $sessionUser       = $_SESSION['user'] ?? null;
        $sessionExpired    = !isset($_SESSION['lasttime'])
            || (int)($_SESSION['lasttime'] + $this->config->get('session_length')) < time();
        $sessionIpMismatch = !($_SESSION[Session::BYPASS_IP_TRACKING] ?? false) && $this->config->get('track_ip') && ($_SESSION['ip'] ?? '') !== Session::getIP();
        if (!$sessionUser instanceof User || $sessionExpired || $sessionIpMismatch) {
            // Catch users with the remember me cookie
            if ($this->config->get('session_remember')) {
                /** @var Remember\Remember $rememberer */
                $rememberer = $this->di->get('session.rememberer');
                $username   = $rememberer->retrieveUsername();

                // For safety's sake regenerate their session ID, whether or not
                // the remember me was successful
                session_regenerate_id(true);
                if ($username === null || strlen($username) === 0 || !$sessionHandler->create($username)) {
                    $sessionHandler->destroy();
                    $this->goLoginPage();
                }
            } else {
                session_regenerate_id(true);
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
        $cookieParams = array_intersect_key(
            session_get_cookie_params(),
            ['path' => true, 'domain' => true, 'secure' => true, 'httponly' => true, 'samesite' => true],
        );
        $sessionLength = $this->config->get('session_length');
        $cookieParams['expires'] = ($sessionLength > 0)
            ? $_SERVER['REQUEST_TIME'] + $sessionLength
            : 0;
        setcookie(
            session_name(),
            session_id(),
            $cookieParams,
        );
    }

    /**
     * @param string $override
     */
    public static function saveHistory($override = '')
    {
        /* Function saves user page history in session so we can go back to where they came from */
        if (!(isset($_SESSION['history']) && is_array($_SESSION['history']))) {
            $_SESSION['history'] = array();
        }

        $requestUri = $override ? $override : $_SERVER['REQUEST_URI'];

        /* Dont handle duplicates (page refreshes) */
        if (!(isset($_SESSION['history'][0]) && $_SESSION['history'][0] == $requestUri)) {
            array_unshift($_SESSION['history'], $requestUri);

            if (count($_SESSION['history']) > 3) {
                array_pop($_SESSION['history']);
            }
        }
    }

    /**
     * @param int $page
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

    public function setVar($var, $value = '')
    {
        $this->template->setVar($var, $value);
    }

    public function setBlock($parent, $handle, $name = '')
    {
        $this->template->setBlock($parent, $handle, $name);
    }

    public function parse($target, $handle, $append = false)
    {
        $this->template->parse($target, $handle, $append);
    }

    /**
     * @param string $handle
     * @param string $file
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
     * @param string $template
     * @return bool|string
     */
    public function getTemplate($template)
    {
        $overrideFolder = strtolower($this->site);
        if (is_file($this->templateRoot . '/' . $overrideFolder . '/' . $template)) {
            return $overrideFolder . '/' . $template;
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
     * @param string $varname
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
     * @param  string|null $file           If null, redirect to dashboard
     * @param  bool        $keepProcessing True to keep processing in background
     * @param  int         $responseCode   If set, output this response code.
     * @return void
     */
    public static function localRedir($file, $keepProcessing = false, $responseCode = null)
    {
        // If somehow $file is empty, redir to the dashboard
        if (($file ?? '') === '') {
            $file = '/';
        }
        header('Location: ' . $file, true, $responseCode ?? 0);
        header('Content-Type:');
        self::disableXssProtectionAfterForm();
        if ($keepProcessing) {
            // don't quit
            self::keepProcessingInBackground();
        } else {
            // quit
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
        // Discard anything which might be in the buffer already:
        // SitePage-based pages might have output something unexpected
        ob_end_clean();

        // Output now
        // No content encoding because gzip interferes with content length
        header('Content-Encoding: none');
        header("Content-Length: " . strlen($output));
        echo $output;

        // Finish the request, and close the session
        fastcgi_finish_request();
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
            ['width' => $width, 'height' => $height,]
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
            ['transform_type' => $transform_type, 'transform' => $transform,]
        );
    }

    /**
     * Redirects to a broken image.
     *
     * @param int   $code    The HTTP error number
     * @param array $options Options for the size and transformation of the
     *                       broken image.
     */
    public static function redirectToBrokenImage($code = 500, $options = [])
    {
        static::localRedir(self::getBrokenImagePath($code, $options), false, 307);
    }

    /**
     * Generates a broken image path.
     *
     * @param int   $code    The HTTP error number
     * @param array $options Options for the size and transformation of the
     *                       broken image.
     * @return string
     */
    public static function getBrokenImagePath($code = 500, $options = []): string
    {
        $query           = [
            'code'           => true,
            'width'          => true,
            'height'         => true,
            'transform'      => true,
            'transform_type' => true,
        ];
        $options['code'] = $code;
        $options         = array_intersect_key($options, $query);
        return "/images/fail.php?" . http_build_query($options);
    }

    /**
     * Forces a redirect to the login page, by throwing an unauthenticated
     * exception.
     *
     * @throws Unauthenticated
     */
    public function goLoginPage()
    {
        try {
            throw new Unauthenticated();
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface | RuntimeException $e) {
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
    }

    /**
     * Forces a render of the 404 page, by throwing a not found exception.
     *
     * @throws NotFound
     */
    public function pageNotFound()
    {
        throw new NotFound();
    }

    /**
     * @param string $handle
     * @param mixed $data Any traversable; array, or collection of objects
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
                        isset($mapping[$key]) ? $key = $mapping[$key] : '';
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
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = parse_url($_SERVER['HTTP_REFERER']);
            if ($referer['host'] === $_SERVER['SERVER_NAME']) {
                foreach (array('create', 'modify') as $path) {
                    if (stripos($referer['path'] ?? '', $path) !== false) {
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
        // Don't extend time limit if there is no current limit
        if ($currentLimit !== 0) {
            set_time_limit($seconds);
        }
    }


    /**
     * Inject global vars required for Sitepage to do its thing.
     *
     * That currently includes:
     * * the user's ID and role into `USER_ID` and `USER_ROLE`
     * * the school name into `SITE_NAME`
     * * the Schoolbox version into `SITE_VERSION`
     * * the CDN prefix (so that stylesheets and images can be referenced)
     *   into `CDN_PREFIX`
     * * localisations
     */
    public function injectGlobalVars(): void
    {
        $user        = $this->user ?? new User();
        $templateVar = [
            'USER_ID'      => $user->getId(),
            'USER_ROLE'    => $user->getRole()->getName(),
            'SITE_NAME'    => $this->config->get('school_name'),
            'SITE_VERSION' => $this->config->get('site_version'),
        ];
        $this->setVar($templateVar);

        /** @var PrefixGenerator $cdnPrefixGenerator */
        $cdnPrefixGenerator = $this->getDi()->get('cdnPrefixGenerator');
        $this->setVar('CDN_PREFIX', $cdnPrefixGenerator->generate());
        $this->localise();
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
        try {
            /** @var Generator $generator */
            $generator = $this->getDi()->get('router.generator');
            return $generator->generate($name, $params);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            return '';
        } catch (\Aura\Router\Exception\RouteNotFound $e) {
            return '';
        }
    }
}
