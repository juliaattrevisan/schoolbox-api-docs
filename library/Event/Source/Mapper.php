<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Event\Source;

use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\Relation\CategoryMapper;
use Schoolbox\Calendar\Relation\PersonalMapper;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     * @throws BaseMapper\Exception If unable to derive an ID for mapped joins
     */
    protected function mapping()
    {
        $this->hasTable('event_source');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('event_id', EventMapper::class, 'id', null, null, 'event', 'event');
        $this->uses('source_name', self::TYPE_STRING);
        $this->uses('guid', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Delete events using a given $sourceName
     *
     * @param string $sourceName
     */
    public function deleteEventsFromSource($sourceName)
    {
        $query = "
            SELECT 
            {$this->getTable()}.`event_id`
            FROM {$this->getTable()} 
            INNER JOIN `event` ON {$this->getTable()}.`event_id` = event.`id`
            WHERE {$this->getTable()}.`source_name` = :sourceName
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'sourceName' => $sourceName
        ]);

        $eventIds = $statement->fetchAll(\PDO::FETCH_COLUMN);

        if (count($eventIds)) {

            /* Delete relations
             */
            PersonalMapper::getInstance()->deleteByColumn('event_id', $eventIds);
            CategoryMapper::getInstance()->deleteByColumn('event_id', $eventIds);

            /* Hard-delete the events themselves
             */
            $query = "
                DELETE FROM `event` 
                WHERE `event`.id IN (:eventIds)
            ";

            $statement = $this->database->prepare($query);
            $statement->execute([
                'eventIds' => $eventIds
            ]);

            /* Hard-delete the event_source records
             */
            $query = "
                DELETE FROM {$this->getTable()}
                WHERE source_name = :sourceName
            ";

            $statement = $this->database->prepare($query);
            $statement->execute([
                'sourceName' => $sourceName
            ]);
        }
    }
}