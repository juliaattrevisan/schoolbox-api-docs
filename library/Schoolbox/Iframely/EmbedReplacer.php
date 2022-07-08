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
     * The query selector which identifies an Iframely embed container within
     * the HTML.
     */
    private const IFRAMELY_EMBED_CONTAINER_ELEMENT_SELECTOR = 'div[data-oembed-url]';

    /**
     * The attributes which may contain the source to create a link to within
     * each embed container.
     */
    private const IFRAMELY_EMBED_CONTAINER_SOURCE_ATTRIBUTES = ['src', 'data-iframely-url'];

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
        $embeds = $doc->find(self::IFRAMELY_EMBED_CONTAINER_ELEMENT_SELECTOR);
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
                    $embed->setInnerHtml($replacementHtml);
                    break;
                }
            }
        }

        // Were any embeds replaced? If so, return the replaced HTML
        if (!$replacementsApplied) {
            return $html;
        }
        return $doc->first('body')->html();
    }

    /**
     * Determine what the replacement HTML should be for elements within an
     * Iframely embed.
     *
     * @param Element $embed
     * @param string  $attribute The attribute to search for
     * @return string|null
     * @noinspection PhpDocMissingThrowsInspection - xpath is hardcoded
     */
    private function findReplacementHtml(Element $embed, string $attribute): ?string
    {
        // Search for the attribute within the embed node:
        // if there are none, skip this embed
        /** @noinspection PhpUnhandledExceptionInspection - xpath is hardcoded */
        $nodes = $embed->find('[' . $attribute . ']');
        if (count($nodes) === 0) {
            return null;
        }

        // If there are attributes, check if any of those URLs point to the
        // Iframely CDN.
        // If any do: make a separate call to the iframely API to get the
        // HTML embed which bypasses Iframely's CDN
        foreach ($nodes as $srcNode) {
            $src = $srcNode->getAttribute($attribute);
            if (!isset($src)) {
                continue;
            }
            $srcUrl = new Uri($srcNode->getAttribute($attribute));
            if ($srcUrl->getHost() === self::IFRAMELY_CDN_HOST) {
                foreach (self::IFRAMELY_API_PARAMS_SETS as $paramsSet) {
                    $apiUrl = (new Uri(self::IFRAMELY_API_ENDPOINT))
                        ->setQuery($paramsSet + $srcUrl->getQueryAsArray());
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
            }
        }
        return null;
    }
}
