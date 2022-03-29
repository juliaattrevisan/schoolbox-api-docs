<?php

/**
 * CalendarEventTypeMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar;

use Schoolbox;
use Schoolbox\Collection;

/**
 * Class EventTypeMapper
 *
 * @package Schoolbox\Calendar
 *
 * @method Collection|EventType[] findAll($options = array())
 * @method EventType createModel()
 * @method EventType hydrate(array $row, $alias = '', $callback = null)
 */
class EventTypeMapper extends Schoolbox\Mapper
{

    protected function mapping()
    {
        $this->hasTable('luEventType');
        $this->hasModel(EventType::class);
        $this->hasMap(
            array(
                'column'     => 'eventTypeID',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'eventType',
                'property' => 'type',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'eventColour',
                'property' => 'color',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            [
                'column'   => 'sequence',
                'property' => 'sequence',
                'type'     => self::TYPE_INTEGER,
            ]
        );
        $this->hasMap(
            [
                'column'   => 'updated_by',
                'property' => 'updatedBy',
                'type'     => self::TYPE_INTEGER,
            ]
        );

        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /** @inheritDoc */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }


    /**
     * If $eventTypeId corresponds to an existing and extant luEventType,
     * return that
     *
     * Otherwise, find the first available luEventType and return its id
     *
     * If your luEventType is empty, return null
     *
     * @param mixed $eventTypeId
     * @return int|null
     */
    public function findFirstMeaningfulId($eventTypeId): ?int
    {
        try {
            $eventTypeExists = $this->findById($eventTypeId);
            $id = $eventTypeExists->getId();
        } catch (\Schoolbox\Mapper\Exception $e) {
            $firstEventType = $this->findAll()->first();

            if (!$firstEventType) {
                return null;
            }

            $id = $firstEventType->getId();
        }

        return (int)$id;
    }
}
