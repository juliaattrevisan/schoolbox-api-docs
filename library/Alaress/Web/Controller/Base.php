<?php

/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Web\Controller;

use Alaress\Module\Registry;
use Alaress\Specification\ISpecification;
use Alaress\Web\Output;
use Aura\Session\SegmentInterface;
use Aura\Web\Exception\InvalidStatusCode;
use Aura\Web\Request;
use Aura\Web\Response;
use Aura\Router\Router;
use Alaress\Session\Session;
use Alaress\Authorizer\Authorizer;
use Crell\ApiProblem\ApiProblem;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Sentry\State\HubInterface;
use Throwable;
use Laminas\Uri\Uri;

abstract class Base
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @deprecated you should be injecting your dependencies from the constructor!
     */
    protected ContainerInterface $di;

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
     * The currently authenticated user.
     *
     * @var User
     */
    private $user;


    /**
     * Null if the SB instance is not configured to use Sentry, or if unable to instantiate Sentry.
     *
     * @var null|HubInterface
     */
    private $sentry;

    /** @var bool */
    private $allowBackToTheFuture = true;

    /**
     * @param  Request $request
     * @return $this
     */
    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setAllowBackToTheFuture($value)
    {
        $this->allowBackToTheFuture = $value;
        return $this;
    }

    /**
     * @return bool
     */
    public function getAllowBackToTheFuture()
    {
        return $this->allowBackToTheFuture;
    }

    /**
     * @param  Response $response
     * @return $this
     */
    public function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @deprecated you should be injecting your dependencies from the constructor!
     *
     * @param  ContainerInterface $di
     * @return $this
     */
    public function setDi(ContainerInterface $di)
    {
        $this->di = $di;
        return $this;
    }

    /**
     * @deprecated you should be injecting your dependencies from the constructor!
     *
     * @return ContainerInterface
     */
    public function getDi(): ContainerInterface
    {
        return $this->di;
    }

    /**
     * @param  Router $router
     * @return $this
     */
    public function setRouter($router)
    {
        $this->router = $router;
        return $this;
    }

    /**
     * @return Router
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * @param  Session $session
     * @return $this
     */
    public function setSession($session)
    {
        $this->session = $session;
        return $this;
    }

    /**
     * @return Session
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * @param  Authorizer $authorizer
     * @return $this
     */
    public function setAuthorizer($authorizer)
    {
        $this->authorizer = $authorizer;
        return $this;
    }

    /**
     * @return Authorizer
     */
    public function getAuthorizer()
    {
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
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }


    /**
     * @param User $user
     * @return $this
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }


    /**
     * Helper method for retrieving config.
     *
     * @return IConfigLoader
     */
    public function getConfigLoader()
    {
        /** @var IConfigLoader $config */
        $config = $this->getDi()->get('config');
        return $config;
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
    public function getRouteIfAuthorized($name, array $params = array(), User $user = null)
    {
        if (!($user instanceof User)) {
            $user = $this->getUser();
        }
        if ($this->getAuthorizer()->isPathAuthorizedForUser($name, $params, $user)) {
            return $this->getRouter()->generate($name, $params);
        }
        return false;
    }


    /**
     * Add given query string params from this request URL into the given path.
     *
     * @param string $path             A path, possibly generated with
     *                                `getRouteIfAuthorized` or similar.
     * @param array  $queryStringKeys  A list of keys to preserve from the
     *                                 current request's query string.
     *
     * @return string `$path` with this request's query string params injected.
     */
    protected function addQueryStringParamsToPath(string $path, array $queryStringKeys): string
    {
        $url   = new Uri($path);
        $query = $url->getQueryAsArray();
        foreach ($queryStringKeys as $queryStringKey) {
            $queryStringValue = $this->request->query->get($queryStringKey);
            if (isset($queryStringValue)) {
                $query[$queryStringKey] = $queryStringValue;
            }
        }
        return $url->setQuery($query)->toString();
    }


    /**
     * Retrieves another controller instance, so that this controller may access
     * actions provided by another controller.
     *
     * For convenience, this controller's user will be injected into the other
     * controller, though `setUser()` may be called on the other controller to
     * override that setting.
     *
     * @param  string $name The name of the controller, as defined in the
     *                      controller's module's basename.
     * @return self
     * @throws LogicException if module `$name` doesn't have a controller
     */
    protected function getController($name)
    {
        $module = Registry::buildModuleFromRegistration($this->di, $name);

        // initialise controller's routes and dependencies, and then build it
        $module->initControllerDependencies();
        $controller = $module->installControllerDependencies($module->buildController(), $this->di);
        $controller->setUser($this->user);
        return $controller;
    }

    /**
     * Factory method for loading a JSON output object.
     *
     * @param  mixed $data
     * @return Output\Json
     * @noinspection PhpDocMissingThrowsInspection The output object is always in DI
     * @noinspection PhpUnhandledExceptionInspection The output object is always in DI
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
     * @noinspection PhpDocMissingThrowsInspection The output object is always in DI
     * @noinspection PhpUnhandledExceptionInspection The output object is always in DI
     */
    protected function buildUnthemedHtmlOutput($template, $data)
    {
        /** @var Output\Html $output */
        $output = $this->getDi()->get('webOutput:nochrome');
        $output = $this->injectCurrentUserGlobal($output);
        return $output->setTemplate($template)->setData($data);
    }


    /**
     * Factory method for loading an xml output object
     *
     * @param string $template
     * @param string|array $data
     * @return mixed
     * @noinspection PhpDocMissingThrowsInspection The output object is always in DI
     * @noinspection PhpUnhandledExceptionInspection The output object is always in DI
     */
    protected function buildXmlOutput($template, $data)
    {
        /** @var Output\Xml $output */
        $output = $this->getDi()->get('webOutput:xml');
        $output = $this->injectCurrentUserGlobal($output);
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
     * @noinspection PhpDocMissingThrowsInspection The output object is always in DI
     * @noinspection PhpUnhandledExceptionInspection The output object is always in DI
     */
    protected function buildHtmlOutput($template, $data, $title = '', $tab = '')
    {
        /** @var Output\ThemedHtml $output */
        $output = $this->getDi()->get('webOutput:html');
        $output = $this->injectCurrentUserGlobal($output);

        /** @var Output\ThemedHtml $tpl */
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
     * @noinspection PhpDocMissingThrowsInspection The output object is always in DI
     * @noinspection PhpUnhandledExceptionInspection The output object is always in DI
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
     * @noinspection PhpDocMissingThrowsInspection The output object is always in DI
     * @noinspection PhpUnhandledExceptionInspection The output object is always in DI
     */
    protected function buildFileOutputFromString($fileContents)
    {
        /** @var Output\File $output */
        $output = $this->getDi()->get('webOutput:base');
        return $output->setData($fileContents);
    }

    /**
     * Parses a PSR-7 response into this controller's Aura response: this sets
     * the status code, headers and body from the PSR-7 response into the Aura
     * response.
     *
     * This relies upon the PSR-7 response's `getBody` method returning a
     * rewindable stream.
     *
     * @param ResponseInterface $response A PSR-7 HTTP response object.
     */
    protected function populateResponseFromPsr7Response(ResponseInterface $response): void
    {
        $this->response->status->set($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $this->response->headers->add($name, $value);
            }
        }
        $response->getBody()->rewind();
        $this->response->content->set(
            $this->buildFileOutputFromString($response->getBody()->getContents())
        );
    }

    /**
     * Just a raw template render
     *
     * @deprecated Use `buildHtmlOutput` or `buildUnthemedHtmlOuptut` instead
     *
     * @param string $namespace
     * @param string $template
     * @param array  $data
     * @param string $tab
     */
    protected function tpl($namespace, $template, $data = [], $tab = '')
    {
        $content = $this->buildContent("@$namespace/$template.twig", $data);

        if ($content instanceof Output\ThemedHtml) {
            $content->setTab($tab);
        }

        $this->getResponse()->content->set($content);
    }


    /**
     * @deprecated Use one of the `build*Output` methods instead, to build
     *             exactly the type of content you specify
     *
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
            throw $this->generateException($errorCode, null, $problem . implode(', ', $resolutions));
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
     * @param string $message
     * @param int    $code
     * @return void
     */
    protected function sendJsonError(string $message, int $code)
    {
        $problem = (new ApiProblem($message))
            ->setStatus($code)
            ->asArray();
        try {
            $this->getResponse()->status->setCode($code);
        } catch (InvalidStatusCode $e) {
            // noop
        }
        $this->getResponse()->content->set($this->buildJsonOutput($problem));
    }

    /**
     * Create an error exception that can be thrown and output by the kernel
     * @param int|null        $errorCode
     * @param Throwable|null $previous
     * @param string          $additionalInfo
     * @param int             $httpStatusCode
     * @return \Exception
     */
    protected function generateException(?int $errorCode, Throwable $previous = null, string $additionalInfo = '', int $httpStatusCode = 400)
    {
        return $this->eg->generateException($errorCode, $previous, $additionalInfo, $httpStatusCode);
    }

    /**
     * @param IExceptionGenerator $eg
     * @return $this
     */
    public function setExceptionGenerator(IExceptionGenerator $eg): self
    {
        $this->eg = $eg;
        return $this;
    }


    /**
     * @param null|HubInterface $sentry
     * @return $this
     */
    public function setSentry(?HubInterface $sentry): self
    {
        $this->sentry = $sentry;
        return $this;
    }


    /**
     * Send exception off to whoever may be listening so we can fix it.
     *
     * Returns whether exception was able to be reported.
     *
     * @param Throwable $e The exception you wish to report
     * @return bool
     */
    protected function captureException(Throwable $e): bool
    {
        // Not installed?
        if (!$this->sentry instanceof HubInterface) {
            return false;
        }
        $this->sentry->captureException($e);
        return true;
    }

    /**
     * Inject the controller's current user into the output object as a global
     * variable.
     *
     * This overrides any previous value which the global might have had.
     *
     * @param Output\Html $output
     * @return Output\Html
     */
    private function injectCurrentUserGlobal(Output\Html $output): Output\Html
    {
        $output->getTemplateEngine()->addGlobal('USER', $this->getUser());
        return $output;
    }

    /**
     * Extends the allowed execution time of this script, by default by another
     * 5 minutes.
     *
     * This is a copy of `\SitePage::extendExecutionTime`, with the same caveats
     * around its use: any process which has need of this should instead have
     * handed off to an asynchronous process, which notifies the user when it is
     * complete.
     *
     * @param int $seconds
     */
    protected function extendExecutionTime(int $seconds = 300)
    {
        $currentLimit = intval(ini_get('max_execution_time'));
        // Don't extend time limit if there is no current limit
        if ($currentLimit !== 0) {
            set_time_limit($seconds);
        }
    }
}
