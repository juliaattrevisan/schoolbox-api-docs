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
use Alaress\Web\Request\Values;
use Alaress\Web\Response\Factory as ResponseFactory;
use Aura\Router\Generator;
use Aura\Router\RouterContainer;
use Aura\Session\SegmentInterface;
use Aura\Web\Exception\InvalidStatusCode;
use Aura\Web\Response;
use Alaress\Session\Session;
use Alaress\Authorizer\Authorizer;
use Crell\ApiProblem\ApiProblem;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Sentry\State\HubInterface;
use Throwable;
use Schoolbox\Url\Uri;

abstract class Base
{
    /**
     * A PSR-7 compliant request object, which represents the current incoming
     * request.
     */
    protected ServerRequestInterface $psr7Request;

    /**
     * A values container, representing the parsed query string (a.k.a. $_GET).
     *
     * Provided in order to ease the transition from `Aura.Web` to PSR-7 request
     * objects.
     */
    protected Values $requestQuery;

    /**
     * A values container, representing $_POST data if provided.
     */
    protected Values $requestPost;

    /**
     * Response object from Aura.Web package.
     *
     * @deprecated This will be replaced with a PSR-7 compliant response object
     *             in `$this->psr7Response`.
     *             If that object is not set, the output mechanism will fall
     *             back to this object.
     */
    protected Response $response;

    /**
     * A PSR-7 compliant response object.
     *
     * If this is set: a response will be output from this object instead of
     * from `$this->response`.
     */
    protected ?ResponseInterface $psr7Response = null;

    /**
     * A custom response factory, designed for building PSR-7 compliant response
     * objects.
     */
    protected ResponseFactory $responseFactory;

    /**
     * @deprecated you should be injecting your dependencies from the constructor!
     */
    protected ContainerInterface $di;

    /**
     * Direct reference to the router object.
     *
     * Usually not required, as controllers usually only want to generate routes
     * (see `$this->routeGenerator`). However, some controllers may do atypical
     * things with the router itself, e.g.:
     * * finding the last matched route
     * * getting a list of all registered routes
     */
    protected RouterContainer $router;

    /**
     * Direct reference to the controller's route generator.
     */
    protected Generator $routeGenerator;

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

    /**
     * Specification for determining if this request expects JSON data to be
     * returned in the response.
     */
    private ISpecification $isJsonSpec;

    /**
     * Specification for determining if this request was made as an
     * XMLHttpRequest.
     *
     * @deprecated This header is sent only by older async request clients
     *             (e.g. jQuery AJAX), and should no longer be relied upon.
     *             Requests with the `Content-Type: application/json` header get
     *             their request body into `$this->request->getParsedBody()`,
     *             and requests which expect JSON to be returned should use
     *             `$this->isJsonSpec` to determine what to return.
     */
    private ISpecification $isXhrSpec;


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
     * @return ServerRequestInterface
     */
    public function getPsr7Request(): ServerRequestInterface
    {
        return $this->psr7Request;
    }

    /**
     * @param ServerRequestInterface $psr7Request
     * @return $this
     */
    public function setPsr7Request(ServerRequestInterface $psr7Request): self
    {
        $this->psr7Request = $psr7Request;
        return $this;
    }

    /**
     * @return Values
     */
    public function getRequestQuery(): Values
    {
        return $this->requestQuery;
    }

    /**
     * @param Values $requestQuery
     * @return $this
     */
    public function setRequestQuery(Values $requestQuery): self
    {
        $this->requestQuery = $requestQuery;
        return $this;
    }

    /**
     * @return Values
     */
    public function getRequestPost(): Values
    {
        return $this->requestPost;
    }

    /**
     * @param Values $requestPost
     * @return $this
     */
    public function setRequestPost(Values $requestPost): self
    {
        $this->requestPost = $requestPost;
        return $this;
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
     * @deprecated use `$this->psr7Response` instead
     *
     * @param  Response $response
     * @return $this
     */
    public function setResponse(Response $response): self
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @deprecated use `$this->psr7Response` instead
     *
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * @return ResponseInterface|null
     */
    public function getPsr7Response(): ?ResponseInterface
    {
        return $this->psr7Response;
    }

    /**
     * @param ResponseInterface|null $psr7Response
     */
    public function setPsr7Response(?ResponseInterface $psr7Response): void
    {
        $this->psr7Response = $psr7Response;
    }

    /**
     * @return ResponseFactory
     */
    public function getResponseFactory(): ResponseFactory
    {
        return $this->responseFactory;
    }

    /**
     * @param ResponseFactory $responseFactory
     * @return $this
     */
    public function setResponseFactory(ResponseFactory $responseFactory): self
    {
        $this->responseFactory = $responseFactory;
        return $this;
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
     * @param  RouterContainer $router
     * @return $this
     */
    public function setRouter(RouterContainer $router)
    {
        $this->router = $router;
        return $this;
    }

    /**
     * @return RouterContainer
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * @return Generator
     */
    public function getRouteGenerator(): Generator
    {
        return $this->routeGenerator;
    }

    /**
     * @param Generator $routeGenerator
     * @return $this
     */
    public function setRouteGenerator(Generator $routeGenerator): self
    {
        $this->routeGenerator = $routeGenerator;
        return $this;
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
     * Should this controller's response return JSON?
     *
     * @return bool
     */
    public function isJsonRequest(): bool
    {
        return $this->isJsonSpec->isSatisfiedBy($this->psr7Request);
    }


    /**
     * @deprecated
     * @param ISpecification $isXhrSpec
     * @return $this
     */
    public function setIsXhrSpec(ISpecification $isXhrSpec): Base
    {
        $this->isXhrSpec = $isXhrSpec;
        return $this;
    }


    /**
     * Was this request made as an XMLHttpRequest?
     *
     * @deprecated
     *
     * @return bool
     */
    public function isXhrRequest(): bool
    {
        return $this->isXhrSpec->isSatisfiedBy($this->psr7Request);
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
            return $this->routeGenerator->generate($name, $params);
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
        $url = new Uri($path);
        foreach ($queryStringKeys as $queryStringKey) {
            $queryStringValue = $this->requestQuery->get($queryStringKey);
            if (isset($queryStringValue)) {
                $url = $url->appendQueryParameters([$queryStringKey => $queryStringValue]);
            }
        }
        return (string)$url;
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
        if (!$this->isXhrRequest()) {
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
