<?php

namespace Schoolbox\Admin\Timezone;

use DateTimeZone;
use Schoolbox\Collection;

/**
 * Class TimezoneFactory
 * @package Schoolbox\Admin
 */
class TimezoneFactory
{

    /**
     * @var TimezoneFactory
     */
    private static $instance;

    /**
     * @return TimezoneFactory
     */
    public static function getInstance(): TimezoneFactory
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return Collection|Timezone[]
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

        foreach ($zones as $timezone) {
            $dateTimeZone = new DateTimeZone($timezone);
            $date = new \DateTime('now', $dateTimeZone);

            $options->add(new Timezone($timezone, $date->format('P')));
        }

        /* Because the GMT offset is now a prefix on the name, this will sort by
         * GMT offset rather than the timezone name itself
         */
        $options->sortByMultiFields([
            'offset' => 'asc',
            'id'     => 'asc',
        ]);

        return $options;
    }
}
