<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TokenReplacer;

use RuntimeException;

/**
 * Class Simple
 *
 * Does a simple 1:1 string replace from one array to another
 *
 * @package Schoolbox\TokenReplacer
 */
class Simple
{
    /**
     * Content type prefix: required for forcing the DOM parser to recognise
     * the HTML to convert as UTF-8 encoded.
     */
    private const META_CONTENT_TYPE_PREFIX = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';

    /**
     * $replacements is a set of key-value tuples just like phplib:
     *
     * [
     *     '[firstname]' => $var1,
     *     '[lastname]' => $var2,
     *     ...
     * ]
     *
     * $body gets its tokens detected and replaced accordingly,
     * returning a version of $body with all the substitutions
     *
     * Tokens that exist, but aren't in $replacements, will remain
     * untouched by the substitutions.
     *
     * $replacement values that exist, but aren't represented by
     * tokens in $body, will remain unused.
     *
     * @param string $body
     * @param array  $replacements
     * @return string
     * @throws \DOMException
     * @throws RuntimeException
     */
    public function replace(string $body, array $replacements): string
    {
        $body = $this->stripTargetAttribute($body);
        $sanitisedReplacements = array_map(static function ($val) {
            return \Scrub::text($val);
        }, array_values($replacements));

        return str_replace(array_keys($replacements), $sanitisedReplacements, $body);
    }


    /**
     * Replace using Scrub::html, to return replaced html
     * @param string $body
     * @param array  $replacements
     * @return string
     * @throws \DOMException
     * @throws RuntimeException
     */
    public function replaceHtml(string $body, array $replacements): string
    {
        $body = $this->stripTargetAttribute($body);
        $sanitisedReplacements = array_map(static function ($val) {
            return \Scrub::html($val);
        }, array_values($replacements));

        return str_replace(array_keys($replacements), $sanitisedReplacements, $body);
    }


    /**
     * Diagnostic for seeing which tokens are available for substitutions
     *
     * @param string $body
     * @return array
     */
    public function manifest(string $body): array
    {

        /* Find all the tokens
         */
        preg_match_all('/\[[^]]+\]/m', $body, $matches);

        /* Remove dupes and reindex
         */
        return array_values(array_unique($matches[0]));
    }


    /**
     * Attempt to remove attributes from the target element
     *
     * @param string $htmlContent
     * @param string $targetAttributeValue
     * @param string $targetAttributeProp
     * @return string
     * @throws \DOMException
     * @throws RuntimeException
     */
    public function stripTargetAttribute(
        string $htmlContent,
        string $targetAttributeValue = 'mention',
        string $targetAttributeProp = 'class'
    ): string {
        //Should exit early if no tag to replace
        if ($htmlContent === strip_tags($htmlContent)) {
            return $htmlContent;
        }
        $dom = new \DOMDocument();
        try {
            $dom->loadHTML(self::META_CONTENT_TYPE_PREFIX . $htmlContent);
        } catch (\ErrorException $e) {
            throw new RuntimeException('Could not load HTML content: ' . $e->getMessage());
        }
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//span[@$targetAttributeProp]");

        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $class = $node->getAttribute($targetAttributeProp);
                if (str_contains($class, $targetAttributeValue)) {
                    //Replace element with plain text
                    $replace = $dom->createTextNode($node->nodeValue);
                    $node->parentNode->replaceChild($replace, $node);
                }
            }
        }
        $body = $dom->saveHTML();

        unset($dom, $xpath);
        return $body ?: '';
    }
}
