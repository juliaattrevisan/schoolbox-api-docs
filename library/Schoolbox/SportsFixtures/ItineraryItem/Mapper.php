<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\SportsFixtures\ItineraryItem;

use Schoolbox\Mapper As BaseMapper;
use Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use Schoolbox\SportsFixtures\Venue\Model as Venue;
use Schoolbox\SportsFixtures\Venue\Mapper as VenueMapper;
use Schoolbox\SportsFixtures\ItineraryItem\Model as ItineraryItem;
use \Schoolbox\Mapper\Exception as MapperException;

/**
 * Class Mapper
 * @method ItineraryItem createModel(int $idValue = null)
 * @package Schoolbox\SportsFixtures\ItineraryItem
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_match_itineraryitem');
        $this->hasModel(ItineraryItem::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('match_id', MatchMapper::class, 'id', null, null, 'match', 'match');
        $this->uses('description', self::TYPE_STRING);
        $this->uses('start_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Find a Match by its id
     *
     * Also include both Teams
     *
     * @param $id
     * @return ItineraryItem
     * @throws MapperException
     */
    public function findById($id)
    {
        $query = "
            SELECT
                {$this->getColumnString('i')}
            FROM {$this->getTable()} `i`
            WHERE i.id = ?
            AND i.deleted_at IS NULL
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([$id]);

        if ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            /** @var ItineraryItem $itineraryItem */
            $itineraryItem = $this->hydrate($row, 'i');

            return $itineraryItem;
        }

        throw new MapperException('Instance of object not found');
    }
}