<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Web\Controller;

use Alaress\Specification\ISpecification;
use Alaress\Web\Output;
use Aura\Di;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Session\SegmentInterface;
use Aura\Web\Request;
use Aura\Web\Response;
use Aura\Router\Router;
use Alaress\Session\Session;
use Alaress\Authorizer\Authorizer;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Raven_Client;

abstract class Base {
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var Di\Container
     */
    protected $di;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Authorizer
     */
    protected $authorizer;

    /** @var IExceptionGenerator */
    private $eg;

    /** @var ISpecification */
    private $isJsonSpec;

    /** @var ISpecification */
    private $isXhrSpec;

    /**
     * Null if the SB instance is not configured to use Sentry, or if unable to instantiate Sentry.
     *
     * @var null|Raven_Client
     */
    private $sentry;

    /** @var bool */
    private $allowBackToTheFuture = true;

    /**
     * @param  Request $request
     * @return $this
     */
    public function setRequest($request) {
        $this->request = $request;
        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest() {
        return $this->request;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setAllowBackToTheFuture($value) {
        $this->allowBackToTheFuture = $value;
        return $this;
    }

    /**
     * @return bool
     */
    public function getAllowBackToTheFuture() {
        return $this->allowBackToTheFuture;
    }

    /**
     * @param  Response $response
     * @return $this
     */
    public function setResponse($response) {
        $this->response = $response;
        return $this;
    }

    /**
     * @return Response
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * @param  Di\Container $di
     * @return $this
     */
    public function setDi($di) {
        $this->di = $di;
        return $this;
    }

    /**
     * @deprecated you should be injecting your dependencies from the constructor!
     *
     * @return Di\Container
     */
    public function getDi() {
        return $this->di;
    }

    /**
     * @param  Router $router
     * @return $this
     */
    public function setRouter($router) {
        $this->router = $router;
        return $this;
    }

    /**
     * @return Router
     */
    public function getRouter() {
        return $this->router;
    }

    /**
     * @param  Session $session
     * @return $this
     */
    public function setSession($session) {
        $this->session = $session;
        return $this;
    }

    /**
     * @return Session
     */
    public function getSession() {
        return $this->session;
    }

    /**
     * @param  Authorizer $authorizer
     * @return $this
     */
    public function setAuthorizer($authorizer) {
        $this->authorizer = $authorizer;
        return $this;
    }

    /**
     * @return Authorizer
     */
    public function getAuthorizer() {
        return $this->authorizer;
    }


    /**
     * @param ISpecification $isJsonSpec
     * @return $this
     */
    public function setIsJsonSpec(ISpecification $isJsonSpec): Base
    {
        $this->isJsonSpec = $isJsonSpec;
        return $this;
    }


    /**
     * @return bool
     */
    public function isJsonRequest(): bool
    {
        return $this->isJsonSpec->isSatisfiedBy($this->request);
    }


    /**
     * @param ISpecification $isXhrSpec
     * @return $this
     */
    public function setIsXhrSpec(ISpecification $isXhrSpec): Base
    {
        $this->isXhrSpec = $isXhrSpec;
        return $this;
    }


    /**
     * @return bool
     */
    public function isXhrRequest(): bool
    {
        return $this->isXhrSpec->isSatisfiedBy($this->request);
    }


    /**
     * Gets the current authenticated user out of the session.
     *
     * WARNING: Do not call this in post-request detached-client callbacks,
     *          as at that point the session has been destroyed.
     *
     * @return User
     */
    public function getUser() {
        return $this->getSession()->getAuthUser();
    }

    /**
     * Helper method for retrieving config.
     *
     * @return IConfigLoader
     */
    public function getConfigLoader()
    {
        return $this->getDi()->get('config');
    }

    /**
     * Retrieves a session segment, specific to whatever class this method was
     * called in.
     *
     * @return SegmentInterface
     */
    public function getSessionSegment()
    {
        return $this->getSession()->getSegment(static::class);
    }


    /**
     * Attempts to authorize a route for a user. Returns the URL for that route
     * if authorized, and false if not.
     *
     * @param  string $name   The name of the route to authorize and generate.
     * @param  array  $params Any params to pass to the route generator.
     * @param  User   $user   The user to authorize the route for. Defaults to
     *                        currently authenticated user.
     * @return string|bool    The URL for the route if authorized, false if not.
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getRouteIfAuthorized($name, array $params = array(), User $user = null) {
        if (!($user instanceof User)) {
            $user = $this->getUser();
        }
        if ($this->getAuthorizer()->isPathAuthorizedForUser($name, $params, $user)) {
            return $this->getRouter()->generate($name, $params);
        }
        return false;
    }




    /**
     * @param  string $name
     * @return self
     * @throws ServiceNotFound
     */
    protected function getController($name)
    {
        try {
            return $this->getDi()->get("controller:$name");

        } catch (ServiceNotFound $e) {
            // the controller isn't registered... hawkward. Let's remedy that

            /** @var \Alaress\Module\Module $module */
            $module = $this->getDi()->get("module:$name");

            // initialise the controller into the DI
            $module->initControllerDependencies();
            $module->initControllers($this->getDi());

            // remove this reference, we don't need it anymore
            unset($module);

            // obtain and return the controller from DI now that it's been registered
            return $this->getDi()->get("controller:$name");


        }
    }

    /**
     * Factory method for loading a JSON output object.
     *
     * @param  mixed $data
     * @return Output\Json
     */
    protected function buildJsonOutput($data)
    {
        /** @var Output\Json $output */
        $output = $this->getDi()->get('webOutput:json');
        return $output->setData($data);
    }

    /**
     * Factory method for loading a chromeless HTML output object.
     *
     * @param  string $template A Twig template
     * @param  mixed  $data
     * @return Output\Html
     */
    protected function buildUnthemedHtmlOutput($template, $data)
    {
        /** @var Output\Html $output */
        $output = $this->getDi()->get('webOutput:nochrome');
        return $output->setTemplate($template)->setData($data);
    }


    /**
     * Factory method for loading an xml output object
     *
     * @param $template
     * @param $data
     * @return mixed
     * @throws ServiceNotFound
     */
    protected function buildXmlOutput($template, $data)
    {
        /** @var Output\Xml $output */
        $output = $this->getDi()->get('webOutput:xml');
        return $output->setTemplate($template)->setData($data);
    }

    /**
     * Factory method for loading a chromed HTML output object.
     *
     * @param  string $template A Twig template
     * @param  mixed  $data
     * @param string  $title
     * @param string  $tab
     * @return Output\ThemedHtml
     * @throws ServiceNotFound If unable to locate HTML web Output
     */
    protected function buildHtmlOutput($template, $data, $title = '', $tab = '')
    {
        /** @var Output\ThemedHtml $output */
        $output = $this->getDi()->get('webOutput:html');
        $tpl = $output->setTemplate($template)->setData($data);

        if ($title) {
            $tpl->setTitle($title);
        }

        if ($tab) {
            $tpl->setTab($tab);
        }

        return $tpl;
    }

    /**
     * Factory method for loading a file output object.
     *
     * @param  string $filename
     * @return Output\File
     */
    protected function buildFileOutputFromFilename($filename)
    {
        /** @var Output\File $output */
        $output = $this->getDi()->get('webOutput:file');
        return $output->setFile($filename);
    }

    /**
     * Factory method for loading a file output object.
     *
     * @param  string $fileContents
     * @return Output\File
     */
    protected function buildFileOutputFromString($fileContents)
    {
        /** @var Output\File $output */
        $output = $this->getDi()->get('webOutput:base');
        return $output->setData($fileContents);
    }


    /**
     * Just a raw template render
     *
     * @param string $namespace
     * @param string $template
     * @param array  $data
     * @param string $tab
     */
    protected function tpl($namespace, $template, $data = [], $tab = '')
    {
        $content = $this->buildContent(
            "@$namespace/$template.twig",
            array_merge($data, ['currentUser' => $this->getUser()])
        );

        if ($content instanceof Output\ThemedHtml) {
            $content->setTab($tab);
        }

        $this->getResponse()->content->set($content);
    }


    /**
     * @param string $template
     * @param array  $data
     * @return \Alaress\Web\Output\Base
     */
    protected function buildContent($template, array $data)
    {
        if ($this->isJsonRequest()) {
            // prefer JSON if specified... this isn't exactly correct, but it's close enough for now
            return $this->buildJsonOutput($data);
        }

        if ($this->isXhrRequest()) {
            // if the request is AJAX, don't return the chrome
            return $this->buildUnthemedHtmlOutput($template, $data);
        }

        // full synchronous page
        return $this->buildHtmlOutput($template, $data);
    }


    /**
     * Send a neat and tidy error back to the client
     *
     * @param string|Exception $problem     either a ControllerException or a string describing what went wrong
     * @param array            $resolutions an array that contains a human-readable list of how to resolve the problem
     * @param int              $statusCode  the HTTP status code to send with this particular error
     * @param int              $errorCode   a database ID of an error to use for non-XHR requests
     * @throws Exception
     */
    protected function sendError($problem, array $resolutions = [], $statusCode = 400, $errorCode = 105)
    {
        // cast exception to primitives
        if ($problem instanceof Exception) {
            $resolutions = $problem->getReasons();
            $problem = $problem->getMessage();
        }

        $this->getResponse()->status->set($statusCode);

        // if this isn't XHR, let the kernel and SitePage take care of
        if (!$this->getRequest()->isXhr()) {
            throw $this->eg->generateException($errorCode, null, $problem . implode(', ', $resolutions));
        }

        $this->getResponse()->content->set([
            'template' => 'sub/mage-error.twig',
            'format' => 'nochrome',
            'data' => [
                'error' => [
                    'problem' => $problem,
                    'reasons' => $resolutions
                ]
            ]
        ]);
    }


    /**
     * @param IExceptionGenerator $eg
     */
    public function setExceptionGenerator(IExceptionGenerator $eg)
    {
        $this->eg = $eg;
    }


    /**
     * @param null|Raven_Client $sentry
     */
    public function setSentry(?Raven_Client $sentry)
    {
        $this->sentry = $sentry;
    }


    /**
     * Send exception off to whoever may be listening so we can fix it.
     *
     * Returns whether exception was able to be reported.
     *
     * Method signature is intentionally vague to abstract away exception reporting implementation
     * in the event we want to switch it out in the future.
     *
     * @param \Throwable $e    The exception you wish to report
     * @param array|null $data Any extra data you wish to include in report
     * @return bool
     */
    protected function captureException(\Throwable $e, array $data = null): bool
    {
        // Not installed?
        if (!$this->sentry instanceof Raven_Client) {
            return false;
        }

        // Suppress this inspection:
        // Sentry doesn't have proper typedefs anyway so it doesn't matter
        /** @noinspection PhpParamsInspection */
        $this->sentry->captureException($e, $data);
        return true;
    }
}
