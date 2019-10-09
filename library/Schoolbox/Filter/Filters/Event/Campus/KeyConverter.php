<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event\Campus;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;


/**
 * Converter between campus concrete keys and strings.
 */
class KeyConverter extends AbstractKeyConverter
{
    /**
     * @var Collection|string[]
     */
    private $campusIds;


    /**
     * @param Filter\Model        $filter
     * @param Collection|string[] $campusIds
     */
    public function __construct(Filter\Model $filter, Collection $campusIds)
    {
        parent::__construct($filter);
        $this->campusIds = $campusIds->shallowClone();
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
        return $this->campusIds->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if (!$this->campusIds->offsetExists($key)) {
            throw new \InvalidArgumentException('Campus Key is invalid: ' . $key);
        }
    }
}
