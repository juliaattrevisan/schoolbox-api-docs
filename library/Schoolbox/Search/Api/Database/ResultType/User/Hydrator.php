<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\User;

use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\Hydrator\IHydrator;

/**
 * Hydrate a user match into an actual search result.
 */
class Hydrator implements IHydrator
{
    /**
     * @inheritdoc
     */
    public function accepts(RawResult $match): bool
    {
        return str_starts_with($match->type, 'user.');
    }

    /**
     * @inheritdoc
     */
    public function hydrate(array $matches): array
    {
        // TODO use user mapper, etc. to flesh out match into result
        return array_map(
            fn (RawResult $match) => new FullResult(
                $match->id,
                $match->title,
                '/search/user/' . $match->id,
                $match->type,
                $match->relevance,
                $match->date,
            ),
            array_filter($matches, [$this, 'accepts']),
        );
    }
}
