<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile;

use Iterator;

/**
 * Class RelationshipIterator
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile
 */
class RelationshipIterator implements Iterator
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
     * A hash lookup of student ids and person ids which we must manually compile dynamically
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
     * RelationshipIterator constructor.
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

        $student = null;
        $studentPersonId = null;
        $parent = null;

        /* Find student in the data[included] stack and manually join in their details
         */
        $studentId = $current['relationships']['student']['data']['id'];

        if (isset($this->relational['student'][$studentId])) {
            $student = $this->relational['student'][$studentId];
            $studentPersonId = $student['relationships']['person']['data']['id'];

            if (isset($this->relational['person'][$studentPersonId])) {
                $student['relationships']['person'] = $this->relational['person'][$studentPersonId];
            }
        }

        /* Find parent in the data[included] stack and manually join in their details
         */
        $parentPersonId = $current['relationships']['person']['data']['id'];

        if (isset($this->relational['person'][$parentPersonId])) {
            $parent = $this->relational['person'][$parentPersonId];
        }

        /* Deconstruct a massive hierarchy of not-so-useful information
         * Keep the list of maybe-relevant links to other endpoints where you might find more info
         */
        return [
            'student' => $student ? [
                'active' => $student['attributes']['isActive'],
                'internalId' => $student['id'],
                'externalId' => $student['relationships']['person']['attributes']['externalId'],
                'personId' => $studentPersonId,
                'title' => $student['relationships']['person']['attributes']['title'],
                'preferredName' => $student['relationships']['person']['attributes']['preferredName'],
                'firstname' => $student['relationships']['person']['attributes']['firstName'],
                'lastname' => $student['relationships']['person']['attributes']['lastName'],
                'email' => $student['relationships']['person']['attributes']['email'],
                'gender' => $student['relationships']['person']['attributes']['gender'],
                'dob' => $student['relationships']['person']['attributes']['dateOfBirth'],
                'medical' => $this->lookups['medical'][$studentPersonId],
                'links' => $student['links']
            ] : null,
            'parent' => $parent ? [
                'active' => $parent['attributes']['isActive'],
                'internalId' => $parent['id'],
                'externalId' => $parent['attributes']['externalId'],
                'personId' => $parentPersonId,
                'title' => $parent['attributes']['title'],
                'preferredName' => $parent['attributes']['preferredName'],
                'firstname' => $parent['attributes']['firstName'],
                'lastname' => $parent['attributes']['lastName'],
                'email' => $parent['attributes']['email'],
                'gender' => $parent['attributes']['gender'],
                'dob' => $parent['attributes']['dateOfBirth'],
                'links' => $parent['links'],
            ] : null,
        ];
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
