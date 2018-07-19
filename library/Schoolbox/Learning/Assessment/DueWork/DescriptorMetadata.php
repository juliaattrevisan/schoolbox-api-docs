<?php

/**
 * DescriptorMetadata
 *
 * @author      Dorothea Baker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork;

class DescriptorMetadata {
    /**
     * @var array $prevDescriptor
     */
    private $prevDescriptor;

    /**
     * @var array $nextDescriptor
     */
    private $nextDescriptor;

    /**
     * A single descriptor object in array form
     * @var array $descriptor
     */
    private $descriptor;

    /**
     * ID of the level this descriptor belongs too
     * @var int $levelId
     */
    private $levelId;

    /**
     * Order of the descriptor values in the criterion this descriptor belongs to
     * @var string
     */
    private $direction;


    /**
     * DescriptorMetadata constructor.
     * @param int $levelId
     * @param array $descriptor
     * @param array $prevDescriptor
     * @param array $nextDescriptor
     * @param string $direction
     */
    public function __construct(int $levelId, array $descriptor, array $prevDescriptor, array $nextDescriptor, string $direction) {
        $this->levelId = $levelId;
        $this->descriptor = $descriptor;
        $this->prevDescriptor = $prevDescriptor;
        $this->nextDescriptor = $nextDescriptor;
        $this->direction = $direction;
    }


    /**
     * @return int
     */
    public function calculateMin() : ?int
    {
        // TODO hard-coded value for testing; replace
        // TODO handle nulls
        return 0;
    }

    /**
     * The persisted value of this descriptor is always its maximum value.
     * @return int
     */
    public function calculateMax() : ?int
    {
        // TODO handle nulls
        return $this->descriptor['value'];
    }

    /**
     * Min and max values for this descriptor
     * @return array
     */
    public function toArray() : array
    {
        // TODO handle nulls
        return ['min' => $this->calculateMin(), 'max' => $this->calculateMax()];
    }
}