<?php

/**
 * CriterionMetadata
 *
 * @author      Dorothea Baker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

class CriterionMetadata {
    public const DIRECTION_ASCENDING = 'asc';
    public const DIRECTION_DESCENDING = 'desc';

    /**
     * ID of the criterion
     * @var int $id
     */
    private $id;

    /**
     * Number of levels in this rubric
     * @var int $numLevels
     */
    private $numLevels;

    /**
     * Order of the descriptor values for this criterion
     * @var string $direction
     */
    private $direction;

    /**
     * ID of the first level with a descriptor value
     * @var int $firstValuedLevelIndex
     */
    private $firstValuedLevelIndex;

    /**
     * ID of the last level with a descriptor value
     * @var int $lastValuedLevelIndex
     */
    private $lastValuedLevelIndex;

    /**
     * @var int $firstLevelValue
     */
    private $firstLevelValue;

    /**
     * @var int $lastLevelValue
     */
    private $lastLevelValue;

    /**
     * Descriptors in this criterion, indexed by level ID
     * @var array $levelDescriptors
     */
    private $levelDescriptors;

    /**
     * @var array $levelIds;
     */
    private $levelIds;

    /**
     * Array of descriptor metadata for this criterion, indexed by level ID
     * @var array $descriptorMetadata
     */
    private $descriptorMetadata;

    /**
     * CriterionMetadata constructor.
     * @param int $id
     * @param array $levelIds
     * @param array $levelDescriptors
     */
    public function __construct(int $id, array $levelIds, array $levelDescriptors) {
        $this->id = $id;
        $this->levelIds = $levelIds;
        $this->numLevels = \count($levelIds) - 1;
        $this->levelDescriptors = $levelDescriptors;

        $this->firstValuedLevelIndex = $this->calculateFirstValuedLevelIndex();
    }

    private function calculateFirstValuedLevelIndex() : int
    {
        // By default, assume the first level has a value
        $this->firstValuedLevelIndex = 0;
        $firstLevel = $this->levelIds[$this->firstValuedLevelIndex];
        $startValue = $this->levelDescriptors[$this->id][$firstLevel]['value'];

        // Loop until we find a level that has a value
        while ($startValue === null && ($this->firstValuedLevelIndex < $this->numLevels)) {
            $this->firstValuedLevelIndex++;
            $firstLevel = $this->levelIds[$this->firstValuedLevelIndex];
            $startValue = $this->levelDescriptors[$this->id][$firstLevel]['value'];
        }

        // $startValue will be null if none of the levels have a value
        return $startValue;
    }

    private function calculateLastValuedLevelIndex() : int
    {

    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getDirection() : string
    {
        return $this->direction;
    }

}