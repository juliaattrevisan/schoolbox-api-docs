<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Term;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\Filter;


/**
 * Converts between termFilter concrete keys and Term.ids
 */
abstract class KeyConverter extends AbstractKeyConverter
{

    /**
     * All Term Ids in the system (regardless of whether they are available)
     *
     * @var GenericCollection|int[]
     */
    private $termIds;


    /**
     * @param Filter\Model     $filter
     * @param Collection|int[] $termIds
     */
    public function __construct(Filter\Model $filter, Collection $termIds)
    {
        parent::__construct($filter);

        // Ensure invoker cannot mutate our collection's sorting or indexing after construction
        $this->termIds = $termIds->shallowClone()->indexByCallback(function($val){
            return $val;
        });
    }


    /**
     * @inheritdoc
     * @return int
     */
    protected function getItem(string $key, \Schoolbox\User $user, string $page): int
    {
        $this->assertValidKey($key, $user, $page);
        return (int)$key;
    }


    /**
     * Return all available concrete items
     *
     * @return Collection|int[]
     */
    public function getAllPossibleItems(): Collection
    {
        return $this->termIds->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, \Schoolbox\User $user, string $page): void
    {
        if (!is_numeric($key)) {
            throw new \InvalidArgumentException("Term keys must be numeric, representing term.id:  $key");
        }
    }
}
