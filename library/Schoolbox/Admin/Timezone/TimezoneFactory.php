<?php

namespace Schoolbox\Admin\Timezone;

use DateTimeZone;
use Schoolbox\Collection;
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
        $zones = DateTimeZone::listIdentifiers(
            DateTimeZone::AFRICA |
            DateTimeZone::AMERICA |
            DateTimeZone::ANTARCTICA |
            DateTimeZone::ASIA |
            DateTimeZone::ATLANTIC |
            DateTimeZone::AUSTRALIA |
            DateTimeZone::EUROPE |
            DateTimeZone::INDIAN |
            DateTimeZone::PACIFIC
        );

        $options = new Collection();
        $gmt = new DateTimeZone("Europe/London");
        $gmtTimezone = new \DateTime("now", $gmt);

        foreach($zones as $timezone)
        {
            $dateTimeZone = new DateTimeZone($timezone);
            $offset = $dateTimeZone->getOffset($gmtTimezone);

            $options->add(new Timezone($timezone, $offset));
        }

        usort($options,function ($a, $b){
            $a = $a->getName();
            $b = $b->getName();


            return strcmp($a, $b);
        });

        return $options;
    }
}

