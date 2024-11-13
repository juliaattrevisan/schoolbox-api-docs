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
use Alaress\Session\Session;
use Alaress\Authorizer\Authorizer;
use Closure;
use Crell\ApiProblem\ApiProblem;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Sentry\State\HubInterface;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;
use Schoolbox\Url\Uri;
use Twig\Error\Error as TwigError;

abstract class Base
{
    /**
     * A PSR-7 compliant request object, which represents the current incoming
     * request.
     */
    protected ServerRequestInterface $request;

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
     * A PSR-7 compliant response object.
     *
     * If this is set, a response will be output from this object; otherwise, an
     * empty response will be output (and a warning raised).
     */
    protected ?ResponseInterface $response = null;

    /**
     * A custom response factory, designed for building PSR-7 compliant response
     * objects.
     */
    protected ResponseFactory $responseFactory;

    /**
     * A list of callbacks to be called after output has been sent to the
     * browser.
     *
     * @var array<callable>
     */
    protected array $postOutputCallbacks = [];

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
    protected $user;


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
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @param ServerRequestInterface $request
     * @return $this
     */
    public function setRequest(ServerRequestInterface $request): self
    {
        $this->request = $request;
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
     * @return ResponseInterface|null
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * @param ResponseInterface|null $response
     */
    public function setResponse(?ResponseInterface $response): void
    {
        $this->response = $response;
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
     * @return array
     */
    public function getPostOutputCallbacks(): array
    {
        return $this->postOutputCallbacks;
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
        return $this->isJsonSpec->isSatisfiedBy($this->request);
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
     * Build a JSON response from `$data`.
     *
     * @param mixed $data
     * @return ResponseInterface
     */
    protected function responseAsJson(mixed $data): ResponseInterface
    {
        return $this->responseFactory->buildJson($data);
    }

    /**
     * Shortcut method for building a chromeless HTML response.
     *
     * @param string $template A Twig template to render.
     * @param array  $data     Data to inject into the template.
     * @return ResponseInterface
     * @throws TwigError if something goes wrong when rendering the template
     */
    protected function responseFromTemplateUnthemed(string $template, array $data): ResponseInterface
    {
        return $this->responseFactory->buildUnthemedHtml($template, $data, $this->user);
    }

    /**
     * Shortcut method for building a HTML response from streamed data.
     *
     * @see \Alaress\Web\Response\TwigCallbackStream::__construct for further
     * parameter documentation.
     *
     * @param string      $template
     * @param Closure     $dataCallback
     * @param array       $dataCallbackArguments
     * @param string|null $headerTemplate
     * @param string|null $footerTemplate
     * @param array       $headerFooterData
     * @return ResponseInterface
     */
    protected function responseFromTemplateStreamedData(
        string $template,
        Closure $dataCallback,
        array $dataCallbackArguments,
        ?string $headerTemplate = null,
        ?string $footerTemplate = null,
        array $headerFooterData = [],
    ): ResponseInterface {
        return $this->responseFactory->buildHtmlFromStream(
            $template,
            $dataCallback,
            $dataCallbackArguments,
            $this->user,
            $headerTemplate,
            $footerTemplate,
            $headerFooterData,
        );
    }

    /**
     * Shortcut method for building a chromed HTML response.
     *
     * @param string $template A Twig template to render.
     * @param array  $data     Data to inject into the template.
     * @param string $title    If not empty: use this as the page title.
     * @param string $tab      If not empty: use this as the selected navigation
     *                         tab.
     * @return ResponseInterface
     * @throws TwigError if something goes wrong when rendering the template
     */
    protected function responseFromTemplate(
        string $template,
        array $data,
        string $title = '',
        string $tab = '',
    ): ResponseInterface {
        return $this->responseFactory->buildHtml($template, $data, $this->user, $title, $tab);
    }

    /**
     * Shortcut method for building a HTTP "201 Created" response, with a
     * redirect to a URL at which the created item may be found.
     *
     * Added because redirects with a 201 status code are a common pattern.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/201
     *
     * @param string $location The URL to redirect to.
     * @return ResponseInterface
     */
    protected function redirectCreated(string $location): ResponseInterface
    {
        return $this->responseFactory->buildRedirect($location, HttpResponse::HTTP_CREATED);
    }

    /**
     * Shortcut method for building a HTTP "301 Moved Permanently" response.
     *
     * The only difference between a 301 and 308 is that:
     * - a 301 response may be changed from POST to GET by a user agent
     *   (e.g. a browser)
     * - a 308 response may not have its verb changed
     *
     * When using this method, consider whether it is really what you need:
     * - a response to a POST request should be either a 201
     *   (`$this->redirectCreated()`) or 303 (`$this->redirectSeeOther()`)
     * - a 301 response to a GET request is acceptable, but IMO 308
     *   (`$this->redirectPermanent()`) is slightly preferable as it indicates
     *   the request verb may not change
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/301
     *
     * @param string $location The URL to redirect to.
     * @return ResponseInterface
     */
    protected function redirectMovedPermanently(string $location): ResponseInterface
    {
        return $this->responseFactory->buildRedirect($location, HttpResponse::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * Shortcut method for building a HTTP "302 Found" response.
     *
     * As with 301 vs. 308, the only difference between a 302 and 307 is that:
     * - a 302 response may be changed from POST to GET by a user agent
     *   (e.g. a browser)
     * - a 307 response may not have its verb changed
     *
     * When using this method, consider whether it is really what you need:
     * - a response to a POST request should be either a 201
     *   (`$this->redirectCreated()`) or 303 (`$this->redirectSeeOther()`)
     * - a 301 response to a GET request is acceptable, but IMO 307
     *   (`$this->redirectTemporary()`) is slightly preferable as it indicates
     *   the request verb may not change
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/302
     *
     * @param string $location The URL to redirect to.
     * @return ResponseInterface
     */
    protected function redirectFound(string $location): ResponseInterface
    {
        return $this->responseFactory->buildRedirect($location, HttpResponse::HTTP_FOUND);
    }

    /**
     * Alias for `redirectSeeOther`, named so that it is apparent that it should
     * be used as the redirect after a POST request is processed.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/303
     *
     * @param string $location The URL to redirect to.
     * @return ResponseInterface
     */
    protected function redirectAfterPost(string $location): ResponseInterface
    {
        return $this->redirectSeeOther($location);
    }

    /**
     * Shortcut method for building a HTTP "303 See Other" response.
     *
     * This response should not be cached: thus, the 'cache-control: none'
     * header will be set.
     *
     * This or 201 (`$this->redirectCreated()`) should be used to build a
     * redirect response after a `POST` request.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/303
     *
     * @param string $location The URL to redirect to.
     * @return ResponseInterface
     */
    protected function redirectSeeOther(string $location): ResponseInterface
    {
        return $this->responseFactory->buildRedirect($location, HttpResponse::HTTP_SEE_OTHER);
    }

    /**
     * Shortcut method for building a HTTP "307 Temporary Redirect" response.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/307
     *
     * @param string $location The URL to redirect to.
     * @return ResponseInterface
     */
    protected function redirectTemporary(string $location): ResponseInterface
    {
        return $this->responseFactory->buildRedirect($location, HttpResponse::HTTP_TEMPORARY_REDIRECT);
    }

    /**
     * Shortcut method for building a HTTP "308 Permanent Redirect" response.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/308
     *
     * @param string $location The URL to redirect to.
     * @return ResponseInterface
     */
    protected function redirectPermanent(string $location): ResponseInterface
    {
        return $this->responseFactory->buildRedirect($location, HttpResponse::HTTP_PERMANENTLY_REDIRECT);
    }

    /**
     * Shortcut method for building an empty response.
     *
     * @param int $statusCode Defaults to 204 for a No Content response.
     * @return ResponseInterface
     */
    protected function responseEmpty(int $statusCode = HttpResponse::HTTP_NO_CONTENT): ResponseInterface
    {
        return $this->responseFactory->build($statusCode);
    }

    /**
     * Shortcut method for building a generic response from the content of a
     * string.
     *
     * @param string $content
     * @param string|null $filename
     * @param string|null $mimetype
     * @return ResponseInterface
     */
    protected function responseFromContent(string $content, ?string $filename = null, ?string $mimetype = null): ResponseInterface
    {
        return $this->responseFactory->buildFromContent($content, $filename, $mimetype);
    }

    /**
     * Shortcut method for building a generic response from the content of a
     * stream.
     *
     * @param StreamInterface $stream
     * @param string|null $filename
     * @param string|null $mimetype
     * @return ResponseInterface
     */
    protected function responseFromStream(StreamInterface $stream, ?string $filename = null, ?string $mimetype = null): ResponseInterface
    {
        return $this->responseFactory->buildFromStream($stream, $filename, $mimetype);
    }

    /**
     * Convenience method for building a response, depending on what the request
     * specifies it accepts.
     * - JSON requests (accept: application/json) will have a JSON request made
     *   from `$data`
     * - XHR requests (x-requested-with: xmlhttprequest) will have an unthemed
     *   HTML response, rendered from `$template` with `$data`
     * - all other requests will have a chromed HTML response
     *
     * @param string $template A Twig template to render (if not JSON)
     * @param array  $data     Data to render directly (if JSON) or inject into
     *                         the template (if not JSON).
     * @param string $title    If not empty: use this as the page title (if not
     *                         JSON or XHR).
     * @param string $tab      If not empty: use this as the selected navigation
     *                         tab (if not JSON or XHR).
     * @return ResponseInterface
     * @throws TwigError if something goes wrong when rendering the template
     */
    protected function responseDependentOnRequest(
        string $template,
        array $data,
        string $title = '',
        string $tab = '',
    ): ResponseInterface {
        if ($this->isJsonRequest()) {
            // prefer JSON if specified... this isn't exactly correct, but it's close enough for now
            return $this->responseAsJson($data);
        }

        if ($this->isXhrRequest()) {
            // if the request is AJAX, don't return the chrome
            return $this->responseFromTemplateUnthemed($template, $data);
        }

        // full synchronous page
        return $this->responseFromTemplate($template, $data, $title, $tab);
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

        // if this isn't XHR, let the kernel and SitePage take care of
        if (!$this->isXhrRequest()) {
            throw $this->generateException($errorCode, null, $problem . implode(', ', $resolutions));
        }

        $this->response = $this
            ->responseFromTemplateUnthemed(
                'sub/mage-error.twig',
                [
                    'error' => [
                        'problem' => $problem,
                        'reasons' => $resolutions
                    ]
                ]
            )
            ->withStatus($statusCode);
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
        $this->response = $this
            ->responseAsJson($problem)
            ->withStatus($code);
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
