<?php

/**
 * Calculates the direction of descriptor values for a single criterion, and defers to DescriptorMetadata for range calculation
 *
 * @author      Dorothea Baker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork;

class CriterionMetadata
{
    public const DIRECTION_ASCENDING = 'asc';
    public const DIRECTION_DESCENDING = 'desc';

    /**
     * Order of the descriptor values for this criterion
     *
     * @var string $direction
     */
    private $direction;

    /**
     * Array of descriptor metadata for this criterion, indexed by level ID
     *
     * @var array $descriptorMetadata
     */
    private $metadata = [];


    /**
     * IDs of all available levels in the rubric
     *
     * @var array $levelIds;
     */
    private $levelIds;

    /**
     * Number of levels in the rubric
     *
     * @var int $numLevels
     */
    private $numLevels;

    /**
     * Index of the last level that has a descriptor with a value -
     * effectively the array boundary when looking for $nextDescriptor
     *
     * @var int $lastValuedLevelIndex
     */
    private $lastValuedLevelIndex;

    /**
     * Descriptors in this criterion, indexed by level ID
     *
     * @var array $levelDescriptors
     */
    private $levelDescriptors;


    /**
     * CriterionMetadata constructor.
     * @param array $levelIds
     * @param array $levelDescriptors
     */
    public function __construct(array $levelIds, array $levelDescriptors)
    {
        $this->levelIds = $levelIds;
        $this->numLevels = \count($levelIds);
        $this->levelDescriptors = $levelDescriptors;

        $this->direction = $this->calculateDirection();

        $this->metadata['direction'] = $this->direction;

        // Can't use array_merge here because it would re-index the descriptors, which need to be indexed by level ID
        $this->metadata += $this->generateDescriptorMetadata();
    }

    /**
     * Returns an array of level ID => metadata for the level descriptor
     * @return array
     */
    private function generateDescriptorMetadata(): array
    {
        $meta = [];

        $prevDescriptor = null;
        $nextDescriptor = null;

        // Need this to work out when we reach the last descriptor-having level
        $iteration = 0;

        // Loop through all the levels in the rubric. Not all of them may have a descriptor in a given criterion...
        for ($levelIndex = 0; $levelIndex < $this->numLevels; $levelIndex++) {
            // Have we already seen all the descriptors that have levels?
            $isLastDescriptor = ($iteration === \count($this->levelDescriptors) - 1);

            // Does the level have a descriptor in this criterion?
            if (array_key_exists($levelIndex, $this->levelDescriptors)) {
                if ($isLastDescriptor) {
                    // If this is the last iteration we know there can't possibly be a next descriptor
                    $nextDescriptor = null;
                } else {
                    // Otherwise, look ahead until we find a level with a descriptor value, and use that as the Next descriptor
                    $nextDescriptorIndex = $levelIndex + 1;
                    $nextDescriptor = $this->levelDescriptors[$nextDescriptorIndex];
                    while ($nextDescriptor['value'] === null && $nextDescriptorIndex < $this->lastValuedLevelIndex) {
                        $nextDescriptorIndex++;
                        $nextDescriptor = $this->levelDescriptors[$nextDescriptorIndex];
                    }
                }

                $dm = new DescriptorMetadata($this->levelDescriptors[$levelIndex], $prevDescriptor, $nextDescriptor, $this->direction);
                $meta[$levelIndex] = $dm->toArray();

                if ($this->levelDescriptors[$levelIndex]['value'] !== null) {
                    $prevDescriptor = $this->levelDescriptors[$levelIndex];
                }

                // How many levels with descriptors have we seen?
                $iteration++;
            }
        }

        return $meta;
    }

    /**
     * Work out whether the values of descriptors in this criterion go up or down.
     * @return string
     */
    private function calculateDirection(): string
    {
        $startValue = $this->calculateStartValue();
        $endValue = $this->calculateEndValue();

        if ($startValue > $endValue) {
            return self::DIRECTION_DESCENDING;
        }

        return self::DIRECTION_ASCENDING;
    }

    /**
     * Work out the value of the first descriptor that has a value. Used to calculate direction
     * @return float|null
     */
    private function calculateStartValue(): ?float
    {
        // By default, assume the first level has a value
        $firstValuedLevelIndex = 0;
        $firstLevel = $this->levelIds[$firstValuedLevelIndex];
        $startValue = $this->levelDescriptors[$firstLevel]['value'];

        // Loop until we find a level that has a value
        while ($startValue === null && ($firstValuedLevelIndex < $this->numLevels)) {
            $firstValuedLevelIndex++;
            $firstLevel = $this->levelIds[$firstValuedLevelIndex];
            $startValue = $this->levelDescriptors[$firstLevel]['value'];
        }

        // $startValue will be null if none of the levels have a value
        return $startValue;
    }

    /**
     * Work out the value of the last descriptor that has a value. Used to calculate direction
     * @return float|null
     */
    private function calculateEndValue(): ?float
    {
        // By default, assume the last level has a value
        $this->lastValuedLevelIndex = $this->numLevels - 1;
        $lastLevel = $this->levelIds[$this->lastValuedLevelIndex];
        $endValue = $this->levelDescriptors[$lastLevel]['value'];

        // Loop until we find a level that has a value
        while ($endValue === null && ($this->lastValuedLevelIndex > 0)) {
            $this->lastValuedLevelIndex--;
            $lastLevel = $this->levelIds[$this->lastValuedLevelIndex];
            $endValue = $this->levelDescriptors[$lastLevel]['value'];
        }

        // $endValue will be null if none of the levels have a value
        return $endValue;
    }


    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
