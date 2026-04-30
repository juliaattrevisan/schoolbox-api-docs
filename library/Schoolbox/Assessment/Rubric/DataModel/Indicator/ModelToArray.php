<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\DataModel\Indicator;

use Scrub;

class ModelToArray
{
    /**
     * Returns an (id, name) tuple that represents an indicator in a scalar format, translating any markdown that might
     * have existed there
     *
     * @param Model $indicator
     * @return array
     */
    public function __invoke(Model $indicator): array
    {
        return [
            'id' => $indicator->getId(),
            'name' => Scrub::markdown($indicator->getName()),
        ];
    }
}
