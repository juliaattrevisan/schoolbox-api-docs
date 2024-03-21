<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\WorkType;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;

/**
 * Converts between concrete keys workType.ids and workType.ids
 */
class KeyConverter extends AbstractKeyConverter
{
    /**
     * All WorkType IDs known to the system (regardless of whether they are available)
     *
     * Indexed by workType.id
     *
     * @var Collection|int[]
     */
    private $workTypeIds;


    /**
     * @param Filter\Model     $filter
     * @param Collection|int[] $workTypeIds
     */
    public function __construct(Filter\Model $filter, Collection $workTypeIds)
    {
        parent::__construct($filter);
        $this->workTypeIds = $workTypeIds->shallowClone()->indexByCallback(static function (int $key): int {
            return $key;
        });
    }


    /**
     * @inheritdoc
     */
    public function getItem(string $key, User $user, string $page): ?int
    {
        $this->assertValidKey($key, $user, $page);

        return (int)$key;
    }


    /**
     * @inheritDoc
     */
    public function possibleItemCount(): int
    {
        return $this->workTypeIds->count();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if (!$this->workTypeIds->offsetExists($key)) {
            throw new InvalidArgumentException("No WorkType found for key: $key");
        }
    }
}
