<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\Module;

use function preg_match_all;
use function str_replace;

/**
 * Responsible for parsing routes that have embedded regexp token constraints
 *
 * @see http://auraphp.com/packages/3.x/Router/defining-routes.html
 */
class RouteParser
{
    /**
     * Parse a route with optional embedded regex tokens into a clean aura-friendly route + regex token array
     *
     * @param string $path
     * @return array
     * @internal string   return[0] The cleaned path
     * @internal string[] return[1] Token regex array
     */
    public function parse(string $path): array
    {
        // Does path not contain any placeholders with regex tokens?
        if (!preg_match_all('#(?:(?:{/?)|,)([^:,}]+):([^},]+)(?:}|(?=,))#', $path, $matches)) {
            return [$path, []];
        }

        $tokens = [];
        [$fullMatches, $params, $regexps] = $matches;

        foreach ($fullMatches as $i => $fullMatch) {
            // obtain token reference
            $tokens[$params[$i]] = $regexps[$i];

            // make shorthanded route aura friendly
            $path = str_replace($fullMatch, str_replace("{$params[$i]}:{$regexps[$i]}", $params[$i], $fullMatch), $path);
        }

        return [$path, $tokens];
    }
}
