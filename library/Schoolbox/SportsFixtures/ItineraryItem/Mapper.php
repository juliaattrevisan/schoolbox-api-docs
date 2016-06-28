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
use Schoolbox\SportsFixtures\Venue\Mapper as VenueMapper;
use Schoolbox\SportsFixtures\ItineraryItem\Model as ItineraryItem;


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
        $this->joins('venue_id', VenueMapper::class, 'id', null, null, 'venue', 'venue');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('start_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}