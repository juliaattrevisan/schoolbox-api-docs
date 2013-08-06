<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 *
 * This class is elsewhere known as Router / Front Controller / Mediator / Ultimate Authority On All Matters
 *
 * Standard Usage of this Mediator:
 *  - By default, construction will attempt to parse the 'url' field in the $_GET global.
 *    This can be overridden with the $url argument.
 *  - The url parsed will then be checked for an authorisation requirement: if required, authentication will be tested
 *  - Authentication is then checked.
 *
 *  - If anything went wrong in construction, the $response member will be populated with a Core_HTTPResponse
 *
 *  - Once constructed, outputResponse() should be called externally.
 *
 *  - outputResponse() passes through 4 levels of output generation and appends the necessary data to the $response member:
 *     1. data: generateResponse()
 *     2. site: generateSite()
 *     3. page: generatePage()
 *     4. dump: render() called on the View created and passd the completed $response object
 *
 *  - This has been specifically segmented as such because the independent processes will change based on context
 */

class Mediator {

    private $className;
    private $methodName;
    private $methodArgs;
    private $viewName;
    private $response;
    private $responseCode = Response_HTTP::HTTP_OK;

    public function __construct($url = '') {
        // general config
        set_error_handler(array($this, 'errorHandler'));
        session_set_cookie_params(MOBILE_SESSION_TIMEOUT);
        session_start();

        $regex = "#^/m/(.*)$#si";
        // echo ' == ', $_GET['url'], ' -> ', preg_replace($regex,"$1",$_GET['url']), " ==\n";

        // if default url is to be used, retrieve from $_GET
        // need to clear the prefixed 'm/' from the uri if it exists
        $url = $url ? $url : isset($_GET['url']) ? preg_replace($regex,"$1",$_GET['url']) : '';

        $router = new Router();

        // TODO: refactor the definitions of static reroutes somewhere else
        // register '/' reroute
        $router->setClassBase('\\Schoolbox\\Mobile\\');
        $router->setUseNamespaceClasses(true);
        $router->registerReroute('\\', array('Core\\Home', 'Framework'));

        // set a whole lot of vars to do with what will be returned and how
        try {
            $router->parseURL($url);
            $this->parseRoute($router);
        }
        catch (Exception_Schoolbox $e) {
            // generate failure response
            $this->setResponseCode(Response_HTTP::HTTP_BAD_REQUEST);
        }

        // THE GAUNTLET: you don't pass through here, you don't get access to *anything*...
        try {
            if(!$this->isAuthExcepted()) {
                $this->authenticate();
            }
        }
        // if authentication fails
        catch(Exception_Schoolbox_AccessDenied $e) {
            // create a dynamic reroute to substitute the destination page contents with the login page
            $router->registerReroute($this->getClassName(false), array('Core\\Auth', 'Login'));
            $router->reroute();

            $this->parseRoute($router);

            //$response->addInternalRedirectHeader('mobile/core/auth/login');
            $this->setResponseCode(Response_HTTP::HTTP_UNAUTHORIZED);
            return;
        }
        catch(Exception_Schoolbox $e) {
            echo $e->getTraceAsString();
            die("SBException: {$e->getErrorMessage()}");
        }
    }

    /**
     * perhaps a little too much encapsulation
     * @param Router $router
     */
    private function parseRoute(Router $router) {
        $this->setClassName($router->getClassName(false));
        $this->setMethodName($router->getMethodName());
        $this->setMethodArgs($router->getMethodArgs());
        $this->setViewName($router->getViewName());
    }

    /**
     * Pass credentials into the authenticator and behave accordingly
     * @param $forceReauth
     */
    public function authenticate($forceReauth = false) {
        // perform authentication
        $auth = new Authenticator();

        // if being forced to reauthenticate or not authenticated in the first place
        if($forceReauth || !$auth->isAuthenticated()) {

            // username and password set
            if( (isset($_POST['username']) && isset($_POST['password']) ) ) {
                $auth->reauthenticate($_POST['username'], $_POST['password']);
                // if there's an environmental indicator, push that into the session
                if(isset($_POST['env'])) {
                    // store this as an array since it will be being continuously [un]serialised
                    $_SESSION['env'] = self::detectDevice($_POST['env'])->toArray();
                }
            }
            // credentials not set: fail.
            else {
                throw new Exception_Schoolbox_AccessDenied('Credentials not supplied for authentication.');
            }
        }
    }

    /**
     * Step 1 of outputResponse()
     * Generates a HTTPResponse based on the Class, Method and Arguments stored in Mediator class members
     * The resultant response is stored in Mediator
     */
    private function generateResponse() {

        if($this->getResponse())
            return;

        // try to instantiate the class specified
        try {

            $classname  = $this->getClassName();
            $methodname = $this->getMethodName();
            $this->nameTransaction('/m/'.$classname.'->'.$methodname);

            // avoid fatal error by checking the class and method are registered
            $classname = '\\Schoolbox\\Mobile\\'.$classname;
            if (!class_exists($classname)) {
                throw new Exception_Schoolbox(sprintf("Class `$classname` does not exist", $classname));
            }
            $class = new $classname($this->getResponseCode());
            if (!is_callable(array($class, $methodname))) {
                throw new Exception_Schoolbox("Method `$methodname` does not exist in class `$classname`");
            }

            // try to dynamically call the method specified with the args specified
            try {
                // bad request or not authenticated
                if($this->getResponseCode() != Response_HTTP::HTTP_OK) {

                    if(!$this->isAuthExcepted()) {
                        throw new Exception_Schoolbox('Cannot generate response, response code is ' . $this->getResponseCode());
                    }
                }

                // THE FUNKY CALL THAT MAKES EVERYTHING GO!
                $this->setResponse(call_user_func_array(array($class, $methodname), $this->getMethodArgs()));

                /* this is for debugging, and only if the SQL server supports it anyway
                $db = DeprecatedDatabase::getInstance();
                $dbProf = $db->query('SHOW profiles');

                while($row = mysql_fetch_assoc($dbProf))
                    $a[] = $row;

                $this->getResponse()->setResponse('sqldump', $a);
                */
            }

            // catch method call failure
            catch (Exception_Schoolbox $e) {

                // response creation failed, so we need to create one and populate it with failure
                $obj = new Response_HTTP($this->getResponseCode());
                $obj->setError('code', $this->getResponseCode());
                $obj->setError("Failed to call method `{$this->getMethodName()}`", $e->getErrorMessage());
                $obj->setError('Backtrace', $e->getTraceAsString());

                $this->setResponse($obj);
            }

            // catch any non-fatal system errors
            catch (ErrorException $e) {
                // response creation failed, so we need to create one and populate it with failure
                $obj = new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
                $obj->setError('code', '404');
                $obj->setError("Failed to call method `{$this->getMethodName()}`", $e);
                $this->setResponse($obj);
            }
        }

        // catch class instantiation fail
        catch (Exception_Schoolbox $e) {

            // response creation failed, so we need to create one and populate it with failure
            $obj = new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
            $obj->setError('code', '404');
            $obj->setError("Failed to instantiate class `{$this->getClassName()}`", $e);

            $this->setResponse($obj);
        }
    }

    /**
     * Step 2 of outputResponse()
     * Manipulates the $response member to include site-specific data
     */
    private function generateSite() {
        $r = $this->getResponse();
        // make the response aware of what populated it
        $r->setCore('className',  $this->getClassName(false));
        $r->setCore('methodName', $this->getMethodName());
        $r->setCore('methodArgs', $this->getMethodArgs());
        //
        // Don't set the session ID unless user is actually logged in, because
        // it only applies to users who are.
        $auth = new Authenticator();
        if ($auth->isAuthenticated()) {
            $r->setCore('sessionId',  session_id());
        }

        // map simple site config variables
        $map = array(
            'title'      => 'title',
            'name'       => 'school_name',
            'address'    => 'school_address',
            'location'   => 'site_location',
            'hostname'   => 'site_hostname',
            'domain'     => 'site_domain',
            'nameFormat' => 'name_format',
            'version'    => 'site_version',
            'type'       => 'site_type',
        );

        foreach($map as $site => $db)
            $r->setSite($site, Config::get($db));

        // map the date display config variables
        $date = array(
            'timeFormat' => Config::get('calendar_time_format'),
            'long'       => Config::get('date_long'),
            'short'      => Config::get('date_short'),
            'database'   => Config::get('date_database'),
            'noTime'     => Config::get('date_notime'),
        );

        $r->setSite('date', $date);
    }

    /**
     * Step 3 of outputResponse()
     * Manipulates the $response member to include page-specific data
     * @param View_Interface $view
     */
    private function generatePage(View_Interface $view) {
        $r = $this->getResponse();
        // only set the view type if the called function didn't override it
        if(!$r->getPage('type')) {
            $r->setPage('type', $this->getViewName());
        }
        $r->setPage('skin', Mediator::detectSite());
        $r->setPage('basedir', '');

        $vars = $view->generatePage();

        if(is_array($vars)) {
            foreach($vars as $k => $v) {
                $r->setPage($k, $v);
            }
        }
    }

    /**
     * Sets the name of this page load for New Relic reporting.
     *
     * @todo This exact function is in SitePage, which means it can be moved
     *
     * For this class, this will usually be the name of the controller class,
     * joined with the method that is being called on that controller.
     *
     * @param string $name
     */
    protected function nameTransaction($name) {
        if (extension_loaded('newrelic')) {
            newrelic_name_transaction($name);
        }
    }

    /**
     * Conveyerbelt methodology.
     * Return the response to the client in the format requested
     */
    public function outputResponse() {

        // step 1: generate data for response IF the constructor DID NOT already generate an error response
        if(!$this->getResponse())
            $this->generateResponse();

        // intercept if a redirect was requested - don't bother going through the rest of generation
        if($this->getResponse()->getHeader('Location') != null)
            $this->getResponse()->sendHeaders();

        // step 2: generate site details for response
        $this->generateSite();

        // step 3: generate page for response based on the view determined
        if($this->getResponse()->getPage('type')) {
            $view = View_Factory::build($this->getResponse()->getPage('type'));
        }
        else {
            $view = View_Factory::build($this->getViewName());
        }
        $this->generatePage($view);

        // step 4: output view
        // send the http response code header
        header($this->getResponse()->getStatusCode());
        $view->render($this->getResponse());
    }


    /* SELF-CONTAINED CHECKING FUNCTIONS */

    /**
     * Function for testing whether or not the method parsed is allowed to bypass Authentication (eg. login page)
     * @return bool
     */
    private function isAuthExcepted() {
        // TODO: refactor this somehow.
        // Caution: any method added to this will bypass authentication checks.
        // This could probably be some kind of class registration, or listener, or something like that...
        $AUTH_EXCEPTION = array(
            'Core\\Auth\\API::Login',
            'Core\\Auth\\API::ForgotPassword',
            'Core\\Auth\\API::Logout',
            'Core\\Auth\\API::AmILoggedIn',
            'Core\\Settings\\API::Skin'
        );

        if( in_array("{$this->getClassName()}::{$this->getMethodName()}", $AUTH_EXCEPTION) ) {
            return true;
        }
        return false;
    }


    /* STATIC FUNCTIONS */

    /**
     * Concatenate function takes either an underscore-delimited string or an array and camelCases it.
     * Reinclusion of underscores and forcing the string to lowercase are done by default, but optional.
     *
     * @static
     * @param $pieces
     * @param bool $preserveUnderscores
     * @param bool $forceLower
     * @return array|string
     */
    public static function concatenate($pieces, $preserveUnderscores = true, $forceLower = true) {
        // if we've been passed a string, explode across underscore
        if (!is_array($pieces)) {
            $pieces = preg_split('/[_\\\\]/', $pieces);
        }

        // if we still only have a string, as opposed to an array, just return the string
        if(!is_array($pieces))
            return $pieces;

        // iterate through array and format
        foreach($pieces as &$piece) {
            // force lowercase unless explicity intended otherwise
            if($forceLower)
                $piece = strtolower($piece);
            $piece = ucfirst($piece);
        }
        return implode($preserveUnderscores ? '\\' : '', $pieces);
    }

    /**
     * Return the protocol that was used to establish this connection
     * @static
     * @return string
     */
    public static function getProtocol()
    {
        $map = array(
            '21' => 'ftp',
            '22' => 'sftp',
            '80' => 'http',
            '443' => 'https',
        );

        return (isset($map[getenv("SERVER_PORT")])) ? $map[getenv("SERVER_PORT")] : $map['80'];

    }

    /**
     * Device detection. Will always return a ClientDevice.
     * Logic can be replaced to change how device is determined.
     * @static
     * @return ClientDevice
     */

    public static function detectDevice($env) {
        return new ClientDevice($env);
    }


    /**
     * @static
     * @return mixed|string
     */
    public static function detectSite() {
        switch(Config::get('site_detect')) {
            case 'domain':
                if (preg_match('/([\w]+)\./', $_SERVER['HTTP_HOST'], $match)) {
                    return $match[1];
                }
                break;
            case 'campus':
                // for some reason these are hardcoded in SitePage...

                //Get site template via users campus
                if (Config::get('site_location') == 'mgs') {
                    if ($_SESSION['campus'][0] == 3) {
                        return 'grimwade';
                    }
                    elseif ($_SESSION['campus'][0] == 2) {
                        return 'wadhurst';
                    }
                    return 'mgs';
                }
                else {
                    return Config::get('site_location');
                }

            default:
                return Config::get('site_location');
        }
        return 'core';
    }

    /* GETTERS AND SETTERS */

    /**
     * @return Response_HTTP
     */
    public function getResponse() {
        return $this->response;
    }

    public function setResponse(Response_HTTP $rdo) {
        $this->response = $rdo;
    }

    public function getResponseCode() {
        return $this->responseCode;
    }

    public function setResponseCode($code) {
        $this->responseCode = $code;
    }

    public function getClassName($withSuffix = true) {
        return $this->className . ($withSuffix ?  '\\API' : '');
    }

    public function getMethodName() {
        return $this->methodName;
    }

    public function getMethodArgs() {
        // check this is ACTUALLY an array - inter-class manipulation tends to null this value
        if($this->methodArgs == null)
            return array();
        return $this->methodArgs;
    }

    public function getViewName() {
        return $this->viewName;
    }

    public function setClassName($className) {
        $this->className = $className;
    }

    public function setMethodName($methodName) {
        $this->methodName = $methodName;
    }

    public function setMethodArgs($methodArgs) {
        $this->methodArgs = $methodArgs;
    }

    public function setViewName($viewName) {
        $this->viewName = $viewName;
    }

    /**
     * Custom Error Handler for the Mediator to convert Errors to Exception
     * @param $errNum
     * @param $errString
     * @param $errFile
     * @param $errLine
     * @return bool
     * @throws ErrorException
     */
    public function errorHandler($errNum, $errString, $errFile, $errLine) {
        if (!(error_reporting() & $errNum)) {
            // This error code is not included in error_reporting
            return true;
        }
        throw new ErrorException($errString, 0, $errNum, $errFile, $errLine);
    }

}
