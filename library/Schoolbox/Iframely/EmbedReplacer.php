<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Iframely;

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
     * Wrapper which is used for generating UTF-8 encoded HTML fragments.
     *
     * Required so that PHP's DOM manipulation is as forceful as possible about
     * making the document UTF-8.
     */
    private const HTML5_WRAPPER = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
            <head>
                <title></title>
                <meta charset="utf-8">
            </head>
            <body>%s</body>
        </html>
        HTML;

    /**
     * The XPath statement which identifies an Iframely embed container within
     * the HTML.
     */
    private const IFRAMELY_EMBED_CONTAINER_XPATH = '//div[@data-oembed-url]';

    /**
     * The XPath statement which identifies the actual embed iframe or other
     * source element within each embed container.
     */
    private const IFRAMELY_EMBED_CONTAINER_SOURCE_XPATH = '//*[@src]';

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
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(sprintf(self::HTML5_WRAPPER, $html));
        $xpath = new DOMXPath($doc);

        // Search for Iframely embeds (within data-oembed-url attributes).
        // If there are none, return early
        $embeds = $xpath->query(self::IFRAMELY_EMBED_CONTAINER_XPATH);
        if (!($embeds instanceof DOMNodeList && $embeds->count() > 0)) {
            return $html;
        }

        // Process each embed
        $replacementsApplied = false;
        foreach ($embeds as $embed) {
            // Type checks fail: can't do anything
            if (!($embed instanceof DOMNode && $embed->parentNode instanceof DOMNode)) {
                continue;
            }

            // Find all src attributes within the embed node:
            // if there are none, skip this embed
            $childMatches = $xpath->query(self::IFRAMELY_EMBED_CONTAINER_SOURCE_XPATH, $embed);
            if (!($childMatches instanceof DOMNodeList && $childMatches->count() > 0)) {
                continue;
            }

            // If there are src attributes, check if any of those URLs point to
            // the Iframely CDN.
            // If any do: make a separate call to the iframely API to get the
            // HTML embed which bypasses Iframely's CDN
            $replacementHtml = null;
            foreach ($childMatches as $childMatch) {
                if ($childMatch instanceof DOMNode) {
                    $srcNode = $childMatch->attributes->getNamedItem('src');
                    if (!$srcNode instanceof DOMAttr) {
                        continue;
                    }

                    // Get embed without Iframely's iframe
                    $srcUrl = new Uri($srcNode->value);
                    if ($srcUrl->getHost() === self::IFRAMELY_CDN_HOST) {
                        // Get the src, and do something with it
                        $apiUrl = (new Uri(self::IFRAMELY_API_ENDPOINT))
                            ->setQuery(self::IFRAMELY_API_PARAMS + $srcUrl->getQueryAsArray());
                        $response     = $this->httpClient->get($apiUrl->toString());
                        $responseBody = json_decode($response->getBody()->getContents(), true);
                        if (isset($responseBody['html'])) {
                            $replacementHtml = $responseBody['html'];
                            break;
                        }
                    }
                }
            }

            // Replace the Iframely element's HTML with the new embed
            if (isset($replacementHtml)) {
                $replacementsApplied = true;
                while ($embed->hasChildNodes()) {
                    $embed->removeChild($embed->firstChild);
                }
                $replacement = new DOMDocument('1.0', 'UTF-8');
                $replacement->loadHTML(sprintf(self::HTML5_WRAPPER, $replacementHtml));
                foreach ($replacement->getElementsByTagName('body') as $body) {
                    if ($body instanceof DOMNode) {
                        foreach ($body->childNodes as $bodyChild) {
                            $embed->appendChild($doc->importNode($bodyChild, true));
                        }
                    }
                }
            }
        }

        // Were any embeds replaced? If so, return the replaced HTML
        if (!$replacementsApplied) {
            return $html;
        }
        $newHtml = '';
        foreach ($doc->getElementsByTagName('body') as $body) {
            if ($body instanceof DOMNode) {
                foreach ($body->childNodes as $bodyChild) {
                    $newHtml .= $doc->saveHTML($bodyChild);
                }
            }
        }
        return $newHtml;
    }
}
