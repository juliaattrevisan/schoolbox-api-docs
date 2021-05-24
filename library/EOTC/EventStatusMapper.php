<?php

/**
 * EventStatusMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC;

use Schoolbox\Collection;

/**
 * Class EventStatusMapper
 *
 * @package EOTC
 * @method EventStatus findById($id)
 * @method Collection|EventStatus[] findAll($options = [])
 */
class EventStatusMapper extends \Schoolbox\Mapper
{

    /* These consts are referenced in numerous places but
     * for the same context.
     */
    const STATUS_DEFAULT = 1;

    const STATUS_DRAFT = 1;
    const STATUS_INPRINCIPLE = 2;
    const STATUS_DETAILSCOMPLETED = 3;
    const STATUS_READYFORFINALAPPROVAL = 4;
    const STATUS_AWAITINGFINALAPPROVAL = 4;
    const STATUS_APPROVED = 5;
    const STATUS_REJECTED = 6;

    const DEFAULT_FROM_DATE_FOR_REVIEW = '-6 month';
    const DEFAULT_FROM_DATE_FOR_PROCESSED = '-1 month';

    /**
     * Mapping
     */
    public function mapping()
    {
        $this->hasTable('eotc_status');
        $this->hasModel(EventStatus::class);
        $this->hasMap(array(
            'column' => 'id',
            'property' => 'id',
            'type' => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column' => 'name',
            'property' => 'name',
            'type' => self::TYPE_STRING,
        ));
    }

    /**
     * Find default status object
     *
     * @return object
     */
    public function findDefaultStatus()
    {
        return $this->findById($this->getDefaultStatusId());
    }

    /**
     * The list of statuses that can be assigned via permissions
     */
    public function findPermissionStatuses()
    {
        $allStatuses = $this->findAll();

        /* Ignore Approved and Rejected statuses
         */
        $exclusions = array(self::STATUS_APPROVED, self::STATUS_REJECTED);

        $collection = new Collection();

        foreach ($allStatuses as $currentStatus) {
            if (!in_array($currentStatus->getId(), $exclusions)) {
                $collection->add($currentStatus);
            }
        }

        return $collection;
    }

    /**
     * Find the next status for an event
     *
     * @param Event $event
     * @return int
     */
    public function findNextStatus($event)
    {
        $nextStatusId = -1;

        try {
            $chain = new ListGroup\Chain($event);
            $nextStatusId = $chain->getResult()->getNextStatus();
        } catch (\Exception $e) {
        }

        return $nextStatusId;
    }

    /**
     * Find the available statuses for a given event and its
     * configuration - there should on be the current status,
     * the next status, and Rejected
     *
     * @param Event $event
     * @return \Schoolbox\Collection
     */
    public function findAvailableStatuses($event)
    {
        $nextStatusId = -1;

        try {
            $chain = new ListGroup\Chain($event);
            $nextStatusId = $chain->getResult()->getNextStatus();
        } catch (\Exception $e) {
        }

        /* Only three options at a time
         */
        return $this->findByColumn('id', array(
            $event->getMetadata()->getStatus()->getId(),
            $this->getRejectedStatusId(),
            $nextStatusId,
        ));
    }

    /* Getters/Setters
     */

    /**
     * Get default status id
     *
     * @return int
     */
    private function getDefaultStatusId()
    {
        return self::STATUS_DEFAULT;
    }

    /**
     * Get rejected status id
     *
     * @return int
     */
    private function getRejectedStatusId()
    {
        return self::STATUS_REJECTED;
    }
}
