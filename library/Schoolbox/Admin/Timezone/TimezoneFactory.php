<?php

namespace Schoolbox\Admin\Timezone;

use DateTimeZone;
use Schoolbox\Column\Date;

/**
 * Class TimezoneFactory
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
        $gmt = new DateTimeZone("Europe/London");
        $gmtTimezone = new \DateTime("now", $gmt);

        foreach ($regions as $name => $mask)
        {
            $zones = DateTimeZone::listIdentifiers($mask);

            foreach($zones as $timezone)
            {
                $dateTimeZone = new DateTimeZone($timezone);
                $offset = $dateTimeZone->getOffset($gmtTimezone);

                $options[] = new Timezone($timezone, $offset);
            }
        }

        usort($options,function ($a, $b){
            $a = $a->getName();
            $b = $b->getName();


            return strcmp($a, $b);
        });

        return $options;
    }
}

