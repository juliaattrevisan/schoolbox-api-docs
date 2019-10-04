<?php
/**
 * Weather
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Match;

use \Schoolbox\Fixtures\Match\Model as Match;
use Schoolbox\Weather\WeatherstackClient;

class Weather
{

    /**
     * @var Match
     */
    private $match;

    /**
     * Forecast length. If the match occurs beyond this many days
     * into the future, don't bother trying to find weather data
     *
     * @var int
     */
    private $forecastLength = 7;

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
     * The weather API
     *
     * @var WeatherstackClient
     */
    private $api;

    /**
     * Weather constructor.
     *
     * @param Match              $match
     * @param WeatherstackClient $api
     */
    public function __construct(Match $match, WeatherstackClient $api)
    {
        $this->match = $match;
        $this->api   = $api;
    }

    /**
     * Load the forecast
     *
     */
    public function loadForecast(): void
    {
        $forecast = $this->findWeather($this->match);

        if (!empty($forecast)) {
            $this->icon      = $forecast['hourly'][4]['weather_icons'][0];
            $this->condition = $forecast['hourly'][4]['weather_descriptions'][0];
            $this->temp      = $forecast['avgtemp'];
        }
    }

    /**
     * Find the weather forecast for this particular day
     *
     * @param Match $match
     * @return array
     */
    private function findWeather(Match $match): array
    {
        $matchDayDiff = \Alaress_Date_Format::now()->diffInDays($match->getStartAt(), false);
        $matchDay     = $match->getStartAt();

        /* Only return data if the match hasn't already occurred
         */
        if ($matchDayDiff >= 0) {

            /* Extract all the datums
             * Don't bother if the match day is too far into the future (10 days)
             */
            if ($matchDayDiff < $this->forecastLength) {
                $forecast = $this->api->getForecastByDate($matchDay, $match->getVenue()->getLatitude(), $match->getVenue()->getLongitude());
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
