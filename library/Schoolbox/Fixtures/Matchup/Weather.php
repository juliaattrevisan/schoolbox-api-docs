<?php

/**
 * Weather
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Matchup;

use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\Weather\WeatherstackClient;

class Weather
{
    /**
     * Forecast length. If the match occurs beyond this many days
     * into the future, don't bother trying to find weather data
     */
    private const FORECAST_LENGTH = 7;


    /**
     * The icon to display
     *
     * @var string
     */
    private $icon = '';


    /**
     * The weather condition to display
     *
     * @var string
     */
    private $condition = '';


    /**
     * Temperature to display
     *
     * @var string
     */
    private $temp = '';


    /**
     * Did we find any data?
     *
     * @var bool
     */
    private $hasResults = false;


    /**
     * Weather constructor.
     *
     * @param Matchup            $matchup
     * @param WeatherstackClient $api
     */
    public function __construct(private Matchup $matchup, private WeatherstackClient $api)
    {
    }


    /**
     * Load the forecast
     */
    public function loadForecast(): self
    {
        $forecast = $this->findWeather($this->matchup);

        if (!empty($forecast)) {
            $this->icon      = $forecast['hourly'][4]['weather_icons'][0];
            $this->condition = $forecast['hourly'][4]['weather_descriptions'][0];
            $this->temp      = $forecast['avgtemp'];
        }

        return $this;
    }

    /**
     * Find the weather forecast for this particular day
     *
     * @param Matchup $matchup
     * @return array
     */
    private function findWeather(Matchup $matchup): array
    {
        $matchDayDiff = \Alaress_Date_Format::now()->diffInDays($matchup->getStartAt(), false);
        $matchDay     = $matchup->getStartAt();

        /* Only return data if the match hasn't already occurred
         */
        if ($matchDayDiff >= 0) {
            /* Extract all the datums
             * Don't bother if the match day is too far into the future (10 days)
             * Don't bother if the match hasn't got a venue
             * Dont' bother if the venue hasn't got any lat/long coords
             *     (imported from clipboard, since their venues don't have lat/long coords)
             */
            if (
                $matchDayDiff < self::FORECAST_LENGTH &&
                null !== $matchup->getVenue() &&
                '' !== trim((string)$matchup->getVenue()->getLatitude()) &&
                '' !== trim((string)$matchup->getVenue()->getLongitude())
            ) {
                $forecast = $this->api->getForecastByDate($matchDay, $matchup->getVenue()->getLatitude(), $matchup->getVenue()->getLongitude());

                if (!empty($forecast)) {
                    $this->hasResults = true;
                }

                return $forecast;
            }
        }

        return [];
    }

    /* Getters/Setters
     */

    /**
     * @return bool
     */
    public function hasResults(): bool
    {
        return $this->hasResults;
    }

    /**
     * @return string
     */
    public function getCondition(): string
    {
        return $this->condition;
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * @return string
     */
    public function getTemp(): string
    {
        return $this->temp;
    }
}
