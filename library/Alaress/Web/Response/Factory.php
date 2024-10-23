<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Response;

use Alaress\Web\Output\DataGenerator\IDataGenerator;
use Closure;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error as TwigError;

/**
 * Factory for building various variants of a response.
 */
class Factory implements ResponseFactoryInterface
{
    /**
     * Constructor
     *
     * @param ResponseFactoryInterface      $responseFactory A response factory
     * @param StreamFactoryInterface        $streamFactory   A stream factory
     * @param Environment                   $twig            A Twig environment
     * @param array<string, IDataGenerator> $dataGenerators  Data generators used for adding data into the template as
     *                                                       rendered by `$this->buildHtml`.
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private Environment $twig,
        private array $dataGenerators = [],
    ) {
    }

    /**
     * Create a new response using `$this->responseFactory`.
     *
     * @param int    $code
     * @param string $reasonPhrase
     * @return ResponseInterface
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return $this->responseFactory->createResponse($code, $reasonPhrase);
    }

    /**
     * Build an empty response.
     *
     * @param int    $statusCode
     * @param string $reasonPhrase
     * @return ResponseInterface
     */
    public function build(int $statusCode = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return $this->createResponse($statusCode, $reasonPhrase);
    }

    /**
     * Builds a redirect response.
     *
     * @param string $location   The URL to redirect to.
     * @param int    $statusCode The status code to redirect with.
     * @return ResponseInterface
     */
    public function buildRedirect(string $location, int $statusCode = Response::HTTP_FOUND): ResponseInterface
    {
        $response = $this
            ->createResponse($statusCode)
            ->withHeader('location', $location);

        // Disable caching for HTTP 303:
        // Aura.Web did this, I believe that the reason for that is to ensure
        // the new location gets fetched afresh.
        if ($statusCode === Response::HTTP_SEE_OTHER) {
            $response = $this->disableCaching($response);
        }

        return $response;
    }

    /**
     * Builds a JSON response.
     *
     * @param mixed $data
     * @return ResponseInterface
     */
    public function buildJson(mixed $data): ResponseInterface
    {
        return $this
            ->createResponse()
            ->withHeader('content-type', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($data)));
    }

    /**
     * Builds a response by rendering a Twig template with a data set.
     *
     * This will simply render the template as-is: it will not apply the site
     * chrome.
     *
     * @param string $template    A Twig template to render.
     * @param array  $data        Data to inject into the template.
     * @param User   $currentUser The currently authenticated user.
     * @return ResponseInterface
     * @throws TwigError if something goes wrong when rendering the template
     */
    public function buildUnthemedHtml(string $template, array $data, User $currentUser): ResponseInterface
    {
        $this->injectCurrentUserGlobal($currentUser);
        return $this->buildFromContent($this->twig->render($template, $data));
    }

    /**
     * Builds a response by rendering a Twig template with a data set within the
     * site chrome.
     *
     * @param string $template    A Twig template to render.
     * @param array  $data        Data to inject into the template.
     * @param ?User  $currentUser If provided, the current authenticated user:
     *                            this will override the `USER` global in Twig.
     * @param string $title       If not empty: use this as the page title.
     * @param string $tab         If not empty: use this as the selected
     *                            navigation tab.
     * @return ResponseInterface
     * @throws TwigError if something goes wrong when rendering the template
     */
    public function buildHtml(
        string $template,
        array $data,
        ?User $currentUser = null,
        string $title = '',
        string $tab = '',
    ): ResponseInterface {
        // Don't need to handle these cases, surely?
//        if (empty($template)) {
//            $code = $this->getResponse()->status->getCode();
//            if ($code === 404) {
//                $template = 'common/error/404.twig';
//            } else {
//                $template = 'common/error/general.twig';
//                $data     = [
//                    'date'    => Alaress_Date_Format::now(),
//                    'summary' => [
//                        'message' => 'Template not found',
//                    ],
//                ];
//            }
//        }

        $chromeData = [
            'tab'   => $tab,
            'title' => $title,
        ];
        $data = [
            'template' => $template,
            'data'     => $data,
            'chrome'   => $chromeData,
            'userIsModerator' => (
                $currentUser !== null &&
                $currentUser->hasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL)
            )
        ];
        foreach ($this->dataGenerators as $key => $generator) {
            $data[$key] = $generator->generate($chromeData);
        }

        if ($currentUser) {
            $this->injectCurrentUserGlobal($currentUser);
        }

        // Render it all out
        return $this->buildFromContent($this->twig->render('include/chrome.twig', $data));
    }

    /**
     * Build a response from the content of a data stream, each line of which is
     * then rendered via Twig.
     *
     * @see TwigCallbackStream::__construct for further parameter documentation.
     *
     * @param string      $template
     * @param Closure     $dataCallback
     * @param array       $dataCallbackArguments
     * @param User        $currentUser
     * @param string|null $headerTemplate
     * @param string|null $footerTemplate
     * @param array       $headerFooterData
     * @return ResponseInterface
     */
    public function buildHtmlFromStream(
        string $template,
        Closure $dataCallback,
        array $dataCallbackArguments,
        User $currentUser,
        ?string $headerTemplate,
        ?string $footerTemplate,
        array $headerFooterData,
    ): ResponseInterface {
        $this->injectCurrentUserGlobal($currentUser);
        return $this->buildFromStream(
            new TwigCallbackStream(
                $this->twig,
                $template,
                $dataCallback,
                $dataCallbackArguments,
                $headerTemplate,
                $footerTemplate,
                $headerFooterData,
            )
        );
    }

    /**
     * Build a response from the content of a string.
     *
     * @param string $content
     * @return ResponseInterface
     */
    public function buildFromContent(string $content): ResponseInterface
    {
        return $this->buildFromStream($this->streamFactory->createStream($content));
    }

    /**
     * Build a response from a stream.
     *
     * @param StreamInterface $stream
     * @return ResponseInterface
     */
    public function buildFromStream(StreamInterface $stream): ResponseInterface
    {
        return $this
            ->createResponse()
            ->withBody($stream);
    }

    /**
     * Helper method for disabling caching on a response.
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function disableCaching(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withoutHeader('cache-control')
            ->withHeader('cache-control', 'max-age=0, no-cache, no-store, must-revalidate, proxy-revalidate')
            ->withoutHeader('etag')
            ->withoutHeader('last-modified')
            ->withoutHeader('vary');
    }

    /**
     * Inject a user object into the Twig environment as a global variable.
     *
     * This overrides any previous value which the global might have had.
     *
     * @param User $currentUser The currently authenticated user.
     */
    private function injectCurrentUserGlobal(User $currentUser): void
    {
        $this->twig->addGlobal('USER', $currentUser);
    }
}
