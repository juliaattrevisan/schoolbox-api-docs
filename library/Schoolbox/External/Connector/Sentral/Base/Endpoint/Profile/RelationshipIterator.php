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
        $parentPersonId = $current['relationships']['person']['data']['id'] ?? null;

        if ($parentPersonId && isset($this->relational['person'][$parentPersonId])) {
            $parent = $this->relational['person'][$parentPersonId];
        }

        /* Deconstruct a massive hierarchy of not-so-useful information
         * Keep the list of maybe-relevant links to other endpoints where you might find more info
         */
        return [
            'student' => $student ? [
                'active' => $student['attributes']['isActive'],
                'internalId' => trim((string)$student['id']),
                'externalId' => trim((string)$student['relationships']['person']['attributes']['externalId']),
                'personId' => trim((string)$studentPersonId),
                'title' => trim((string)$student['relationships']['person']['attributes']['title']),
                'preferredName' => trim((string)$student['relationships']['person']['attributes']['preferredName']),
                'firstname' => trim((string)$student['relationships']['person']['attributes']['firstName']),
                'lastname' => trim((string)$student['relationships']['person']['attributes']['lastName']),
                'dob' => trim((string)$student['relationships']['person']['attributes']['dateOfBirth']),
                'email' => trim((string)$student['relationships']['person']['attributes']['email']),
                'emergencyContacts' => $this->lookups['emergencyContacts'][$studentPersonId] ?? [],
                'enrolment' => $this->lookups['enrolment'][$studentId] ?? [
                    'startDate' => null,
                    'endDate' => null,
                    'year' => null,
                    'isBoarding' => false,
                    'isActive' => false,
                ],
                'gender' => trim((string)$student['relationships']['person']['attributes']['gender']),
                'learningPlans' => $this->lookups['learningPlans'][$studentId] ?? 0,
                'medical' => $this->lookups['medical'][$studentPersonId] ?? [],
                'phone' => $this->lookups['phone'][$studentPersonId] ?? [],
                'links' => $student['links']
            ] : null,
            'parent' => $parent ? [
                'active' => $parent['attributes']['isActive'],
                'internalId' => trim((string)$parent['id']),
                'externalId' => trim((string)$parent['attributes']['externalId']),
                'personId' => trim((string)$parentPersonId),
                'title' => trim((string)$parent['attributes']['title']),
                'preferredName' => trim((string)$parent['attributes']['preferredName']),
                'firstname' => trim((string)$parent['attributes']['firstName']),
                'lastname' => trim((string)$parent['attributes']['lastName']),
                'dob' => trim((string)$parent['attributes']['dateOfBirth']),
                'email' => trim((string)$parent['attributes']['email']),
                'gender' => trim((string)$parent['attributes']['gender']),
                'phone' => $this->lookups['phone'][$parentPersonId] ?? [],
                'relation' => trim((string)$current['attributes']['relationName']),
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
