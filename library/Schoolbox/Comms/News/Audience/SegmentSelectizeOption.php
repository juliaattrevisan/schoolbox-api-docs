<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use JsonSerializable;

/**
 * The news audience selectize expects a specifically formatted data structure.
 * The selectize has two mechanisms of accepting options:
 * 1. Initial values on page load, and
 * 2. Text search
 * In both cases, the data structure must be the same. When json_encoded, instances of this class
 * will conform to that expected format.
 */
class SegmentSelectizeOption implements JsonSerializable
{

    /**
     * The encoded value that represents a segment
     * @var string
     */
    private $value;

    /**
     * A label to show on a selected segment (not brick)
     * @var string
     */
    private $label;

    /**
     * The labels for each simple segment represented by this selectize option
     * @var string[][]
     */
    private $labels = [];


    /**
     * @param string $value
     * @param string $label
     */
    public function __construct(string $value, string $label)
    {
        $this->value = $value;
        $this->label = $label;
    }


    /**
     * Any entity logically referred to in 'value' should have a human-friendly label added via this method.
     * If not, the front-end will fallback to the raw value, eg folder:'2' is not as helpful as folder:'English 34'
     *
     * @param string $entityType
     * @param string $encodedValue
     * @param string $label
     */
    public function addLabel(string $entityType, string $encodedValue, string $label): void
    {
        $this->labels[$entityType][$encodedValue] = $label;
    }

    /**
     * This is the format expected by the audience selectize
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return [
            'value'  => $this->value,
            'label'  => $this->label,
            'labels' => $this->labels,
        ];
    }
}
