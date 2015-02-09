<?php
/**
 * CategoryMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Calendar\Relation;

use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper;

class CategoryMapper extends Schoolbox\External\Mapper
{

    /**
     * Mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('event_category');
        $this->hasModel('\\Schoolbox\\Calendar\\Relation\\Category');

        $this->hasMap(
            array(
                'column' => 'event_id',
                'property' => 'eventId',
                'type' => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column' => 'category_id',
                'property' => 'categoryId',
                'type' => self::TYPE_INTEGER,
            )
        );
        $this->hasJoin(
            'category',
            array(
                'mapper' => '\\Schoolbox\\Resource\\CategoryMapper',
                'this_key' => 'category_id',
                'other_key' => 'id',
            )
        );
    }

    /**
     * Purge existing sid assignations
     *
     * @param Schoolbox\Calendar\Event $event
     */
    public function cleanse(Schoolbox\Calendar\Event $event)
    {
        $statement = $this->database->prepare(
            "
			DELETE FROM `{$this->table}`
			WHERE `event_id` = :identifierValue
		"
        );

        $statement->bindValue(':identifierValue', $event->getId());
        $statement->execute();
    }

    /**
     * Find all the categories belonging to an event
     *
     * @param Schoolbox\Calendar\Event $event
     * @return Collection
     */
    public function findAllForEvent(Schoolbox\Calendar\Event $event)
    {
        $categoryMapper = $this->getJoinMapper('category');

        $query = "
            SELECT {$categoryMapper->getColumnString('c')}
            FROM `{$this->table}` ec
            LEFT JOIN {$this->getJoinSql('category', 'ec', 'c')}
            WHERE ec.event_id = :eventId
        ";

        $statement = $this->database->prepare($query);
        $statement->bindValue(':eventId', $event->getId());
        $statement->execute();

        $allCategories = new Collection();

        while ($row = $statement->fetch()) {
            $allCategories->add($categoryMapper->hydrate($row, 'c'));
        }

        return $allCategories;
    }

    /**
     * This mapper doesn't raise notifications - the event mapper handles that.
     *
     * @param object $object
     * @param string $verb
     * @param null   $previous
     *
     * @return true
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
