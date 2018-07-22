<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api\StandardTransformer;

/**
 * Parser for standards from ACARA.
 */
class Acara implements IStrategy
{
    /**
     * @inheritdoc
     */
    public function parseStandard(string $standard): ?array
    {
        // ACARA strategy: find a link to Scootle, use it
        if (preg_match('[>(.*?)</a>]i', $standard, $matches)) {
            [, $code] = $matches;
            $standard = strip_tags($standard);
            return [
                'code'  => $code,
                'title' => $standard,
            ];
        }
        return null;
    }
}
