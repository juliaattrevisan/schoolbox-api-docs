<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\User;

use Schoolbox\Search\Api\Database\IHydrator;
use Schoolbox\Search\Api\Database\RawMatch;
use Schoolbox\Search\Api\Result\DataObject;

/**
 * Hydrate a user match into an actual search result.
 */
class Hydrator implements IHydrator
{
    /**
     * @inheritdoc
     */
    public function accepts(RawMatch $match): bool
    {
        return str_starts_with($match->type, 'user.');
    }

    /**
     * @inheritdoc
     */
    public function hydrate(RawMatch $match): DataObject
    {
        // TODO use user mapper, etc. to flesh out match into result
        return new DataObject(
            $match->id,
            $match->title,
            '/search/user/' . $match->id,
            $match->type,
            $match->date,
        );
    }
}
