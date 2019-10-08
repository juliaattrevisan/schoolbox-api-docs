<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event\EventType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;


/**
 * Converts between AssessmentType concrete keys and AssessmentType strings
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * All AssessmentTypes known to the system (regardless of whether they are available)
     *
     * @var Collection|string[]
     */
    private $assessmentTypes;


    /**
     * @param Filter\Model        $filter
     * @param Collection|string[] $assessmentTypes
     */
    public function __construct(Filter\Model $filter, Collection $assessmentTypes)
    {
        parent::__construct($filter);
        $this->assessmentTypes = $assessmentTypes;
    }


    /**
     * @inheritdoc
     * @return string
     */
    protected function getItem(string $key, \Schoolbox\User $user, string $page): string
    {
        $this->assertValidKey($key, $user, $page);
        return $key;
    }


    /**
     * @return Collection|string[]
     */
    public function getAllPossibleItems(): Collection
    {
        return $this->assessmentTypes->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, \Schoolbox\User $user, string $page): void
    {
        if (!in_array($key, $this->assessmentTypes->getAll(), true)) {
            throw new \InvalidArgumentException('AssessmentType Key is invalid: ' . $key);
        }
    }

}
