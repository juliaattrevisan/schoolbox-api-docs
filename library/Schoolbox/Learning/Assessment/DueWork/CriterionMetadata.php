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
     * Order of the descriptor values for this criterion
     * @var string $direction
     */
    private $direction;

    /**
     * Array of descriptor metadata for this criterion, indexed by level ID
     * @var array $descriptorMetadata
     */
    private $descriptorMetadata;


    /**
     * IDs of all available levels in the rubric
     * @var array $levelIds;
     */
    private $levelIds;

    /**
     * Number of levels in the rubric
     * @var int $numLevels
     */
    private $numLevels;

    /**
     * Descriptors in this criterion, indexed by level ID
     * @var array $levelDescriptors
     */
    private $levelDescriptors;


    /**
     * CriterionMetadata constructor.
     * @param int $id
     * @param array $levelIds
     * @param array $levelDescriptors
     */
    public function __construct(int $id, array $levelIds, array $levelDescriptors) {
        $this->id = $id;
        $this->levelIds = $levelIds;
        $this->numLevels = \count($levelIds);
        $this->levelDescriptors = $levelDescriptors;

        $this->direction = $this->calculateDirection();
    }

    /**
     * Work out whether the values of descriptors in this criterion go up or down.
     * @return string
     */
    private function calculateDirection() : string
    {
        $startValue = $this->calculateStartValue();
        $endValue = $this->calculateEndValue();

        if ($startValue > $endValue) {
            return self::DIRECTION_ASCENDING;
        }

        return self::DIRECTION_DESCENDING;
    }

    /**
     * Work out the value of the first descriptor that has a value. Used to calculate direction
     * @return int
     */
    private function calculateStartValue() : int
    {
        // By default, assume the first level has a value
        $firstValuedLevelIndex = 0;
        $firstLevel = $this->levelIds[$firstValuedLevelIndex];
        $startValue = $this->levelDescriptors[$this->id][$firstLevel]['value'];

        // Loop until we find a level that has a value
        while ($startValue === null && ($firstValuedLevelIndex < $this->numLevels)) {
            $firstValuedLevelIndex++;
            $firstLevel = $this->levelIds[$firstValuedLevelIndex];
            $startValue = $this->levelDescriptors[$this->id][$firstLevel]['value'];
        }

        // $startValue will be null if none of the levels have a value
        return $startValue;
    }

    /**
     * Work out the value of the last descriptor that has a value. Used to calculate direction
     * @return int
     */
    private function calculateEndValue() : int
    {
        // By default, assume the last level has a value
        $lastValuedLevelIndex = $this->numLevels - 1;
        $lastLevel = $this->levelIds[$lastValuedLevelIndex];
        $endValue = $this->levelDescriptors[$this->id][$lastLevel]['value'];

        // Loop until we find a level that has a value
        while ($endValue === null && ($lastValuedLevelIndex > 0)) {
            $lastValuedLevelIndex--;
            $lastLevel = $this->levelIds[$lastValuedLevelIndex];
            $endValue = $this->levelDescriptors[$this->id][$lastLevel]['value'];
        }

        // $endValue will be null if none of the levels have a value
        return $endValue;
    }

    /**
     * @return int
     */
    public function getId() : int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getDirection() : string
    {
        return $this->direction;
    }

}