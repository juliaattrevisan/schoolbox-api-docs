<?php

/**
 * DescriptorMetadata
 *
 * @author      Dorothea Baker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

class DescriptorMetadata {
    private $prevDescriptorMax;

    private $nextDescriptorMax;

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


    public function __construct(int $levelId, array $descriptor, string $direction) {
        $this->levelId = $levelId;
        $this->descriptor = $descriptor;
        $this->direction = $direction;
    }


    public function calculateMin() {

    }

    /**
     * The persisted value of this descriptor is always its maximum value.
     * @return int
     */
    public function calculateMax() : int
    {
        return $this->descriptor['value'];
    }

    /**
     *
     */
    public function toArray() : array
    {
        return ['min' => $this->calculateMin(), 'max' => $this->calculateMax()];
    }
}