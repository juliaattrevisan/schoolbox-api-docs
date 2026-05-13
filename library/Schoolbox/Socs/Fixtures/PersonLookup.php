<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures;

use Schoolbox\Clipboard\Hierarchy\Lookup\Person;

class PersonLookup extends Person
{
    /**
     * Find a user id based on their external ID
     * @param string $externalId
     * @return int|null
     */
    public function fetchUser(string $externalId): ?int
    {
        // install upon first use
        if (null === $this->lookup) {
            $this->lookup = $this->generate();
        }

        // find the guy who matches; null if the requested $externalId never existed
        return $this->lookup[$externalId] ?? null;
    }
}
