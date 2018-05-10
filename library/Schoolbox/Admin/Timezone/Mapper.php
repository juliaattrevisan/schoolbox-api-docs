<?php

namespace Schoolbox\Admin\Timezone;

use DateTimeZone;

/**
 * Class Mapper
 *
 * a:1:{s:6:"mapper";s:31:"Schoolbox\Admin\Timezone\Mapper";}
 *
 * @package Schoolbox\Admin
 */
class Mapper
{
    /**
     * @return Mapper
     */
    public static function getInstance()
    {
        return new Mapper();
    }

    /**
     * @return array
     */
    public function findAll()
    {
        $regions = array(
            'Africa' => DateTimeZone::AFRICA,
            'America' => DateTimeZone::AMERICA,
            'Antarctica' => DateTimeZone::ANTARCTICA,
            'Asia' => DateTimeZone::ASIA,
            'Atlantic' => DateTimeZone::ATLANTIC,
            'Australia' => DateTimeZone::AUSTRALIA,
            'Europe' => DateTimeZone::EUROPE,
            'Indian' => DateTimeZone::INDIAN,
            'Pacific' => DateTimeZone::PACIFIC
        );

        $options = [];

        foreach ($regions as $name => $mask)
        {
            $zones = DateTimeZone::listIdentifiers($mask);

            foreach($zones as $timezone)
            {
                $options [] = new Model($timezone);
            }
        }

        return $options;
    }
}

