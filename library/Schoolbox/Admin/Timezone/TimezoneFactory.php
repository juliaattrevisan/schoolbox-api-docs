<?php

namespace Schoolbox\Admin\Timezone;

use DateTimeZone;

/**
 * Class Mapper
 *
 * @package Schoolbox\Admin
 */
class TimezoneFactory
{
    /**
     * @return TimezoneFactory
     */
    public static function getInstance()
    {
        return new TimezoneFactory();
    }

    /**
     * @return array
     */
    public function findAll()
    {
        $regions = [
            'Africa' => DateTimeZone::AFRICA,
            'America' => DateTimeZone::AMERICA,
            'Antarctica' => DateTimeZone::ANTARCTICA,
            'Asia' => DateTimeZone::ASIA,
            'Atlantic' => DateTimeZone::ATLANTIC,
            'Australia' => DateTimeZone::AUSTRALIA,
            'Europe' => DateTimeZone::EUROPE,
            'Indian' => DateTimeZone::INDIAN,
            'Pacific' => DateTimeZone::PACIFIC
        ];

        $options = [];

        foreach ($regions as $name => $mask)
        {
            $zones = DateTimeZone::listIdentifiers($mask);

            foreach($zones as $timezone)
            {
                $options[] = new Timezone($timezone);
            }
        }

        return $options;
    }
}

