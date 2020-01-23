<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile;

use Iterator;

/**
 * Class StaffIterator
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile
 */
class StaffIterator implements Iterator
{

    /**
     * @var array
     */
    private $data;


    /**
     * @var int
     */
    private $position = 0;


    /**
     * A hash lookup of staff ids and person ids which we must manually compile dynamically
     * ...because sentral
     *
     * @var array
     */
    private $relational = [];


    /**
     * Medical and other data
     *
     * @var array
     */
    private $lookups;


    /**
     * StaffIterator constructor.
     *
     * @param array $data
     * @param array $lookups
     */
    public function __construct(array $data, array $lookups)
    {
        $this->data = $data;
        $this->lookups = $lookups;

        /* Compile a lookup of $data because traversing all this is really annoying
         */
        foreach ($this->data['included'] as $included) {
            $this->relational[$included['type']][$included['id']] = $included;
        }
    }


    /**
     * @inheritDoc
     */
    public function current(): array
    {
        $current = $this->data['data'][$this->position];

        $staffPersonId = $current['relationships']['person']['data']['id'];

        $staff = $this->relational['person'][$staffPersonId];

        $tuple = [
            'active' => $current['attributes']['isActive'],
            'externalId' => trim((string)$staff['attributes']['externalId']),
            'title' => trim((string)$staff['attributes']['title']),
            'preferredName' =>trim((string)$staff['attributes']['preferredName']),
            'firstname' => trim((string)$staff['attributes']['firstName']),
            'lastname' => trim((string)$staff['attributes']['lastName']),
            'classification' => trim((string)$current['attributes']['employmentClassification']),
            'email' => trim((string)$staff['attributes']['email']),
            'employmentStatus' => trim((string)$current['attributes']['employmentStatus']),
            'jobTitle' => trim((string)$current['attributes']['jobTitle']),
            'phone' => $this->lookups['phone'][$staffPersonId] ?? [],
        ];

        return $tuple;
    }


    /**
     * @inheritDoc
     */
    public function next(): void
    {
        ++$this->position;
    }


    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return isset($this->data['data'][$this->position]);
    }


    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->position = 0;
    }


    /**
     * @inheritDoc
     */
    public function key(): int
    {
        return $this->position;
    }
}

