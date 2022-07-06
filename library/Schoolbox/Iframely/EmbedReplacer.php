<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Iframely;

use DiDom\Document;
use DiDom\Query;
use DOMAttr;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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
    private const IFRAMELY_API_PARAMS = ['iframe' => 0];

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
            // Find all src attributes within the embed node:
            // if there are none, skip this embed
            $srcNodes = $embed->xpath(self::IFRAMELY_EMBED_CONTAINER_SOURCE_ELEMENT_XPATH);
            if (count($srcNodes) === 0) {
                continue;
            }

            // If there are src attributes, check if any of those URLs point to
            // the Iframely CDN.
            // If any do: make a separate call to the iframely API to get the
            // HTML embed which bypasses Iframely's CDN
            $replacementHtml = null;
            foreach ($srcNodes as $srcNode) {
                // Get embed without Iframely's iframe
                $srcUrl = new Uri($srcNode->getAttribute('src'));
                if ($srcUrl->getHost() === self::IFRAMELY_CDN_HOST) {
                    // Get the src, and do something with it
                    $apiUrl = (new Uri(self::IFRAMELY_API_ENDPOINT))
                        ->setQuery(self::IFRAMELY_API_PARAMS + $srcUrl->getQueryAsArray());
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
                        $replacementHtml = $responseBody['html'];
                        break;
                    }
                }
            }

            // Replace the Iframely element's HTML with the new embed
            if (isset($replacementHtml)) {
                $replacementsApplied = true;
                $embed->setInnerHtml($replacementHtml);
            }
        }

        // Were any embeds replaced? If so, return the replaced HTML
        if (!$replacementsApplied) {
            return $html;
        }
        return $doc->first('body')->html();
    }
}
