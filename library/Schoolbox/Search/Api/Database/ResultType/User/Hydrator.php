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
use Schoolbox\Url\Uri;
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
     * @param UserMapper               $userMapper        Used to fetch users for hydration.
     * @param User\Formatter\Formatter $userNameFormatter Used to format a user's name on output.
     */
    public function __construct(
        private UserMapper $userMapper,
        private User\Formatter\Formatter $userNameFormatter,
    ) {
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
        // Find all users which match $matches
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);
        $users           = $this->userMapper->findByColumn(
            'id',
            array_map(
                fn (RawResult $match) => $match->id,
                $acceptedMatches,
            )
        );

        // Map those users to full results
        return array_map(
            function (RawResult $match) use ($users) {
                $user = $users->getBy('id', $match->id);
                if ($user instanceof User) {
                    return new FullResult(
                        $match->id,
                        $this->userNameFormatter->formatObject($user),
                        '/search/user/' . $match->id,
                        $match->type,
                        $match->relevance,
                        $match->date,
                        null,
                         (new Uri())
                            ->withPath('/portrait.php')
                            ->withQuery(
                                http_build_query([
                                    'id'   => $user->getId(),
                                    'size' => Storage::TRANSFORM_SQUARE64,
                                ])
                            )
                            ->__toString(),
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
