<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Twig\Filter;

use Alaress_Date_Format;

/**
 * Twig filter for converting an input into a relative date.
 */
class RelativeDate
{
    /**
     * Convert an input into a relative date output.
     *
     * @param mixed $date
     * @return string
     */
    public function __invoke($date): string
    {
        if ($date instanceof Alaress_Date_Format) {
            $dateObject = $date;
        } elseif ($date instanceof \DateTimeInterface) {
            $dateObject = Alaress_Date_Format::newFromTimestamp($date->getTimestamp());
        } else {
            $dateObject = Alaress_Date_Format::newFromTimestamp(strtotime($date));
        }
        return $dateObject->getRelativeDay();
    }
}
