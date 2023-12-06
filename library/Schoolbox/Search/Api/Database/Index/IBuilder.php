<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Alaress_Date_Format;

/**
 * Interface for index builder classes.
 */
interface IBuilder
{
    /**
     *
     *
     * @param Alaress_Date_Format $currentDate
     * @return array
     */
    public function build(Alaress_Date_Format $currentDate): array;
}
