<?php

/**
 * EventListGroup
 *
 * All the events whose statuses can be modified appear in a mega
 * Collection of Collections called Event List Group. These have
 * events corresponding to Pending Final Approval etc. on the
 * Review screen.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ListGroup;

use EOTC\Event;

class Collection extends \Schoolbox\Collection
{

    /**
     * A list of ids that have already been processed
     *
     * @var array
     */
    private $processedSeriesIds = array();

    /**
     * An instance of Mapping
     *
     * @var Mapping
     */
    private $mapping = null;

    /**
     * No setup hook in \Schoolbox\Collection?
     */
    public function setup()
    {
        $this->setMapping(new Mapping());

        foreach ($this->getMapping()->getMapping() as $current) {
            $this->add(new \Schoolbox\Collection());
        }
    }

    /**
     * Assign an event to an Event List Group
     *
     * Ignore recurring instances and assign events to a collection
     * based on the event's status id and RAMS requirements (or any
     * other determination as defined in Chain)
     *
     * @param Event $event
     */
    public function assign(Event $event)
    {
        if (is_numeric($event->getSeriesId())) {
            if (!in_array($event->getSeriesId(), $this->getProcessedSeriesIds())) {
                $this->finish($event->getSeriesId());

                try {
                    $chain = new PriorityChain($event);
                    $location = $chain->getResult();

                    $this->getOffset($location->getListGroupIndex())->add($event);
                } catch (\Exception $e) {
                }
            }
        } else {
            try {
                $chain = new PriorityChain($event);
                $location = $chain->getResult();

                $this->getOffset($location->getListGroupIndex())->add($event);
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * The name of the Event List Group currently being processed
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getCurrentName($key)
    {
        return $this->getMapping()->getName($key);
    }

    /**
     * Mark this series id as having been processed
     *
     * @param int $seriesId
     */
    private function finish($seriesId)
    {
        $this->processedSeriesIds[] = $seriesId;
    }

    /**
     * Sort events in each Collection in a unique way
     *
     * Approved and Rejected are reverse chronological
     * Everything else is chronological
     */
    public function sortEventsByStartDate()
    {
        $reverseOrder = array(
            $this->getMapping()->getApprovedIndex(),
            $this->getMapping()->getRejectedIndex()
        );

        for ($i = 0; $i < $this->count(); $i++) {
            $currentGroup = $this->getOffset($i);

            if (in_array($i, $reverseOrder)) {
                $currentGroup->sortBy('startDateTime', 'desc');
            } else {
                $currentGroup->sortBy('startDateTime', 'asc');
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * Set processed series ids
     *
     * @param array $processedSeriesIds
     */
    private function setProcessedSeriesIds($processedSeriesIds)
    {
        $this->processedSeriesIds = $processedSeriesIds;
    }

    /**
     * Get processed series ids
     *
     * @return array
     */
    private function getProcessedSeriesIds()
    {
        return $this->processedSeriesIds;
    }

    /**
     * Set mapping
     *
     * @param Mapping $mapping
     */
    private function setMapping($mapping)
    {
        $this->mapping = $mapping;
    }

    /**
     * Get mapping
     *
     * @return Mapping
     */
    private function getMapping()
    {
        return $this->mapping;
    }
}
