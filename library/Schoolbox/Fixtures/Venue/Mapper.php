<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Fixtures\Venue;

use Schoolbox\Column\Decimal;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Fixtures\Venue\Model as Venue;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * @method Venue createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /** @const int DEC_PLACES_LATITUDE Matches Google Maps API, accurate to ~1cm */
    const DECIMAL_PLACES_LATITUDE = 7;

    /** @const int DEC_PLACES_LONGITUDE Matches Google Maps API, accurate to ~1cm */
    const DECIMAL_PLACES_LONGITUDE = 7;

    /**
     * Override default decimal places
     */
    public function __construct(){
        parent::__construct();

        /** @var Decimal $latitudeHydrator */
        $latitudeHydrator = $this->getMap()['latitude']['hydrator'];
        $latitudeHydrator->setPlaces(self::DECIMAL_PLACES_LATITUDE);

        /** @var Decimal $longitudeHydrator */
        $longitudeHydrator = $this->getMap()['longitude']['hydrator'];
        $longitudeHydrator->setPlaces(self::DECIMAL_PLACES_LONGITUDE);
    }

    /**
     * Setup mapping
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_venue');
        $this->hasModel(Venue::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('latitude', self::TYPE_DECIMAL);
        $this->uses('longitude', self::TYPE_DECIMAL);
        $this->uses('address', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Find all the Venues whose names are like $keyword
     *
     * @param string $keyword
     * @return \Schoolbox\Collection
     * @throws MapperException
     */
    public function findByName($keyword)
    {
        $pb = $this->getPredicateBuilder();
        $pb->like('name', "%{$this->getDatabase()->quoteForLike($keyword)}%");
        return $this->findByPredicates($pb);
    }
}
