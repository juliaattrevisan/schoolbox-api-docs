<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Iframely;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Schoolbox\Url\Uri;

/**
 * Replaces Iframely embeds within a HTML fragment with a version which avoids
 * unnecessary requests to Iframely's CDN.
 *
 * Requests to this CDN are not free: thus instead of rendering HTML with links
 * to the CDN, the links should be converted to non-CDN links before rendering.
 * (The converted process itself requires requests to Iframely, thus this result
 * should then be cached.)
 */
class EmbedReplacer
{
    /**
     * The query selector which identifies an Iframely embed container within
     * the HTML.
     */
    private const IFRAMELY_EMBED_CONTAINER_ELEMENT_SELECTOR = 'div[data-oembed-url],oembed';

    /**
     * The attributes which may contain the source to create a link to within
     * each embed container.
     */
    private const IFRAMELY_EMBED_CONTAINER_SOURCE_ATTRIBUTES = ['src', 'data-iframely-url', 'url'];

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
     * A set of additional parameters for the API endpoint, to be attempted in
     * order.
     *
     * Currently:
     * * set `omit_script=1&iframe=0`, to bypass Iframely's display helpers and
     *   instead use the raw embedded URL
     * * then set just `omit_script=1`, to keep the iframe display helper but
     *   put it in an iframe instead
     *
     * @see https://iframely.com/docs/parameters#omit_script=1
     * @see https://iframely.com/docs/parameters#iframe=0
     */
    private const IFRAMELY_API_PARAMS_SETS = [
        ['omit_script' => 1, 'iframe' => 0],
        ['omit_script' => 1],
    ];

    /**
     * Constructor
     *
     * @param Client $httpClient Required for making requests to Iframely's API
     *                           (for a version of the embed which does not use
     *                           Iframely's CDN)
     * @param string $iframelyLicenseKey
     */
    public function __construct(private Client $httpClient, private string $iframelyLicenseKey)
    {
    }

    /**
     * Replace any Iframely embeds within `$html` with an embed which does not
     * pass through to Iframely's CDN.
     *
     * @param string $html
     * @return string
     */
    public function replace(string $html): string
    {
        // Empty HTML: just return the empty content.
        if (trim($html) === '') {
            return $html;
        }

        // Try to load the purified content into a DOM document
        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'utf-8');

        // Search for Iframely embeds (within data-oembed-url attributes).
        // If there are none, return early
        $embeds = $doc->querySelectorAll(self::IFRAMELY_EMBED_CONTAINER_ELEMENT_SELECTOR);
        if (count($embeds) === 0) {
            return $html;
        }

        // Process each embed
        $replacementsApplied = false;
        foreach ($embeds as $embed) {
            foreach (self::IFRAMELY_EMBED_CONTAINER_SOURCE_ATTRIBUTES as $attribute) {
                $replacementHtml = $this->findReplacementHtml($embed, $attribute);
                // Replace the Iframely element's HTML with the new embed
                if (isset($replacementHtml)) {
                    $replacementsApplied = true;
                    $replacementDoc      = HTMLDocument::createFromString($replacementHtml, LIBXML_NOERROR, 'utf-8');
                    $replacementNode     = $replacementDoc->querySelector('body')->firstChild;
                    $doc->adoptNode($replacementNode);
                    $embed->parentNode->replaceChild($replacementNode, $embed);
                    break;
                }
            }
        }

        // Were any embeds replaced? If so, return the replaced HTML
        if (!$replacementsApplied) {
            return $html;
        }

        // Figure out how to extract body
        $body = $doc->querySelector('body');
        return $body instanceof Element ? $body->innerHTML : $html;
    }

    /**
     * Determine what the replacement HTML should be for elements within an
     * Iframely embed.
     *
     * @param Node    $srcNode   The HTML element for the Iframely embed.
     * @param string  $attribute The attribute to search for within the embed.
     * @return string|null
     */
    private function findReplacementHtml(Node $srcNode, string $attribute): ?string
    {
        // If there are attributes, check if any of those URLs point to the
        // Iframely CDN.
        // If any do: hit the iframely API to get the HTML embed without the
        // iframe helper, or without scripts.
        $src = $srcNode->getAttribute($attribute);
        if (!isset($src)) {
            return null;
        }
        $srcUrl = new Uri($srcNode->getAttribute($attribute));

        foreach (self::IFRAMELY_API_PARAMS_SETS as $paramsSet) {
            $apiUrl = new Uri(self::IFRAMELY_API_ENDPOINT);
            if (strtolower($srcNode->tagName) === 'oembed') {
                // We are in an oembed ckeditor5 object so we need to add different query strings
                $apiUrl = $apiUrl->appendQueryParameters($paramsSet + ['api_key' => $this->iframelyLicenseKey, 'url' => $srcNode->getAttribute($attribute)]);
            } else if ($srcUrl->getHost() === self::IFRAMELY_CDN_HOST) {
                //ckeditor4 the iframe tag contains the required iframe url with the key already there
                $apiUrl = $apiUrl->appendQueryParameters($paramsSet + $srcUrl->getQueryAsArray());
            } else {
                // not supposed to be here... don't iframely non iframely iframes...
                // lets just continue without editing the current node.
                continue;
            }
            try {
                $response = $this->httpClient->get($apiUrl);
            } catch (GuzzleException $e) {
                // Something went wrong whilst making the new request,
                // e.g. this request return a 417 Expectation Failed:
                // this means Iframely doesn't know how to output this
                // content without the iframe helper.
                // Try the next param set.
                continue;
            }
            $responseBody = json_decode($response->getBody()->getContents(), true);
            if (isset($responseBody['html'])) {
                return $responseBody['html'];
            }
        }
        return null;
    }
}
