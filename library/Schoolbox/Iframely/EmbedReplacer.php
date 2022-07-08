<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Iframely;

use DiDom\Document;
use DiDom\Element;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Laminas\Uri\Uri;

/**
 * Replaces Iframely embeds within a HTML fragment with a version which does not
 * make requests to Iframely's CDN.
 *
 * Requests to this CDN are not free: thus instead of rendering HTML with links
 * to the CDN, the links should be converted to non-CDN links before rendering.
 * (The converted process itself requires requests to Iframely, thus this result
 * should then be cached.)
 */
class EmbedReplacer
{
    /**
     * The XPath statement which identifies an Iframely embed container within
     * the HTML.
     */
    private const IFRAMELY_EMBED_CONTAINER_ELEMENT_XPATH = '//div[@data-oembed-url]';

    /**
     * The XPath statement which identifies the actual embed iframe or other
     * source attribute's element within each embed container.
     */
    private const IFRAMELY_EMBED_CONTAINER_SOURCE_ELEMENT_XPATH = '//*[@src]';

    /**
     * The XPath statement which identifies an Iframely URL within each embed
     *
     */
    private const IFRAMELY_EMBED_CONTAINER_IFRAMELY_URL_ELEMENT_XPATH = '//*[@data-iframely-url]';

    /**
     * Iframely's CDN host: any embeds which contain this host name will be
     * replaced.
     */
    private const IFRAMELY_CDN_HOST = 'cdn.iframe.ly';

    /**
     * API endpoint for fetching URL information.
     *
     * @see https://iframely.com/docs/iframely-api
     */
    private const IFRAMELY_API_ENDPOINT = 'https://iframe.ly/api/iframely';

    /**
     * Additional parameters for the API endpoint.
     *
     * Currently sets the `iframe` parameter to 0, to disable Iframely's display
     * helpers and instead use the raw embedded URL.
     *
     * @see https://iframely.com/docs/parameters#iframe=0
     */
    private const IFRAMELY_API_PARAMS_NO_IFRAME = ['iframe' => 0];

    /**
     * Additional parameters for the API endpoint.
     *
     * Currently sets the `iframe` parameter to 0, to disable Iframely's display
     * helpers and instead use the raw embedded URL.
     *
     * @see https://iframely.com/docs/parameters#iframe=0
     */
    private const IFRAMELY_API_PARAMS_NO_SCRIPTS = ['omit_script' => 1];

    /**
     * Constructor
     *
     * @param Client $httpClient Required for making requests to Iframely's API
     *                           (for a version of the embed which does not use
     *                           Iframely's CDN)
     */
    public function __construct(private Client $httpClient)
    {
    }

    /**
     * Replace any Iframely embeds within `$html` with an embed which does not
     * pass through to Iframely's CDN.
     *
     * @param string $html
     * @return string
     * @throws GuzzleException if the Iframely API was unreachable
     */
    public function replace(string $html): string
    {
        // Empty HTML: just return the empty content.
        if ($html === '') {
            return $html;
        }

        // Load the purified content into a DOM document
        $doc = new Document($html, false, 'UTF-8');

        // Search for Iframely embeds (within data-oembed-url attributes).
        // If there are none, return early
        $embeds = $doc->xpath(self::IFRAMELY_EMBED_CONTAINER_ELEMENT_XPATH);
        if (count($embeds) === 0) {
            return $html;
        }

        // Process each embed
        $replacementsApplied = false;
        foreach ($embeds as $embed) {
            // TODO extract these out to composites?
            $replacementHtml = $this->findReplacementHtml($embed);
            // Replace the Iframely element's HTML with the new embed
            if (isset($replacementHtml)) {
                $replacementsApplied = true;
                $embed->setInnerHtml($replacementHtml);
//                continue;
            }

//            $replacementHtml = $this->findReplacementHtmlForIframelyUrlElements($embed);
//            if (isset($replacementHtml)) {
//                $replacementsApplied = true;
//                $embed->setInnerHtml($replacementHtml);
//            }
        }

        // Were any embeds replaced? If so, return the replaced HTML
        if (!$replacementsApplied) {
            return $html;
        }
        return $doc->first('body')->html();
    }

    /**
     * Determine what the replacement HTML should be for a source element within
     * an Iframely embed.
     *
     * @param Element $embed
     * @return string|null
     * @noinspection PhpDocMissingThrowsInspection - xpath is hardcoded
     */
    private function findReplacementHtml(Element $embed): ?string
    {
        // Search for: //*[@src], //*[@data-iframely-url]
        // Find all src attributes within the embed node:
        // if there are none, skip this embed
        /** @noinspection PhpUnhandledExceptionInspection - xpath is hardcoded */
        $nodes = array_merge(
            $embed->xpath(self::IFRAMELY_EMBED_CONTAINER_SOURCE_ELEMENT_XPATH),
            $embed->xpath(self::IFRAMELY_EMBED_CONTAINER_IFRAMELY_URL_ELEMENT_XPATH),
        );
        if (count($nodes) === 0) {
            return null;
        }

        // If there are src attributes, check if any of those URLs point to
        // the Iframely CDN.
        // If any do: make a separate call to the iframely API to get the
        // HTML embed which bypasses Iframely's CDN
        foreach ($nodes as $srcNode) {
            // Get embed without Iframely's iframe
            $srcUrl = new Uri($srcNode->getAttribute('src') ?? $srcNode->getAttribute('data-iframely-url'));
            if ($srcUrl->getHost() === self::IFRAMELY_CDN_HOST) {
                $apiUrls = [
                    (new Uri(self::IFRAMELY_API_ENDPOINT))
                        ->setQuery(
                            self::IFRAMELY_API_PARAMS_NO_IFRAME
                            + $srcUrl->getQueryAsArray()
                        ),
                    (new Uri(self::IFRAMELY_API_ENDPOINT))
                        ->setQuery(
                            self::IFRAMELY_API_PARAMS_NO_SCRIPTS
                            + $srcUrl->getQueryAsArray()
                        ),
                ];
                foreach ($apiUrls as $apiUrl) {
                    try {
                        $response = $this->httpClient->get($apiUrl->toString());
                    } catch (GuzzleException $e) {
                        // Something went wrong whilst making the new request,
                        // e.g. this request return a 417 Expectation Failed:
                        // this means Iframely doesn't know how to output this
                        // content without its embed.
                        // Try without scripts instead.
                        continue;
                    }
                    $responseBody = json_decode($response->getBody()->getContents(), true);
                    if (isset($responseBody['html'])) {
                        return $responseBody['html'];
                    }
                }
//                $apiUrl = (new Uri(self::IFRAMELY_API_ENDPOINT))
//                    ->setQuery(self::IFRAMELY_API_PARAMS_NO_IFRAME + $srcUrl->getQueryAsArray());
//                try {
//                    $response = $this->httpClient->get($apiUrl->toString());
//                } catch (GuzzleException $e) {
//                    // Something went wrong whilst making the new request,
//                    // e.g. this request return a 417 Expectation Failed:
//                    // this means Iframely doesn't know how to output this
//                    // content without its embed.
//                    // Try without scripts instead.
//                    continue;
//                }
            }
        }
        return null;
    }

    /**
     * Determine what the replacement HTML should be for a source element within
     * an Iframely embed.
     *
     * @param Element $embed
     * @return string|null
     * @noinspection PhpDocMissingThrowsInspection - xpath is hardcoded
     */
    private function findReplacementHtmlForSourceElements(Element $embed): ?string
    {
        // Find all src attributes within the embed node:
        // if there are none, skip this embed
        /** @noinspection PhpUnhandledExceptionInspection - xpath is hardcoded */
        $srcNodes = $embed->xpath(self::IFRAMELY_EMBED_CONTAINER_SOURCE_ELEMENT_XPATH);
        if (count($srcNodes) === 0) {
            return null;
        }

        // If there are src attributes, check if any of those URLs point to
        // the Iframely CDN.
        // If any do: make a separate call to the iframely API to get the
        // HTML embed which bypasses Iframely's CDN
        foreach ($srcNodes as $srcNode) {
            // Get embed without Iframely's iframe
            $srcUrl = new Uri($srcNode->getAttribute('src'));
            if ($srcUrl->getHost() === self::IFRAMELY_CDN_HOST) {
                $apiUrl = (new Uri(self::IFRAMELY_API_ENDPOINT))
                    ->setQuery(self::IFRAMELY_API_PARAMS_NO_IFRAME + $srcUrl->getQueryAsArray());
                try {
                    $response = $this->httpClient->get($apiUrl->toString());
                } catch (GuzzleException $e) {
                    // Something went wrong whilst making the new request,
                    // e.g. this request return a 417 Expectation Failed:
                    // this means Iframely doesn't know how to output this
                    // content without its embed.
                    // Anyway: just skip this conversion.
                    continue;
                }
                $responseBody = json_decode($response->getBody()->getContents(), true);
                if (isset($responseBody['html'])) {
                    return $responseBody['html'];
                }
            }
        }
        return null;
    }

    /**
     * Determine what the replacement HTML should be for an Iframely URL element
     * within an Iframely embed.
     *
     * @param Element $embed
     * @return string|null
     * @noinspection PhpDocMissingThrowsInspection - xpath is hardcoded
     */
    private function findReplacementHtmlForIframelyUrlElements(Element $embed): ?string
    {
        // Find all data-iframely-url attributes within the embed node:
        // if there are none, skip this embed
        /** @noinspection PhpUnhandledExceptionInspection - xpath is hardcoded */
        $iframelyUrlNodes = $embed->xpath(self::IFRAMELY_EMBED_CONTAINER_IFRAMELY_URL_ELEMENT_XPATH);
        if (count($iframelyUrlNodes) === 0) {
            return null;
        }

        // Now do something
        foreach ($iframelyUrlNodes as $srcNode) {
            // Get embed without Iframely's iframe
            $iframelyUrl = new Uri($srcNode->getAttribute('data-iframely-url'));
            if ($iframelyUrl->getHost() === self::IFRAMELY_CDN_HOST) {
                // Get the src, and figure out where it actually redirects
                // to in the end
                $response = $this->httpClient->get(
                    $iframelyUrl->toString(),
                    [
                        RequestOptions::ALLOW_REDIRECTS => [
                            'track_redirects' => true,
                            'on_redirect'     => function (...$args) {
                                var_dump($args);
                            }
                        ],
                    ],
                );


//                $apiUrl = (new Uri(self::IFRAMELY_API_ENDPOINT))
//                    ->setQuery(self::IFRAMELY_API_PARAMS + $srcUrl->getQueryAsArray());
//                try {
//                    $response = $this->httpClient->get($apiUrl->toString());
//                } catch (GuzzleException $e) {
//                    // Something went wrong whilst making the new request,
//                    // e.g. this request return a 417 Expectation Failed:
//                    // this means Iframely doesn't know how to output this
//                    // content without its embed.
//                    // Anyway: just skip this conversion.
//                    continue;
//                }
//                $responseBody = json_decode($response->getBody()->getContents(), true);
//                if (isset($responseBody['html'])) {
//                    return $responseBody['html'];
//                }
            }
        }

        return null;
    }
}
