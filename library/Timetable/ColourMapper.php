<?php
/**
 * Timetable Colour Data Mapper
 *
 * Defines the mapping for the timetable colour lookup table
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\GenericCollection;

/**
 * @method GenericCollection|Timetable_Colour[] findAll($options = [])
 */
class Timetable_ColourMapper extends Schoolbox\Mapper
{

    /**
     * @var string
     */
    protected $table = 'lu_timetable_colour';

    /**
     * @inheritDoc
     */
    protected function mapping()
    {
        $this->hasTable('lu_timetable_colour');
        $this->hasModel('Timetable_Colour');
        $this->hasMap(
            [
                'column'     => 'timetable_colour_id',
                'property'   => 'timetableColourId',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            ]
        );

        $this->hasMap(
            [
                'column'   => 'hex_colour',
                'property' => 'hexColour',
                'type'     => self::TYPE_STRING,
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getSetMethodName($property)
    {
        return 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $property)));
    }

    /**
     * @inheritDoc
     */
    public function getGetMethodName($property)
    {
        return 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $property)));
    }
}
