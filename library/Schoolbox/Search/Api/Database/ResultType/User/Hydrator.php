<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\User;

use Alaress\Image\Transformation\Storage;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\Hydrator\IHydrator;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Hydrate a user match into an actual search result.
 */
class Hydrator implements IHydrator
{
    /**
     * Constructor
     *
     * @param UserMapper $userMapper Used to fetch users for hydration.
     */
    public function __construct(private UserMapper $userMapper)
    {
    }

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
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);
        $users           = $this->userMapper->findByColumn(
            'id',
            array_map(
                fn (RawResult $match) => $match->id,
                $acceptedMatches,
            )
        );

        // TODO use user mapper, etc. to flesh out match into result
        return array_map(
            function (RawResult $match) use ($users) {
                $user = $users->getBy('id', $match->id);
                if ($user instanceof User) {
                    return new FullResult(
                        $match->id,
                        // TODO use proper name format
                        $user->formatName(),
                        '/search/user/' . $match->id,
                        $match->type,
                        $match->relevance,
                        $match->date,
                        null,
                        sprintf(
                            '/portrait.php?id=%d&size=%s',
                            $user->getId(),
                            urlencode(Storage::TRANSFORM_SQUARE64)
                        ),
                        'staff-students',
                    );
                }

                // Nothing was here after all
                return null;
            },
            $acceptedMatches,
        );
    }
}
