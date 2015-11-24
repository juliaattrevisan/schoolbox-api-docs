<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Synchronise\Synergetic\Diocesan\Hydrator;


use Synchronise_Base_IHydrator;

class House implements Synchronise_Base_IHydrator
{
    const HOUSE_CO = 1;
    const HOUSE_CW = 2;
    const HOUSE_ED = 3;
    const HOUSE_MI = 4;
    const HOUSE_MP = 5;
    const HOUSE_NE = 6;
    const HOUSE_RO = 7;
    const HOUSE_SE = 8;

    const BOARD_INNES = 1;

    /** @var array */
    protected $map;


    /**
     * Cache consts into a map
     */
    function __construct()
    {
        $rc = new \ReflectionClass($this);

        $this->map = array();
        foreach ($rc->getConstants() as $name => $value)
        {
            if (strpos($name, 'HOUSE_') === 0) {
                $this->map[substr($name, 6)] = $value;
            }
        }
    }


    /**
     * Converts data from the format from external database to the format used
     * in Schoolbox.
     *
     * This method should modify and return $newUser.
     *
     * @param  array $externalUser A result from the external database.
     * @param  array $newUser      The current result that will be inserted into
     *                             Schoolbox.
     *                             This result could have come from a hydrate()
     *                             call on another class that implements this
     *                             interface.
     * @return array The result to insert into Schoolbox,
     */
    public function hydrate($externalUser, $newUser)
    {
        /*
         * ALWAYS house MAPS externalUser.StudentHouse AS [
         *   'CO' => Cochrane (1),
         *   'CW' => Cowie (2)
         *   'ED' => Edwards (3)
         *   'MI' => Mitchelson (4)
         *   'MP' => Mary Pulling (5)
         *   'NE' => Neligan (6)
         *   'RO' => Roberton (7)
         *   'SE' => Selwyn (8)
         * ]
         */
        if (in_array($externalUser['StudentHouse'], $this->map)) {
            $newUser['house'] = $this->map[$externalUser['StudentHouse']];
        }

        /*
         * IF externalUser.StudentBoardingHouse NOT BLANK
         * THEN residentialHouse = Innes House (1)
         */
        // boarder?
        if ($externalUser['StudentBoardingHouse']) {
            $newUser['residential_house_id'] = self::BOARD_INNES;
        }

        return $newUser;
    }

}
