<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event\EventType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;


/**
 * Converter between event type concrete keys and strings.
 */
class KeyConverter extends AbstractKeyConverter
{
    /**
     * @var Collection|string[]
     */
    private $eventTypeIds;


    /**
     * @param Filter\Model        $filter
     * @param Collection|string[] $eventTypeIds
     */
    public function __construct(Filter\Model $filter, Collection $eventTypeIds)
    {
        parent::__construct($filter);
        $this->eventTypeIds = $eventTypeIds->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function getItem(string $key, User $user, string $page): int
    {
        $this->assertValidKey($key, $user, $page);
        return (int)$key;
    }


    /**
     * @return Collection|string[]
     */
    public function getAllPossibleItems(): Collection
    {
        return $this->eventTypeIds->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if (!$this->eventTypeIds->offsetExists($key)) {
            throw new \InvalidArgumentException('EventType Key is invalid: ' . $key);
        }
    }
}
