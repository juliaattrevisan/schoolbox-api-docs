<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Response;

use Alaress\Web\Output\DataGenerator\IDataGenerator;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Twig\Environment;
use Twig\Error\Error as TwigError;

/**
 * Factory for building various variants of a response.
 */
class Factory
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
     * Builds a redirect response.
     *
     * @param string $location
     * @param int    $statusCode
     * @return ResponseInterface
     */
    public function buildRedirect(string $location, int $statusCode = 302): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse($statusCode)
            ->withHeader('location', $location);
    }

    /**
     * Builds a JSON response.
     *
     * @param object|array $data
     * @return ResponseInterface
     */
    public function buildJson(object|array $data): ResponseInterface
    {
        return $this->responseFactory
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
     * @param string $template A Twig template to render.
     * @param array  $data     Data to inject into the template.
     * @return ResponseInterface
     * @throws TwigError if something goes wrong when rendering the template
     */
    public function buildUnthemedHtml(string $template, array $data): ResponseInterface
    {
        return $this->buildFromContent($this->twig->render($template, $data));
    }

    /**
     * Builds a response by rendering a Twig template with a data set within the
     * site chrome.
     *
     * @param string $template A Twig template to render.
     * @param array  $data     Data to inject into the template.
     * @param string $title    If not empty: use this as the title of the page.
     * @param string $tab      If not empty: use this as the currently selected
     *                         navigation tab.
     * @return ResponseInterface
     * @throws TwigError if something goes wrong when rendering the template
     */
    public function buildHtml(string $template, array $data, string $title = '', string $tab = ''): ResponseInterface
    {
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
        ];
        foreach ($this->dataGenerators as $key => $generator) {
            $data[$key] = $generator->generate($chromeData);
        }

        // Render it all out
        return $this->buildFromContent($this->twig->render('include/chrome.twig', $data));
    }

    /**
     * Build a response from the content of a string.
     *
     * @param string $content
     * @return ResponseInterface
     */
    public function buildFromContent(string $content): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse()
            ->withBody($this->streamFactory->createStream($content));
    }
}
