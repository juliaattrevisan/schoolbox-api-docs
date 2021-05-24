<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
     * The encoded value that represents a segment. eg 'folder:2:x:self'
     * @var string
     */
    private $key;

    /**
     * A semantically descriptive name to render in selectize option list, and on a selected segment.
     * Not rendered on the brick (that comes from labels)
     *
     * @var string
     */
    private $name;

    /**
     * The labels for each simple segment represented by this selectize option
     *
     * Intended for display to end-users.
     *
     * @var string[][]
     */
    private $labels = [];

    /**
     * @param string $key
     * @param string $name
     */
    public function __construct(string $key, string $name)
    {
        $this->key  = $key;
        $this->name = $name;
    }


    /**
     * Any entity logically referenced by the segment should have a human-friendly label added via this method.
     * If not, the front-end will fallback to the raw value, eg folder:'2' is not as helpful as folder:'English 34'
     *
     * @param string $entityType
     * @param string $encodedValue
     * @param string $label
     *
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
            'key'       => $this->key,
            'name'      => $this->name,
            'labels'    => $this->labels,
        ];
    }
}
