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
use Schoolbox\Calendar\Relation\CampusMapper as EventCampusMapper;
use Schoolbox\Calendar\Relation\RoleMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class Mapper
 *
 * @package Event\Source
 * @method Model createModel($id = null)
 */

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
     * Find a list of eventIds that originated from $sourceName
     *
     * @param $sourceName
     * @return array
     * @throws BaseMapper\DefinitionException
     */
    private function findEventIdsUsingSource($sourceName)
    {
        $query = "
            SELECT 
            {$this->getTable()}.`event_id`
            FROM {$this->getTable()} 
            INNER JOIN {$this->getJoinSql('event')}
            WHERE {$this->getTable()}.`source_name` = :sourceName
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'sourceName' => $sourceName
        ]);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }


    /**
     * Delete events using a given $sourceName
     *
     * @param string $sourceName
     * @throws BaseMapper\DefinitionException
     */
    public function deleteEventsFromSource($sourceName)
    {
        $eventIds = $this->findEventIdsUsingSource($sourceName);

        if (count($eventIds)) {

            /* Delete relations
             */
            PersonalMapper::getInstance()->deleteByColumn('event_id', $eventIds);
            CategoryMapper::getInstance()->deleteByColumn('event_id', $eventIds);
            EventCampusMapper::getInstance()->deleteByColumn('event_id', $eventIds);
            RoleMapper::getInstance()->deleteByColumn('event_id', $eventIds);

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

            /* Hard-delete the events themselves
             */
            $deleteEvents = EventMapper::getInstance()->findByColumn('id', $eventIds);
            EventMapper::getInstance()->hardDelete($deleteEvents);

        }
    }
}